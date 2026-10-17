<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Services\EventVenueService;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\MasterData\Models\Venue;
use App\Modules\MasterData\Models\VenueCourt;
use App\Modules\Payment\Models\Transaction;
use App\Modules\Ticketing\Models\Ticket;
use App\Modules\Ticketing\Models\TicketCategory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Role::query()->firstOrCreate(['name' => 'organizer', 'guard_name' => 'web'], ['panel_access' => 'external']);
    Role::query()->firstOrCreate(['name' => 'member', 'guard_name' => 'web'], ['panel_access' => 'external']);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->venue = Venue::create([
        'name' => 'GOR C-Tra Arena Bandung',
        'address' => 'Jl. Cikutra No. 201, Bandung',
        'is_active' => true,
    ]);

    $this->user = User::factory()->create([
        'name' => 'Doni Organizer',
        'email' => 'doni@eo.com',
        'is_active' => true,
    ]);
    $this->user->assignRole('member');

    DB::table('wilayah')->insertOrIgnore([
        ['kode' => '32', 'nama' => 'JAWA BARAT'],
        ['kode' => '32.73', 'nama' => 'KOTA BANDUNG'],
        ['kode' => '32.73.01', 'nama' => 'SUKASARI'],
        ['kode' => '32.73.01.1001', 'nama' => 'SARIJADI'],
    ]);
});

test('Just-In-Time KYC menyimpan pengajuan pending lalu disetujui admin menjadi organizer', function (): void {
    Storage::fake('local');

    $admin = User::factory()->create(['is_active' => true]);
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'panel_access' => 'internal']);
    $permUpdate = Permission::firstOrCreate(['name' => 'user.update', 'guard_name' => 'web']);
    $adminRole->givePermissionTo($permUpdate);
    $admin->assignRole('admin');

    $response = $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), [
            'organizer_mode' => 'individual',
            'identity_number' => '3273998877665544',
            'identity_name' => 'Doni Organizer',
            'birth_place' => 'Bandung',
            'birth_date' => '1990-01-01',
            'identity_occupation' => 'Wiraswasta',
            'identity_address' => 'Jl. Cikutra No. 201, Bandung',
            'bank_name' => 'BCA',
            'bank_account_number' => '8887776665',
            'bank_account_holder' => 'Doni Organizer',
            'bank_branch' => 'KCP Cikutra',
            'province_id' => '32',
            'city_id' => '32.73',
            'subdistrict_id' => '32.73.01',
            'village_id' => '32.73.01.1001',
            'postal_code' => '40151',
            'address' => 'Jl. Cikutra No. 201, Bandung',
            'rt' => '01',
            'rw' => '02',
            'emergency_contact' => '081299887766',
            'identity_image' => UploadedFile::fake()->image('ktp.jpg'),
            'identity_selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'responsibility_statement' => UploadedFile::fake()->create('sptjm.pdf', 500, 'application/pdf'),
            'bank_proof' => UploadedFile::fake()->create('buku_tabungan.pdf', 500, 'application/pdf'),
        ]);

    $response->assertRedirect(route('panel.organizer.apply'));

    $pending = $this->user->fresh();
    expect($pending->kyc_status)->toBe('pending')
        ->and($pending->hasRole('organizer'))->toBeFalse();

    // Admin approves
    $this->actingAs($admin)
        ->post(route('admin.organizer-kyc.approve', $this->user));

    $updated = $this->user->fresh();
    expect($updated->hasRole('organizer'))->toBeTrue()
        ->and($updated->kyc_status)->toBe('verified')
        ->and($updated->bank_name)->toBe('BCA');
});

test('organizer dapat membuat event draf dan mengajukan review moderasi', function (): void {
    $this->user->update([
        'kyc_status' => 'verified',
        'bank_name' => 'BCA',
        'bank_account_number' => '8887776665',
    ]);
    $this->user->assignRole('organizer');

    // Create event
    $response = $this->actingAs($this->user)
        ->post(route('panel.organizer.events.store'), [
            'title' => 'Piala Walikota Bandung 2026',
            'description' => 'Turnamen resmi basket',
            'venue_id' => $this->venue->id,
            'starts_at' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'ends_at' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'allow_manual_payment' => 1,
            'manual_bank_name' => 'BCA',
            'manual_bank_account_number' => '8887776665',
            'manual_bank_account_holder' => 'Doni Organizer',
        ]);
    $event = Event::where('title', 'Piala Walikota Bandung 2026')->first();
    expect($event)->not->toBeNull()
        ->and($event->status)->toBe('draft')
        ->and($event->allow_manual_payment)->toBeTrue();

    // Tambah kategori agar bisa submit review
    EventCategory::factory()->create(['event_id' => $event->id]);

    // Submit review
    $reviewResp = $this->actingAs($this->user)
        ->post(route('panel.organizer.events.submitReview', $event->id));

    expect($event->fresh()->status)->toBe('pending_approval');
    $reviewResp->assertRedirect(route('panel.organizer.events.manage', $event->id));
});

