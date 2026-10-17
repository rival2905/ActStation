<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\MasterData\Models\SportCategory;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->seed(RolePermissionSeeder::class);

    $this->sport = SportCategory::firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->organizer = User::factory()->create([
        'is_active' => true,
        'kyc_status' => 'verified',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'Organizer Utama',
    ]);
    $this->organizer->assignRole('organizer');
});

test('organizer can create event with empty venue and no uploads', function (): void {
    $response = $this->actingAs($this->organizer)
        ->post(route('panel.organizer.events.store'), [
            'title' => 'Turnamen Terbuka Tanpa Venue Draf Awal',
            'sport_category_id' => $this->sport->id,
            'starts_at' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'ends_at' => Carbon::now()->addDays(15)->format('Y-m-d'),
            'venue_selection_present' => 1,
        ]);

    $response->assertSessionHasNoErrors();
    $event = Event::where('title', 'Turnamen Terbuka Tanpa Venue Draf Awal')->first();
    expect($event)->not->toBeNull()
        ->and($event->status)->toBe('draft')
        ->and($event->venue_id)->toBeNull();

    expect(DB::table('event_venues')->where('event_id', $event->id)->count())->toBe(0)
        ->and($event->eventCourts()->count())->toBe(0)
        ->and($event->documents()->count())->toBe(0);

    $response->assertRedirect(route('panel.organizer.events.manage', $event->id));
});

test('organizer can upload optional cabor recommendation and thb as pdf', function (): void {
    $recomFile = UploadedFile::fake()->create('rekomendasi_cabor.pdf', 500, 'application/pdf');
    $thbFile = UploadedFile::fake()->create('panduan_teknis.pdf', 1200, 'application/pdf');

    $response = $this->actingAs($this->organizer)
        ->post(route('panel.organizer.events.store'), [
            'title' => 'Turnamen Lengkap Berkas',
            'sport_category_id' => $this->sport->id,
            'starts_at' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'ends_at' => Carbon::now()->addDays(15)->format('Y-m-d'),
            'venue_selection_present' => 1,
            'recommendation_doc' => $recomFile,
            'technical_handbook_doc' => $thbFile,
        ]);

    $response->assertSessionHasNoErrors();
    $event = Event::where('title', 'Turnamen Lengkap Berkas')->first();
    expect($event)->not->toBeNull();

    $recomDoc = $event->documents()->where('category', 'federation_recommendation')->first();
    expect($recomDoc)->not->toBeNull()
        ->and($recomDoc->name)->toBe('Surat Rekomendasi Induk Cabor')
        ->and($recomDoc->is_public)->toBeFalse()
        ->and(Storage::disk('public')->exists($recomDoc->file_path))->toBeTrue();

    $thbDoc = $event->documents()->where('category', 'technical_handbook')->first();
    expect($thbDoc)->not->toBeNull()
        ->and($thbDoc->name)->toBe('Technical Handbook (THB) / Buku Panduan Teknis')
        ->and($thbDoc->is_public)->toBeTrue()
        ->and(Storage::disk('public')->exists($thbDoc->file_path))->toBeTrue();
});

test('organizer event create rejects non-pdf for recommendation and thb', function (): void {
    $jpgFile = UploadedFile::fake()->image('rekomendasi.jpg');

    $response = $this->actingAs($this->organizer)
        ->post(route('panel.organizer.events.store'), [
            'title' => 'Turnamen Berkas Non-PDF',
            'sport_category_id' => $this->sport->id,
            'starts_at' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'ends_at' => Carbon::now()->addDays(15)->format('Y-m-d'),
            'recommendation_doc' => $jpgFile,
        ]);

    $response->assertSessionHasErrors(['recommendation_doc']);
});

test('create event view contains recommendation and thb inputs and omits venue approval doc', function (): void {
    $response = $this->actingAs($this->organizer)
        ->get(route('panel.organizer.events.create'));

    $response->assertOk()
        ->assertSee('name="recommendation_doc"', false)
        ->assertSee('name="technical_handbook_doc"', false)
        ->assertSee('Surat Rekomendasi Induk Cabor')
        ->assertSee('Technical Handbook (THB) / Buku Panduan Teknis')
        ->assertDontSee('name="venue_approval_doc"', false)
        ->assertDontSee('Surat Izin Tempat / Bukti Sewa');
});
