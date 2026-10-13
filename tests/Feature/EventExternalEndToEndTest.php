<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Services\EventLifecycleService;
use App\Modules\Event\Services\EventPayoutService;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\MasterData\Models\Venue;
use App\Modules\Payment\Models\Transaction;
use App\Modules\Payment\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

test('skenario end-to-end lengkap turnamen basket eksternal dan escrow platform', function (): void {
    Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web', 'panel_access' => 'both']);
    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web', 'panel_access' => 'external']);

    $sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $devLevel = DevelopmentLevel::query()->firstOrCreate(
        ['name' => 'Remaja (13-16)', 'sport_category_id' => $sport->id],
        ['min_age' => 13, 'max_age' => 16, 'sort_order' => 2]
    );

    $venue = Venue::create([
        'name' => 'GOR Basket Tri Lomba Juang',
        'address' => 'Bandung',
        'is_active' => true,
    ]);

    $admin = User::factory()->create(['email' => 'admin-e2e@actstation.test']);
    $admin->assignRole('superadmin');

    // 1. Organizer daftar & lolos JIT KYC
    $organizer = User::factory()->create([
        'name' => 'Panitia Perbasi Cup',
        'email' => 'panitia@perbasicup.test',
        'bank_name' => 'BCA',
        'bank_account_number' => '5544332211',
        'bank_account_holder' => 'Panitia Perbasi Cup',
        'kyc_status' => 'verified',
    ]);
    $organizer->assignRole('organizer');

    // 2. Organizer membuat event draf & kategori KU-16
    $event = Event::create([
        'sport_category_id' => $sport->id,
        'venue_id' => $venue->id,
        'user_id' => $organizer->id,
        'title' => 'Kejurda Basket Remaja Jabar 2026',
        'slug' => 'kejurda-basket-remaja-jabar-2026',
        'status' => 'draft',
        'registration_open' => true,
        'starts_at' => Carbon::now()->addDays(5),
        'ends_at' => Carbon::now()->addDays(10),
    ]);

    $category = EventCategory::create([
        'event_id' => $event->id,
        'name' => 'KU-16 Putra',
        'slug' => 'ku-16-putra',
        'match_type' => '5x5',
        'gender' => 'male',
        'development_level_id' => $devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'max_teams' => 8,
        'registration_flow' => 'payment_first',
        'registration_fee' => 1000000.00,
        'registration_start' => now()->subDay(),
        'payment_start' => now()->subHour(),
        'registration_end' => now()->addDays(3),
        'payment_end' => now()->addDays(3),
        'enable_waiting_list' => true,
    ]);

    // 3. Organizer submit review -> Admin Approve (pending_approval -> published)
    $lifecycle = app(EventLifecycleService::class);
    $lifecycle->submitReview($event);
    expect($event->fresh()->status)->toBe('pending_approval');

    $lifecycle->approve($event);
    expect($event->fresh()->status)->toBe('published');

    // 4. Komunitas mendaftarkan tim dan atlet
    $manager = User::factory()->create(['name' => 'Manajer Klub Bintang']);
    $manager->assignRole('community_manager');

    $community = Community::create([
        'name' => 'Klub Bintang Basket',
        'slug' => 'klub-bintang-basket',
        'sport_category_id' => $sport->id,
        'subtype' => 'club',
        'status' => 'verified',
    ]);

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $manager->id,
        'position' => 'Manajer',
        'status' => 'active',
        'started_at' => now(),
    ]);

    $athleteIds = [];
    for ($i = 1; $i <= 5; $i++) {
        $ath = User::factory()->create([
            'name' => "Atlet {$i}",
            'gender' => 'male',
            'birth_date' => Carbon::now()->subYears(15)->format('Y-m-d'),
            'identity_number' => "327311112222000{$i}",
        ]);
        $ath->assignRole('athlete');

        CommunityAthlete::create([
            'community_id' => $community->id,
            'user_id' => $ath->id,
            'jersey_number' => $i,
            'status' => 'active',
            'region_status' => 'verified',
            'joined_at' => now(),
        ]);

        $athleteIds[] = $ath->id;
    }

    $members = [];
    foreach ($athleteIds as $idx => $aId) {
        $members[] = [
            'user_id' => $aId,
            'jersey_number' => $idx + 10,
            'is_captain' => $idx === 0 ? 1 : 0,
            'position' => 'Guard',
            'team_role' => 'player',
        ];
    }

    // Submit pendaftaran tim via HTTP panel
    $regResponse = test()->actingAs($manager)->post(route('panel.community-events.store', $event->slug), [
        'event_category_id' => $category->id,
        'team_name' => 'Bintang Muda KU-16',
        'contact_person' => 'Manajer Bintang',
        'contact_phone' => '081234567890',
        'members' => $members,
    ]);

    $participant = EventParticipant::where('team_name', 'Bintang Muda KU-16')->first();
    expect($participant)->not->toBeNull()
        ->and($participant->status)->toBe('pending_payment');

    // Bayar lewat invoice yang diterbitkan saat pendaftaran; verifikasi berkas tetap terpisah.
    $invoice = Transaction::where('payable_type', 'event_participant')->where('payable_id', $participant->id)->firstOrFail();
    expect($invoice->amount)->toBe('1000000.00');
    app(TransactionService::class)->markPaid($invoice, 'GW-REF-E2E-001');
    expect($participant->fresh()->status)->not->toBe('confirmed');

    // 6. Hari H: Pertandingan bergulir (status: ongoing, game: live)
    $event->update([
        'status' => 'ongoing',
        'starts_at' => Carbon::now()->subDay(),
    ]);

    Game::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'game_status' => 'live',
    ]);

    // 7. Organizer mengajukan Payout Termin 1 (40%)
    $payoutService = app(EventPayoutService::class);
    expect($payoutService->canRequestTerm1($event))->toBeTrue();

    $payout1 = $payoutService->requestPayout($event, $organizer, 1, [
        'bank_name' => 'BCA',
        'bank_account_number' => '5544332211',
        'bank_account_holder' => 'Panitia Perbasi Cup',
    ]);

    expect((float) $payout1->amount)->toBe(400000.00)
        ->and($event->fresh()->payout_status)->toBe('term1_requested');

    // Admin konfirmasi transfer Termin 1
    $payoutService->processTransfer($payout1, $admin, 'proof1.jpg');
    expect($event->fresh()->payout_status)->toBe('term1_paid');

    // 8. Turnamen selesai (completed) dan lewat masa sanggah H+2 (48 jam)
    $event->update([
        'status' => 'completed',
        'ends_at' => Carbon::now()->subHours(50),
    ]);

    expect($payoutService->canRequestTerm2($event))->toBeTrue();

    // 9. Organizer mengajukan Payout Termin 2 (60% dikurangi 5% Platform Fee)
    $payout2 = $payoutService->requestPayout($event, $organizer, 2, [
        'bank_name' => 'BCA',
        'bank_account_number' => '5544332211',
        'bank_account_holder' => 'Panitia Perbasi Cup',
    ]);

    expect((float) $payout2->amount)->toBe(600000.00)
        ->and((float) $payout2->platform_fee)->toBe(50000.00) // 5% dari 1jt
        ->and((float) $payout2->net_amount)->toBe(550000.00)
        ->and($event->fresh()->payout_status)->toBe('term2_requested');

    // Admin konfirmasi transfer pelunasan Termin 2
    $payoutService->processTransfer($payout2, $admin, 'proof2.jpg');
    expect($event->fresh()->payout_status)->toBe('settled');
});
