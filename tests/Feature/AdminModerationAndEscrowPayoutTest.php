<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventPayout;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\MasterData\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web', 'panel_access' => 'both']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'panel_access' => 'internal']);

    Permission::firstOrCreate(['name' => 'event.publish', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'transaction.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'refund.process', 'guard_name' => 'web']);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->venue = Venue::create([
        'name' => 'GOR Bulungan',
        'address' => 'Jakarta Selatan',
        'is_active' => true,
    ]);

    $this->admin = User::factory()->create([
        'email' => 'admin@actstation.test',
        'is_active' => true,
    ]);
    $this->admin->assignRole('superadmin');

    $this->organizer = User::factory()->create([
        'name' => 'Bambang EO',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'Bambang EO',
        'kyc_status' => 'verified',
    ]);
});

test('admin dapat meninjau antrean moderasi dan menyetujui publikasi event', function (): void {
    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->organizer->id,
        'venue_id' => $this->venue->id,
        'status' => 'pending_approval',
        'starts_at' => Carbon::now()->addDays(5),
        'ends_at' => Carbon::now()->addDays(10),
    ]);
    EventCategory::factory()->create(['event_id' => $event->id]);

    // View queue
    $this->actingAs($this->admin)
        ->get(route('admin.events.moderation.index'))
        ->assertOk()
        ->assertSee($event->title);

    // Approve
    $this->actingAs($this->admin)
        ->post(route('admin.events.moderation.approve', $event->id))
        ->assertRedirect();

    expect($event->fresh()->status)->toBe('published');
});

test('admin dapat menolak draf event dengan catatan perbaikan kyc', function (): void {
    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->organizer->id,
        'venue_id' => $this->venue->id,
        'status' => 'pending_approval',
        'starts_at' => Carbon::now()->addDays(5),
        'ends_at' => Carbon::now()->addDays(10),
    ]);
    EventCategory::factory()->create(['event_id' => $event->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.events.moderation.reject', $event->id), [
            'notes' => 'Surat izin venue tidak terbaca stempel pengelolanya.',
        ])
        ->assertRedirect();

    $rejected = $event->fresh();
    expect($rejected->status)->toBe('draft')
        ->and($rejected->kyc_notes)->toBe('Surat izin venue tidak terbaca stempel pengelolanya.');
});

test('admin dapat mengaudit permohonan payout dan mengonfirmasi transfer dana termin', function (): void {
    Storage::fake('public');

    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->organizer->id,
        'status' => 'ongoing',
        'payout_status' => 'term1_requested',
    ]);

    $payout = EventPayout::create([
        'event_id' => $event->id,
        'term' => 1,
        'amount' => 4000000.00,
        'platform_fee' => 0.00,
        'net_amount' => 4000000.00,
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'Bambang EO',
        'status' => 'requested',
        'requested_by' => $this->organizer->id,
    ]);

    // View payout queue
    $this->actingAs($this->admin)
        ->get(route('admin.escrow-payouts.index'))
        ->assertOk()
        ->assertSee('Termin 1');

    $proof = UploadedFile::fake()->image('transfer_receipt.jpg');

    // Confirm transfer
    $this->actingAs($this->admin)
        ->post(route('admin.escrow-payouts.transfer', $payout->id), [
            'transfer_proof' => $proof,
            'notes' => 'Ref no: TRX-BCA-987654',
        ])
        ->assertRedirect();

    $updated = $payout->fresh();
    expect($updated->status)->toBe('transferred')
        ->and($updated->approved_by)->toBe($this->admin->id)
        ->and($updated->transfer_proof)->not->toBeNull()
        ->and($event->fresh()->payout_status)->toBe('term1_paid');
});
