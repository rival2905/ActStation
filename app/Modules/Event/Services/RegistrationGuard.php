<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\CommunityLookup;
use App\Modules\Event\Exceptions\RegistrationGuardException;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrationGuard
{
    public function __construct(
        private readonly CommunityLookup $communityLookup,
    ) {}

    public function validate(
        Event $event,
        EventCategory $category,
        ?int $communityId,
        string $teamName,
        ?EventParticipant $ignoring = null,
        bool $allowWaitingList = false,
        ?string $registrationSource = null,
        ?string $communitySubtype = null,
        bool $invitationAuthorized = false,
    ): void {
        $now = Carbon::now();

        if ($event->status !== 'published') {
            throw new RegistrationGuardException("Pendaftaran hanya dibuka untuk event berstatus published (status saat ini: {$event->status}).");
        }

        if (! $event->registration_open) {
            throw new RegistrationGuardException('Pendaftaran event saat ini sedang ditutup.');
        }

        if ($event->registration_deadline !== null && $now->greaterThan($event->registration_deadline)) {
            throw new RegistrationGuardException('Batas waktu pendaftaran event telah berakhir.');
        }

        $flow = (string) $category->registration_flow;
        $fee = (float) $category->registration_fee;

        if (($flow === 'payment_first' && $fee <= 0) || ($flow === 'instant_free' && $fee > 0)) {
            throw new RegistrationGuardException('Konfigurasi alur dan biaya kategori tidak valid.');
        }
        $usesRegistrationWindow = $flow === 'payment_first' || ($flow === 'by_invitation' && $fee > 0);
        if ($usesRegistrationWindow
            && ($category->registration_start === null || $category->registration_end === null
                || $category->payment_start === null || $category->payment_end === null
                || $event->starts_at === null
                || $category->registration_start->greaterThan($category->payment_start)
                || ! $category->payment_start->lessThan($category->registration_end)
                || ! $category->registration_end->equalTo($category->payment_end)
                || $category->payment_end->greaterThan($event->starts_at)
                || ($event->registration_deadline !== null && $event->registration_deadline->lessThan($category->registration_end)))) {
            throw new RegistrationGuardException('Konfigurasi jendela pendaftaran dan pembayaran kategori tidak valid.');
        }

        if ($usesRegistrationWindow) {
            if ($category->registration_start === null || $category->registration_end === null) {
                throw new RegistrationGuardException('Jendela pendaftaran kategori belum dikonfigurasi.');
            }

            if ($now->lessThan($category->registration_start)) {
                throw new RegistrationGuardException('Jendela pendaftaran kategori belum dibuka.');
            }

            if ($now->greaterThan($category->registration_end)) {
                throw new RegistrationGuardException('Jendela pendaftaran kategori telah berakhir.');
            }
        }

        if ($flow === 'by_invitation' && ! $invitationAuthorized) {
            throw new RegistrationGuardException('Kategori ini hanya menerima pendaftaran dengan undangan event yang sah.');
        }

        if ($communityId !== null) {
            $community = $this->communityLookup->find($communityId);
            if ($community === null || $community->status !== 'verified') {
                throw new RegistrationGuardException('Komunitas pendaftar harus terdaftar dan berstatus verified.');
            }

            $actualSubtype = (string) $community->subtype;
            $actualSource = $actualSubtype === 'club' ? 'club' : 'community';
            if (($registrationSource !== null && $registrationSource !== $actualSource)
                || ($communitySubtype !== null && $communitySubtype !== $actualSubtype)) {
                throw new RegistrationGuardException('Sumber atau subtipe komunitas pada pendaftaran tidak sesuai dengan profil komunitas.');
            }
            $sourceAllowed = ($actualSubtype === 'club' && $category->allow_club)
                || ($category->allow_community && ($category->community_subtype === null || $category->community_subtype === $actualSubtype));

            if (! $sourceAllowed) {
                throw new RegistrationGuardException('Sumber atau subtipe komunitas tidak diizinkan pada kategori ini.');
            }
            $hasRegistered = $this->activeParticipants($category->participants(), $now)
                ->when($ignoring, fn ($query) => $query->where('id', '!=', $ignoring->id))
                ->where('community_id', $communityId)
                ->exists();

            if ($hasRegistered) {
                throw new RegistrationGuardException('Komunitas hanya diperbolehkan mendaftarkan 1 tim per kategori event.');
            }
        }

        if ($event->max_participants !== null) {
            $activeParticipants = $this->activeParticipants($event->participants(), $now)
                ->when($ignoring, fn ($query) => $query->where('id', '!=', $ignoring->id))
                ->count();

            if ($activeParticipants >= $event->max_participants) {
                throw new RegistrationGuardException("Kuota peserta event sudah penuh ({$event->max_participants} peserta).");
            }
        }

        if ($flow !== 'screening_first' && $category->max_teams !== null && ! ($allowWaitingList && $category->enable_waiting_list)) {
            $activeTeams = $this->activeParticipants($category->participants(), $now)
                ->when($ignoring, fn ($query) => $query->where('id', '!=', $ignoring->id))
                ->count();

            if ($activeTeams >= $category->max_teams) {
                throw new RegistrationGuardException("Kuota tim untuk kategori ini sudah penuh ({$category->max_teams} tim).");
            }
        }

        $trimmedName = trim($teamName);
        $nameConflict = $category->participants()
            ->when($ignoring, fn ($query) => $query->where('id', '!=', $ignoring->id))
            ->whereRaw('LOWER(team_name) = ?', [mb_strtolower($trimmedName)])
            ->exists();

        if ($nameConflict) {
            throw new RegistrationGuardException("Nama tim [{$trimmedName}] sudah terdaftar di kategori ini.");
        }
    }

    private function activeParticipants(HasMany $query, Carbon $now): HasMany
    {
        return $query->where(function ($active) use ($now): void {
            $active->whereIn('status', ['registered', 'revision_needed', 'verified', 'confirmed'])
                ->orWhere(function ($pending) use ($now): void {
                    $pending->where('status', 'pending_payment')
                        ->where(function ($deadline) use ($now): void {
                            $deadline->whereNull('payment_deadline_at')
                                ->orWhere('payment_deadline_at', '>=', $now);
                        });
                });
        });
    }
}
