<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\ActivityBilling;
use App\Contracts\CommunityLookup;
use App\Models\User;
use App\Modules\Event\Models\ActivityRegistration;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventActivity;
use App\Modules\Event\Models\EventParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ActivityPaymentService
{
    public function __construct(private readonly ActivityBilling $billing) {}

    public function show(Event $event, ActivityRegistration $registration, User $user): array
    {
        $this->authorize($event, $registration, $user);
        $invoice = $this->billing->find((int) $registration->id);
        abort_unless($invoice && (int) $registration->amount > 0, 404);

        return compact('event', 'registration', 'invoice');
    }

    public function upload(Event $event, ActivityRegistration $registration, User $user, array $data, string $ip, string $agent): void
    {
        $this->authorize($event, $registration, $user, false);
        Validator::make($data, ['waiver_agreed' => ['required', 'accepted'], 'payment_proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048']])->validate();
        $path = null;
        try {
            DB::transaction(function () use ($event, $registration, $data, $ip, $agent, &$path): void {
                EventActivity::whereKey($registration->event_activity_id)->lockForUpdate()->firstOrFail();
                $record = ActivityRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
                $freshEvent = $event->fresh();
                if (! $freshEvent->allow_manual_payment || ! in_array($freshEvent->status, ['published', 'ongoing'], true) || $record->status !== 'pending_payment') {
                    throw ValidationException::withMessages(['payment' => 'Pembayaran manual tidak tersedia.']);
                }
                $this->assertEligible($event, $record);
                $file = $data['payment_proof'];
                $path = $file->store('activity_payments/'.$record->id, 'local');
                if (! $path) {
                    throw new \RuntimeException('Penyimpanan bukti gagal.');
                }
                $this->billing->recordManualProof((int) $record->id, ['payment_proof' => $path, 'proof_name' => basename($file->getClientOriginalName()),
                    'is_escrow_waived' => true, 'waiver_signed_at' => now()->toIso8601String(), 'waiver_ip' => $ip, 'waiver_user_agent' => substr($agent, 0, 1000)]);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function confirm(Event $event, ActivityRegistration $registration, User $user): void
    {
        abort_unless(app(EventCommitteeAccess::class)->allows($event, $user, 'payments.confirm'), 403);
        abort_unless((int) $registration->activity?->event_id === (int) $event->id, 404);
        DB::transaction(function () use ($event, $registration): void {
            EventActivity::whereKey($registration->event_activity_id)->lockForUpdate()->firstOrFail();
            $record = ActivityRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            if ($record->status === 'confirmed' && $this->billing->paid((int) $record->id, (int) $record->amount)) {
                return;
            }
            $freshEvent = $event->fresh();
            if ($record->status !== 'pending_payment' || ! $freshEvent->allow_manual_payment || ! in_array($freshEvent->status, ['published', 'ongoing'], true)) {
                throw ValidationException::withMessages(['payment' => 'Pendaftaran tidak dapat dikonfirmasi.']);
            }
            $this->assertEligible($event, $record);
            $invoice = $this->billing->find((int) $record->id);
            $path = $invoice['meta']['payment_proof'] ?? '';
            if (! str_starts_with($path, 'activity_payments/'.$record->id.'/') || str_contains($path, '..') || ! Storage::disk('local')->exists($path)) {
                throw ValidationException::withMessages(['payment' => 'Bukti transfer tidak tersedia.']);
            }
            $this->billing->confirm((int) $record->id, (int) $record->amount);
            $record->update(['status' => 'confirmed']);
        });
    }

    public function download(Event $event, ActivityRegistration $registration, User $user)
    {
        $this->authorize($event, $registration, $user);
        $invoice = $this->billing->find((int) $registration->id);
        $path = $invoice['meta']['payment_proof'] ?? '';
        abort_unless(str_starts_with($path, 'activity_payments/'.$registration->id.'/') && ! str_contains($path, '..') && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $invoice['meta']['proof_name'] ?? 'bukti-pembayaran', ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function authorize(Event $event, ActivityRegistration $registration, User $user, bool $reviewer = true): void
    {
        abort_unless($user->is_active && (int) $registration->activity?->event_id === (int) $event->id, 403);
        abort_unless((int) $registration->user_id === (int) $user->id
            || ($registration->community_id && app(CommunityLookup::class)->canManage((int) $user->id, (int) $registration->community_id))
            || ($reviewer && app(EventCommitteeAccess::class)->allows($event, $user, 'payments.confirm')), 403);
    }

    private function assertEligible(Event $event, ActivityRegistration $registration): void
    {
        app(EventQuestionService::class)->assertActivityComplete($event, $registration);
        if ($registration->members()->whereNull('accepted_at')->exists() || ! $registration->members()->exists()
            || $registration->members()->whereDoesntHave('user')->exists()
            || $registration->members()->whereHas('user', fn ($q) => $q->where('is_active', false))->exists()) {
            throw ValidationException::withMessages(['consent' => 'Persetujuan seluruh peserta diperlukan.']);
        }
        if (! $registration->allocation_key) {
            return;
        }
        $parent = EventParticipant::where('event_id', $event->id)->whereIn('status', ['confirmed', 'attended']);
        $valid = str_starts_with($registration->allocation_key, 'institution:')
            ? $parent->where('community_id', $registration->community_id)->exists()
            : $parent->whereKey($registration->parent_participant_id)->exists();
        if (! $valid) {
            throw ValidationException::withMessages(['parent' => 'Pendaftaran induk perlu ditinjau.']);
        }
    }
}
