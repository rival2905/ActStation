<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Modules\Event\Events\EventCancelled;
use App\Modules\Event\Events\EventCompleted;
use App\Modules\Event\Events\EventPublished;
use App\Modules\Event\Exceptions\InvalidLifecycleTransitionException;
use App\Modules\Event\Models\ActivityRegistration;
use App\Modules\Event\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EventLifecycleService
{
    /**
     * Transition event to given status.
     */
    public function transition(Event $event, string $targetStatus, ?string $reason = null): void
    {
        match ($targetStatus) {
            'pending_approval' => $this->submitReview($event),
            'published' => $event->status === 'pending_approval' ? $this->approve($event) : $this->publish($event),
            'draft' => $this->reject($event, (string) $reason),
            'ongoing' => $this->start($event),
            'cancelled' => $this->cancel($event, (string) $reason),
            'completed' => $this->complete($event),
            'archived' => $this->archive($event),
            default => throw new InvalidLifecycleTransitionException("Status target [{$targetStatus}] tidak dikenal."),
        };
    }

    /**
     * draft -> published
     */
    public function publish(Event $event): void
    {
        if ($event->status !== 'draft' && $event->status !== 'pending_approval') {
            throw new InvalidLifecycleTransitionException("Hanya event berstatus draft yang dapat dipublikasikan (status saat ini: {$event->status}).");
        }
        if ($event->categories()->count() < 1) {
            throw new InvalidLifecycleTransitionException('Event harus memiliki minimal 1 kategori sebelum dipublikasikan.');
        }

        if ($event->starts_at === null || $event->ends_at === null) {
            throw new InvalidLifecycleTransitionException('Tanggal mulai dan selesai event wajib diisi sebelum dipublikasikan.');
        }

        if ($event->starts_at->greaterThanOrEqualTo($event->ends_at)) {
            throw new InvalidLifecycleTransitionException('Tanggal mulai event harus lebih awal dari tanggal selesai.');
        }

        DB::transaction(function () use ($event): void {
            $event->update(['status' => 'published']);
            event(new EventPublished($event));
        });
    }

    /**
     * draft -> pending_approval
     */
    public function submitReview(Event $event): void
    {
        if ($event->status !== 'draft') {
            throw new InvalidLifecycleTransitionException("Hanya event berstatus draft yang dapat diajukan untuk review (status saat ini: {$event->status}).");
        }

        if ($event->categories()->count() < 1) {
            throw new InvalidLifecycleTransitionException('Event harus memiliki minimal 1 kategori sebelum diajukan review.');
        }

        if ($event->starts_at === null || $event->ends_at === null) {
            throw new InvalidLifecycleTransitionException('Tanggal mulai dan selesai event wajib diisi sebelum diajukan review.');
        }

        if ($event->starts_at->greaterThanOrEqualTo($event->ends_at)) {
            throw new InvalidLifecycleTransitionException('Tanggal mulai event harus lebih awal dari tanggal selesai.');
        }

        $event->update(['status' => 'pending_approval']);
    }

    /**
     * pending_approval -> published
     */
    public function approve(Event $event): void
    {
        if ($event->status !== 'pending_approval') {
            throw new InvalidLifecycleTransitionException("Hanya event berstatus pending_approval yang dapat disetujui (status saat ini: {$event->status}).");
        }

        DB::transaction(function () use ($event): void {
            $event->update(['status' => 'published']);
            event(new EventPublished($event));
        });
    }

    /**
     * pending_approval -> draft
     */
    public function reject(Event $event, string $notes): void
    {
        if ($event->status !== 'pending_approval') {
            throw new InvalidLifecycleTransitionException("Hanya event berstatus pending_approval yang dapat ditolak (status saat ini: {$event->status}).");
        }

        $event->update([
            'status' => 'draft',
            'kyc_notes' => $notes,
        ]);
    }

    /**
     * published -> ongoing
     */
    public function start(Event $event): void
    {
        if ($event->status !== 'published') {
            throw new InvalidLifecycleTransitionException("Hanya event berstatus published yang dapat dimulai (status saat ini: {$event->status}).");
        }

        if ($event->starts_at === null || Carbon::now()->lessThan($event->starts_at)) {
            throw new InvalidLifecycleTransitionException('Event belum dapat dimulai sebelum tanggal mulai tercapai.');
        }

        $event->update(['status' => 'ongoing']);
    }

    /**
     * published|ongoing -> cancelled
     */
    public function cancel(Event $event, string $reason): void
    {
        if (! in_array($event->status, ['published', 'ongoing'], true)) {
            throw new InvalidLifecycleTransitionException("Hanya event berstatus published atau ongoing yang dapat dibatalkan (status saat ini: {$event->status}).");
        }

        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw new InvalidLifecycleTransitionException('Alasan pembatalan wajib diisi.');
        }

        DB::transaction(function () use ($event, $trimmedReason): void {
            $event->update([
                'status' => 'cancelled',
                'cancel_reason' => $trimmedReason,
            ]);

            event(new EventCancelled($event, $trimmedReason));
            ActivityRegistration::whereHas('activity', fn ($query) => $query->where('event_id', $event->id))
                ->whereNotIn('status', ['cancelled', 'rejected'])->update(['status' => 'needs_review']);
        });
    }

    /**
     * ongoing -> completed
     */
    public function complete(Event $event): void
    {
        if ($event->status !== 'ongoing') {
            throw new InvalidLifecycleTransitionException("Hanya event berstatus ongoing yang dapat diselesaikan (status saat ini: {$event->status}).");
        }

        $hasUnfinishedGames = $event->games()
            ->whereNotIn('game_status', ['finished', 'cancelled', 'bye'])
            ->exists();

        if ($hasUnfinishedGames) {
            throw new InvalidLifecycleTransitionException('Semua pertandingan harus berstatus finished, cancelled, atau bye sebelum event diselesaikan.');
        }

        DB::transaction(function () use ($event): void {
            $event->update(['status' => 'completed']);
            event(new EventCompleted($event));
        });
    }

    /**
     * completed -> archived
     */
    public function archive(Event $event): void
    {
        if ($event->status !== 'completed') {
            throw new InvalidLifecycleTransitionException("Hanya event berstatus completed yang dapat diarsipkan (status saat ini: {$event->status}).");
        }

        if ($event->ends_at === null || Carbon::now()->lessThan($event->ends_at->copy()->addDays(30))) {
            throw new InvalidLifecycleTransitionException('Event baru dapat diarsipkan minimal 30 hari setelah berakhir.');
        }

        $event->update(['status' => 'archived']);
    }
}
