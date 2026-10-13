<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Events\PaymentRefunded;
use App\Contracts\Events\PaymentSucceeded;
use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Services\EventLifecycleService;
use App\Modules\Event\Services\EventPayoutService;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\Payment\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->sport = SportCategory::firstOrCreate(
        ['id' => 1],
        ['name' => 'Basketball', 'slug' => 'basketball']
    );

    $this->organizer = User::factory()->create([
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'John Doe',
        'kyc_status' => 'verified',
    ]);
});

test('lifecycle submitReview, approve, dan reject transisi berjalan sesuai kontrak', function (): void {
    $lifecycle = app(EventLifecycleService::class);

    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->organizer->id,
        'status' => 'draft',
        'starts_at' => Carbon::now()->addDays(2),
        'ends_at' => Carbon::now()->addDays(5),
    ]);
    EventCategory::factory()->create(['event_id' => $event->id]);

    // 1. Submit review -> pending_approval
    $lifecycle->submitReview($event);
    expect($event->fresh()->status)->toBe('pending_approval');

    // 2. Reject review -> draft dengan catatan kyc
    $lifecycle->reject($event, 'Bukti venue tidak terbaca');
    $rejected = $event->fresh();
    expect($rejected->status)->toBe('draft')
        ->and($rejected->kyc_notes)->toBe('Bukti venue tidak terbaca');

    // 3. Resubmit & approve -> published
    $lifecycle->submitReview($event);
    $lifecycle->approve($event);
    expect($event->fresh()->status)->toBe('published');
});

test('event participant mengimplementasikan Payable contract', function (): void {
    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->organizer->id,
    ]);
    $cat = EventCategory::factory()->create(['event_id' => $event->id, 'name' => 'KU-16 Putra', 'registration_fee' => '1500000.00']);
    $participant = EventParticipant::factory()->create([
        'event_id' => $event->id,
        'event_category_id' => $cat->id,
        'team_name' => 'Garuda Muda',
    ]);

    expect($participant->getPayableType())->toBe('event_participant')
        ->and($participant->getPayableId())->toBe($participant->id)
        ->and($participant->getAmount())->toBe('1500000.00')
        ->and($participant->getDescription())->toContain('Garuda Muda');
});

test('notifikasi pembayaran tanpa invoice tidak mengubah status pendaftaran', function (): void {
    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->organizer->id,
    ]);
    $participant = EventParticipant::factory()->create([
        'event_id' => $event->id,
        'status' => 'registered',
    ]);

    // PaymentSucceeded
    event(new PaymentSucceeded(
        transactionId: 999,
        payableType: 'event_participant',
        payableId: $participant->id,
        amount: '1500000.00'
    ));

    expect($participant->fresh()->status)->toBe('registered');

    // PaymentRefunded
    event(new PaymentRefunded(
        transactionId: 999,
        refundId: 888,
        payableType: 'event_participant',
        payableId: $participant->id,
        amount: '1500000.00'
    ));

    expect($participant->fresh()->status)->toBe('registered');
});

test('EventPayoutService menghitung termin dan memvalidasi syarat pencairan dana', function (): void {
    $payoutService = app(EventPayoutService::class);

    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->organizer->id,
        'status' => 'ongoing',
        'starts_at' => Carbon::now()->subDay(),
        'ends_at' => Carbon::now()->addDays(2),
    ]);
    $category = EventCategory::factory()->create(['event_id' => $event->id]);
    $participant = EventParticipant::factory()->create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'status' => 'confirmed',
    ]);

    // Simulasi transaksi paid masuk escrow
    Transaction::create([
        'user_id' => $this->organizer->id,
        'invoice_number' => 'INV-TEST-001',
        'type' => 'registration',
        'payable_type' => 'event_participant',
        'payable_id' => $participant->id,
        'amount' => 10000000.00,
        'fee' => 0.00,
        'total' => 10000000.00,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $summary = $payoutService->getEscrowSummary($event);
    expect($summary['total_escrow'])->toBe(10000000.0)
        ->and($summary['term1_max'])->toBe(4000000.0)
        ->and($summary['term2_max'])->toBe(6000000.0);
    // Belum ada game dimulai -> canRequestTerm1 false
    expect($payoutService->canRequestTerm1($event))->toBeFalse();

    // Buat 1 game in_progress
    Game::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'game_status' => 'live',
    ]);

    expect($payoutService->canRequestTerm1($event))->toBeTrue();

    // Request Termin 1 (40%)
    $payout = $payoutService->requestPayout($event, $this->organizer, 1, [
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'John Doe',
    ]);

    expect($payout->status)->toBe('requested')
        ->and((float) $payout->amount)->toBe(4000000.0)
        ->and($event->fresh()->payout_status)->toBe('term1_requested');

    // Admin transfer Termin 1
    $admin = User::factory()->create();
    $transferred = $payoutService->processTransfer($payout, $admin, 'proof.jpg');

    expect($transferred->status)->toBe('transferred')
        ->and($event->fresh()->payout_status)->toBe('term1_paid');
});