test('organizer menolak konfirmasi pembayaran manual tanpa bukti dan verifikasi berkas', function (): void {
    if (! $this->user->hasRole('organizer')) {
        $this->user->assignRole('organizer');
    }
    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->user->id,
        'status' => 'published',
    ]);
    $category = EventCategory::factory()->create(['event_id' => $event->id]);

    $participant = EventParticipant::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'user_id' => $this->user->id,
        'team_name' => 'Garuda Bandung',
        'contact_person' => 'Budi',
        'contact_phone' => '08123456789',
        'payment_channel' => 'manual_organizer',
        'status' => 'pending_payment',
    ]);

    // 1. Minta revisi
    $this->actingAs($this->user)
        ->post(route('panel.organizer.teams.requestRevision', [$event->id, $participant->id]), [
            'revision_notes' => 'Pasfoto pemain buram',
        ]);

    expect($participant->fresh()->status)->toBe('revision_needed')
        ->and($participant->fresh()->revision_notes)->toBe('Pasfoto pemain buram');

    // Permintaan verifikasi manual tidak boleh menyingkirkan status revisi tanpa bukti bayar.
    $this->actingAs($this->user)
        ->post(route('panel.organizer.teams.confirmManualPayment', [$event->id, $participant->id]));

    expect($participant->fresh()->status)->toBe('revision_needed')
        ->and($participant->fresh()->payment_verified_by)->toBeNull();
});

test('gate scanner memvalidasi tiket masuk penonton secara akurat', function (): void {
    if (! $this->user->hasRole('organizer')) {
        $this->user->assignRole('organizer');
    }
    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->user->id,
        'status' => 'published',
    ]);

    $tCat = TicketCategory::create([
        'event_id' => $event->id,
        'name' => 'VIP Courtside',
        'price' => 100000,
        'quota' => 50,
        'sold_count' => 1,
        'seating_zone' => 'Courtside Row A',
    ]);

    $ticket = Ticket::create([
        'ticket_category_id' => $tCat->id,
        'user_id' => $this->user->id,
        'ticket_code' => 'TKT-VIP-999',
        'qr_code' => 'QR-TOKEN-999',
        'holder_name' => 'Sultan Penonton',
        'seat_number' => 'Courtside Row A',
        'status' => 'active',
    ]);

    // 1. Scan pertama -> Valid & status berganti used
    $res1 = $this->actingAs($this->user)
        ->postJson(route('panel.organizer.gate-scanner.verify', $event->id), [
            'token' => 'TKT-VIP-999',
        ]);

    $res1->assertOk()
        ->assertJson([
            'success' => true,
            'status' => 'valid',
            'holder_name' => 'Sultan Penonton',
        ]);

    expect($ticket->fresh()->status)->toBe('used');

    // 2. Scan kedua (curang / duplikat) -> Ditolak already_used
    $res2 = $this->actingAs($this->user)
        ->postJson(route('panel.organizer.gate-scanner.verify', $event->id), [
            'token' => 'TKT-VIP-999',
        ]);

    $res2->assertStatus(422)
        ->assertJson([
            'success' => false,
            'status' => 'already_used',
        ]);
});

