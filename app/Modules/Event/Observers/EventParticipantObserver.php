<?php

declare(strict_types=1);

namespace App\Modules\Event\Observers;

use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\EventActivityService;
use App\Modules\Event\Services\EventRegistrationEmailService;
use Illuminate\Support\Facades\DB;

class EventParticipantObserver
{
    public function __construct(
        protected EventRegistrationEmailService $emailService
    ) {}

    public function created(EventParticipant $participant): void
    {
        $status = (string) $participant->status;
        if (! in_array($status, ['registered', 'waiting_list', 'pending_payment'], true)) {
            return;
        }

        $snapshot = $this->emailService->createSnapshot($participant, 'created');

        DB::afterCommit(function () use ($snapshot): void {
            $this->emailService->sendNotification($snapshot);
        });
    }

    public function updated(EventParticipant $participant): void
    {
        if ($participant->wasChanged('status')) {
            app(EventActivityService::class)->reconcileParent($participant);
        }
        if (! $participant->wasChanged()) {
            return;
        }

        $transition = null;

        // 1. Highest priority: payment_verified_at newly set
        if ($participant->wasChanged('payment_verified_at')
            && $participant->payment_verified_at !== null
            && $participant->getOriginal('payment_verified_at') === null
        ) {
            $transition = 'payment_verified';
        } elseif ($participant->wasChanged('status')) {
            $newStatus = (string) $participant->status;
            if (in_array($newStatus, ['pending_payment', 'revision_needed', 'confirmed', 'cancelled', 'rejected', 'waiting_list', 'registered'], true)) {
                $transition = $newStatus;
            }
        } elseif ($participant->status === 'revision_needed' && $participant->wasChanged('revision_notes')) {
            $transition = 'revision_needed';
        }

        if ($transition === null) {
            return;
        }

        $snapshot = $this->emailService->createSnapshot($participant, $transition);

        DB::afterCommit(function () use ($snapshot): void {
            $this->emailService->sendNotification($snapshot);
        });
    }

    public function deleted(EventParticipant $participant): void
    {
        app(EventActivityService::class)->reconcileParent($participant, true);
        $snapshot = $this->emailService->createSnapshot($participant, 'deleted');

        DB::afterCommit(function () use ($snapshot): void {
            $this->emailService->sendNotification($snapshot);
        });
    }
}