test('organizer dapat menghapus tim yang belum bayar dan invoice pending ikut dibatalkan', function (): void {
    if (! $this->user->hasRole('organizer')) {
        $this->user->assignRole('organizer');
    }
    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->user->id,
        'status' => 'published',
    ]);
    $category = EventCategory::factory()->create(['event_id' => $event->id]);

    $participant = EventParticipant::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'user_id' => $this->user->id,
        'team_name' => 'Tim Belum Bayar',
        'contact_person' => 'Doni',
        'contact_phone' => '08123456789',
        'status' => 'pending_payment',
    ]);

    Transaction::create([
        'user_id' => $this->user->id,
        'invoice_number' => 'INV-DEL-TEST-001',
        'type' => 'registration',
        'payable_type' => 'event_participant',
        'payable_id' => $participant->id,
        'amount' => 500000,
        'fee' => 0,
        'total' => 500000,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('panel.organizer.teams.destroy', [$event->id, $participant->id]));

    $response->assertSessionHas('success');
    expect(EventParticipant::where('id', $participant->id)->exists())->toBeFalse()
        ->and(Transaction::where('payable_type', 'event_participant')->where('payable_id', $participant->id)->exists())->toBeFalse();
});

test('organizer dilarang menghapus tim yang sudah bayar lunas', function (): void {
    if (! $this->user->hasRole('organizer')) {
        $this->user->assignRole('organizer');
    }
    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->user->id,
        'status' => 'published',
    ]);
    $category = EventCategory::factory()->create(['event_id' => $event->id]);

    $participant = EventParticipant::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'user_id' => $this->user->id,
        'team_name' => 'Tim Sudah Lunas',
        'contact_person' => 'Doni',
        'contact_phone' => '08123456789',
        'status' => 'confirmed',
    ]);

    Transaction::create([
        'user_id' => $this->user->id,
        'invoice_number' => 'INV-PAID-TEST-002',
        'type' => 'registration',
        'payable_type' => 'event_participant',
        'payable_id' => $participant->id,
        'amount' => 500000,
        'fee' => 0,
        'total' => 500000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('panel.organizer.teams.destroy', [$event->id, $participant->id]));

    $response->assertSessionHas('error');
    expect(EventParticipant::where('id', $participant->id)->exists())->toBeTrue();
});

test('organizer dapat membuat tiket per-game yang mengikat langsung ke jadwal pertandingan scheduling', function (): void {
    if (! $this->user->hasRole('organizer')) {
        $this->user->assignRole('organizer');
    }
    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->user->id,
        'status' => 'published',
    ]);

    $category = EventCategory::factory()->create(['event_id' => $event->id]);

    $game = Game::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'team_a_label' => 'SMA 1 Bintang',
        'team_b_label' => 'SMA 2 Harapan',
        'game_time' => now()->addDays(2)->setTime(14, 0),
        'game_status' => 'scheduled',
        'match_label' => 'Match 1',
        'round_label' => 'Semifinal',
    ]);

    $venue = Venue::create(['name' => 'DEMO Ticket Venue', 'is_active' => true]);
    $court = VenueCourt::create(['venue_id' => $venue->id, 'name' => 'DEMO Ticket Court', 'is_active' => true]);
    app(EventVenueService::class)->sync($event, [$venue->id], [$court->id]);
    $game->update(['venue_id' => $venue->id, 'venue_court_id' => $court->id]);

    // 1. Simpan kategori tiket per-game terikat ke game_id
    $res = $this->actingAs($this->user)->post(route('panel.organizer.ticketing.store', $event->id), [
        'name' => 'Tiket Suporter Semifinal SMA 1 vs SMA 2',
        'price' => 25000,
        'quota' => 100,
        'access_type' => 'per_game',
        'game_id' => $game->id,
        'seating_tier' => 'standard',
        'seating_zone' => 'Tribun Barat',
    ]);

    $res->assertRedirect();

    $tCat = TicketCategory::where('name', 'Tiket Suporter Semifinal SMA 1 vs SMA 2')->first();
    expect($tCat)->not->toBeNull()
        ->and((int) $tCat->game_id)->toBe($game->id)
        ->and($tCat->valid_date?->toDateString())->toBe($game->game_time->toDateString());

    // 2. Cek halaman scheduling menampilkan tiket yang terikat
    $schedRes = $this->actingAs($this->user)->get(route('panel.organizer.scheduling.index', $event->id));
    $schedRes->assertOk();
    $schedRes->assertSee('Tiket Suporter Semifinal SMA 1 vs SMA 2');

    $event->update(['ticket_sales_starts_at' => now()->subMinute()]);

    // 3. Cek halaman publik tiket menampilkan info match akurat
    $pubRes = $this->get(route('events.tickets', $event->slug));
    $pubRes->assertOk();
    $pubRes->assertSee('SMA 1 Bintang vs SMA 2 Harapan');
    $pubRes->assertSee('Semifinal');
});
