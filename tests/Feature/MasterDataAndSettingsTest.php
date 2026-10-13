<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\School;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\MasterData\Models\Venue;
use App\Modules\MasterData\Models\VenueCourt;
use App\Modules\MasterData\Models\Wilayah;
use App\Modules\MasterData\Models\WilayahKodepos;
use App\Modules\MasterData\Services\SchoolImportService;
use App\Modules\Settings\Models\Setting;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->superadmin = User::where('email', 'superadmin@actstation.test')->first();

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['is_active' => true]);
    $this->staff->assignRole('staff');
});

test('gating permission: staff forbidden on settings and create sport-category', function (): void {
    // Staff accessing settings index (general) -> 403
    $this->actingAs($this->staff)
        ->get(route('admin.settings.general'))
        ->assertForbidden();

    // Staff attempting to create sport category -> 403
    $this->actingAs($this->staff)
        ->post(route('admin.sport-categories.store'), [
            'name' => 'Renang',
            'slug' => 'renang',
            'is_active' => true,
        ])
        ->assertForbidden();

    // Admin can view settings general
    $this->actingAs($this->admin)
        ->get(route('admin.settings.general'))
        ->assertOk();

    // Admin forbidden on payment settings and updating settings
    $this->actingAs($this->admin)
        ->get(route('admin.settings.payment'))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->put(route('admin.settings.general.update'), [
            'app_name' => 'Hacked App',
        ])
        ->assertForbidden();

    // Superadmin can access and update
    $this->actingAs($this->superadmin)
        ->get(route('admin.settings.payment'))
        ->assertOk();

    $this->actingAs($this->superadmin)
        ->post(route('admin.sport-categories.store'), [
            'name' => 'Bola Basket',
            'slug' => 'bola-basket',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.sport-categories.index'));
});

test('unique slug on sport-categories is enforced', function (): void {
    SportCategory::create([
        'name' => 'Sepak Bola',
        'slug' => 'sepak-bola',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->superadmin)
        ->post(route('admin.sport-categories.store'), [
            'name' => 'Sepak Bola Baru',
            'slug' => 'sepak-bola',
            'is_active' => true,
        ]);

    $response->assertSessionHasErrors('slug');
});

test('development level age overlap is rejected within the same sport category', function (): void {
    $cabor = SportCategory::create([
        'name' => 'Basket',
        'slug' => 'basket',
        'is_active' => true,
    ]);

    // Create existing KU-12 (min: 10, max: 12)
    DevelopmentLevel::create([
        'sport_category_id' => $cabor->id,
        'name' => 'KU-12',
        'min_age' => 10,
        'max_age' => 12,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Overlapping age: 12 - 14 overlaps at 12
    $response = $this->actingAs($this->superadmin)
        ->post(route('admin.development-levels.store'), [
            'sport_category_id' => $cabor->id,
            'name' => 'KU-14 Overlap',
            'min_age' => 12,
            'max_age' => 14,
            'sort_order' => 2,
            'is_active' => true,
        ]);

    $response->assertSessionHasErrors('min_age');

    // Non-overlapping age: 13 - 15 is accepted
    $validResponse = $this->actingAs($this->superadmin)
        ->post(route('admin.development-levels.store'), [
            'sport_category_id' => $cabor->id,
            'name' => 'KU-14 Valid',
            'min_age' => 13,
            'max_age' => 15,
            'sort_order' => 2,
            'is_active' => true,
        ]);

    $validResponse->assertRedirect(route('admin.development-levels.index'));
});

test('DevelopmentLevel::forBirthDate matches exactly on min and max age boundaries', function (): void {
    $cabor = SportCategory::create([
        'name' => 'Basket',
        'slug' => 'basket',
        'is_active' => true,
    ]);

    $level = DevelopmentLevel::create([
        'sport_category_id' => $cabor->id,
        'name' => 'KU-12',
        'min_age' => 10,
        'max_age' => 12,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Exact min_age (10 years old)
    $birthDateMin = Carbon::now()->subYears(10);
    $foundMin = DevelopmentLevel::forBirthDate($birthDateMin, $cabor->id);
    expect($foundMin)->not->toBeNull()
        ->and($foundMin->id)->toBe($level->id);

    // Exact max_age (12 years old)
    $birthDateMax = Carbon::now()->subYears(12);
    $foundMax = DevelopmentLevel::forBirthDate($birthDateMax, $cabor->id);
    expect($foundMax)->not->toBeNull()
        ->and($foundMax->id)->toBe($level->id);

    // Below min_age (9 years old) -> null
    $birthDateTooYoung = Carbon::now()->subYears(9);
    expect(DevelopmentLevel::forBirthDate($birthDateTooYoung, $cabor->id))->toBeNull();

    // Above max_age (13 years old) -> null
    $birthDateTooOld = Carbon::now()->subYears(13);
    expect(DevelopmentLevel::forBirthDate($birthDateTooOld, $cabor->id))->toBeNull();
});

test('school import is idempotent: running twice yields identical count and updates existing data', function (): void {
    $csvContent = implode("\n", [
        'npsn,nama_sekolah,school_level,status_sekolah,kode_kabupaten_kota,kode_kecamatan,alamat,tahun_dataset,source',
        '20200001,SMA Negeri 1 Bandung,sma,negeri,32.73,32.73.01,Jl. Ir H Djuanda,2024,kemendikdasmen',
        '20200002,SMP Negeri 2 Bandung,smp,negeri,32.73,32.73.01,Jl. Supratman,2024,kemendikdasmen',
    ]);

    $tempFile = tempnam(sys_get_temp_dir(), 'schools_').'.csv';
    file_put_contents($tempFile, $csvContent);

    $service = app(SchoolImportService::class);

    // Run 1
    $result1 = $service->import($tempFile);
    expect($result1['processed'])->toBe(2)
        ->and(School::count())->toBe(2);

    $school1 = School::where('npsn', '20200001')->first();
    expect($school1->name)->toBe('SMA Negeri 1 Bandung');

    // Update name in CSV
    $updatedCsv = implode("\n", [
        'npsn,nama_sekolah,school_level,status_sekolah,kode_kabupaten_kota,kode_kecamatan,alamat,tahun_dataset,source',
        '20200001,SMA Negeri 1 Bandung Updated,sma,negeri,32.73,32.73.01,Jl. Ir H Djuanda Baru,2024,kemendikdasmen',
        '20200002,SMP Negeri 2 Bandung,smp,negeri,32.73,32.73.01,Jl. Supratman,2024,kemendikdasmen',
    ]);
    file_put_contents($tempFile, $updatedCsv);

    // Run 2
    $result2 = $service->import($tempFile);
    expect($result2['processed'])->toBe(2)
        ->and(School::count())->toBe(2);

    $school1Reloaded = School::where('npsn', '20200001')->first();
    expect($school1Reloaded->name)->toBe('SMA Negeri 1 Bandung Updated')
        ->and($school1Reloaded->address)->toBe('Jl. Ir H Djuanda Baru');

    @unlink($tempFile);
});

test('settings: secret stored encrypted in database, submit mask retains old secret, and activity log is redacted', function (): void {
    // 1. Initial setup: superadmin sets payment settings with secret
    $this->actingAs($this->superadmin)
        ->put(route('admin.settings.payment.update'), [
            'payment_gateway_default' => 'midtrans',
            'midtrans_server_key' => 'SB-Mid-server-REAL-SECRET-123',
            'midtrans_client_key' => 'SB-Mid-client-SECRET-ABC',
            'midtrans_is_production' => false,
            'xendit_secret_key' => 'xnd_development_SECRET_XYZ',
            'xendit_public_key' => 'xnd_public_ABC',
        ])
        ->assertRedirect(route('admin.settings.payment'));

    // 2. Secret is stored encrypted in database
    $settingRecord = Setting::where('key', 'midtrans_server_key')->first();
    expect($settingRecord)->not->toBeNull()
        ->and($settingRecord->value)->not->toBe('SB-Mid-server-REAL-SECRET-123')
        ->and(Crypt::decryptString($settingRecord->value))->toBe('SB-Mid-server-REAL-SECRET-123');

    // 3. Submitting mask placeholder does not overwrite secret
    $this->actingAs($this->superadmin)
        ->put(route('admin.settings.payment.update'), [
            'payment_gateway_default' => 'midtrans',
            'midtrans_server_key' => '••••••••',
            'midtrans_client_key' => '••••••••',
            'midtrans_is_production' => true,
            'xendit_secret_key' => '••••••••',
            'xendit_public_key' => 'xnd_public_NEW',
        ])
        ->assertRedirect(route('admin.settings.payment'));

    $settingRecordReloaded = Setting::where('key', 'midtrans_server_key')->first();
    expect(Crypt::decryptString($settingRecordReloaded->value))->toBe('SB-Mid-server-REAL-SECRET-123');

    // 4. Activity log for payment setting is redacted
    $log = ActivityLog::where('auditable_type', Setting::class)
        ->where('auditable_id', $settingRecord->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values['value'])->toBe(ActivityLog::REDACTED);
});

test('cascade wilayah endpoints return correct child hierarchy based on code prefix', function (): void {
    // Seed test wilayah data
    Wilayah::insert([
        ['kode' => '32', 'nama' => 'Jawa Barat'],
        ['kode' => '31', 'nama' => 'DKI Jakarta'],
        ['kode' => '32.73', 'nama' => 'Kota Bandung'],
        ['kode' => '32.74', 'nama' => 'Kota Cirebon'],
        ['kode' => '31.71', 'nama' => 'Kota Jakarta Selatan'],
        ['kode' => '32.73.01', 'nama' => 'Sukasari'],
        ['kode' => '32.73.02', 'nama' => 'Coblong'],
        ['kode' => '32.73.01.2001', 'nama' => 'Sukarasa'],
    ]);

    WilayahKodepos::insert([
        ['kode' => '32.73.01.2001', 'kodepos' => '40152'],
    ]);

    // Provinces endpoint returns length 2
    $this->actingAs($this->superadmin)
        ->getJson(route('admin.regions.provinces'))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonFragment(['kode' => '32', 'nama' => 'Jawa Barat']);

    // Cities endpoint filtered by parent_id 32
    $cityResponse = $this->actingAs($this->superadmin)
        ->getJson(route('admin.regions.cities', ['parent_id' => '32']))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonFragment(['kode' => '32.73', 'nama' => 'Kota Bandung'])

        ->assertJsonMissing(['kode' => '31.71']);

    // Districts endpoint filtered by parent_id 32.73
    $this->actingAs($this->superadmin)
        ->getJson(route('admin.regions.districts', ['parent_id' => '32.73']))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonFragment(['kode' => '32.73.01', 'nama' => 'Sukasari']);

    // Villages endpoint filtered by parent_id 32.73.01
    $this->actingAs($this->superadmin)
        ->getJson(route('admin.regions.villages', ['parent_id' => '32.73.01']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['kode' => '32.73.01.2001', 'nama' => 'Sukarasa']);

    // Postal code endpoint for village
    $this->actingAs($this->superadmin)
        ->getJson(route('admin.regions.postal-code', ['village_id' => '32.73.01.2001']))
        ->assertOk()
        ->assertExactJson(['kodepos' => '40152']);
});

test('command import-wilayah and import-schools fail clearly when source file is missing', function (): void {
    $this->artisan('master-data:import-schools', ['--file' => 'non_existent_file.csv'])
        ->assertFailed()
        ->expectsOutputToContain('tidak ditemukan');
});

test('venue and courts: court name must be unique per venue, allow same name across different venues', function (): void {
    $venue1 = Venue::create([
        'name' => 'GOR Pajajaran',
        'is_active' => true,
    ]);

    $venue2 = Venue::create([
        'name' => 'GOR C-Tra Arena',
        'is_active' => true,
    ]);

    // Court on venue 1
    $this->actingAs($this->superadmin)
        ->post(route('admin.venues.courts.store', $venue1), [
            'name' => 'Lapangan Utama',
            'surface' => 'Kayu Parquet',
            'is_indoor' => true,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.venues.show', $venue1));

    // Duplicate court name on same venue 1 -> fails
    $this->actingAs($this->superadmin)
        ->post(route('admin.venues.courts.store', $venue1), [
            'name' => 'Lapangan Utama',
            'is_indoor' => true,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('name');

    // Same court name on venue 2 -> succeeds
    $this->actingAs($this->superadmin)
        ->post(route('admin.venues.courts.store', $venue2), [
            'name' => 'Lapangan Utama',
            'is_indoor' => true,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.venues.show', $venue2));
});

test('settings: payment secret value update is redacted in activity log', function (): void {
    $setting = Setting::create([
        'group' => 'payment',
        'key' => 'midtrans_server_key',
        'value' => Crypt::encryptString('OLD-SECRET-KEY'),
        'type' => 'string',
    ]);

    // Update value only (group does not change)
    $setting->update([
        'value' => Crypt::encryptString('NEW-SECRET-KEY'),
    ]);

    $log = ActivityLog::where('auditable_type', Setting::class)
        ->where('auditable_id', $setting->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values['value'])->toBe(ActivityLog::REDACTED)
        ->and($log->new_values['value'])->toBe(ActivityLog::REDACTED);
});

test('user with setting.update without setting.payment gets 403 on PUT settings/payment', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('setting.update');

    $this->actingAs($user)
        ->put(route('admin.settings.payment.update'), [
            'payment_gateway_default' => 'midtrans',
            'midtrans_server_key' => 'SOME-KEY',
        ])
        ->assertForbidden();
});

test('IDOR protection: court of venue A cannot be updated or deleted via venue B URL', function (): void {
    $venueA = Venue::create(['name' => 'Venue Alpha', 'is_active' => true]);
    $venueB = Venue::create(['name' => 'Venue Beta', 'is_active' => true]);

    $courtA = VenueCourt::create([
        'venue_id' => $venueA->id,
        'name' => 'Court Alpha 1',
        'is_indoor' => true,
        'is_active' => true,
    ]);

    // Update courtA through venueB route -> 404
    $this->actingAs($this->superadmin)
        ->put(route('admin.venues.courts.update', ['venue' => $venueB, 'court' => $courtA]), [
            'name' => 'Hacked Court Name',
            'is_indoor' => true,
            'is_active' => true,
        ])
        ->assertNotFound();

    // Delete courtA through venueB route -> 404
    $this->actingAs($this->superadmin)
        ->delete(route('admin.venues.courts.destroy', ['venue' => $venueB, 'court' => $courtA]))
        ->assertNotFound();

    expect($courtA->fresh()->name)->toBe('Court Alpha 1');
});

test('venue photo upload is stored on public disk and old photo is removed on update', function (): void {
    Storage::fake('public');

    $image = UploadedFile::fake()->image('venue_photo.jpg', 640, 480);

    $this->actingAs($this->superadmin)
        ->post(route('admin.venues.store'), [
            'name' => 'Photo Venue',
            'is_active' => true,
            'image' => $image,
        ])
        ->assertRedirect(route('admin.venues.index'));

    $venue = Venue::where('name', 'Photo Venue')->first();
    expect($venue)->not->toBeNull()
        ->and($venue->image)->not->toBeNull();

    Storage::disk('public')->assertExists($venue->image);
    $oldImagePath = $venue->image;

    // Update with new image
    $newImage = UploadedFile::fake()->image('venue_photo_new.png', 800, 600);
    $this->actingAs($this->superadmin)
        ->put(route('admin.venues.update', $venue), [
            'name' => 'Photo Venue Updated',
            'is_active' => true,
            'image' => $newImage,
        ])
        ->assertRedirect(route('admin.venues.index'));

    $venue->refresh();
    expect($venue->image)->not->toBe($oldImagePath);
    Storage::disk('public')->assertMissing($oldImagePath);
    Storage::disk('public')->assertExists($venue->image);
});

test('venue photo rejects SVG uploads', function (): void {
    Storage::fake('public');

    $svgFile = UploadedFile::fake()->create('malicious.svg', 10, 'image/svg+xml');

    $this->actingAs($this->superadmin)
        ->post(route('admin.venues.store'), [
            'name' => 'SVG Venue',
            'is_active' => true,
            'image' => $svgFile,
        ])
        ->assertSessionHasErrors('image');

    expect(Venue::where('name', 'SVG Venue')->exists())->toBeFalse();
});

test('school village relation resolves correctly', function (): void {
    Wilayah::insert([
        ['kode' => '32.73.01.2001', 'nama' => 'Sukarasa'],
    ]);

    $school = School::create([
        'npsn' => '99999999',
        'name' => 'SD Sukarasa',
        'school_level' => 'sd',
        'school_status' => 'negeri',
        'village_id' => '32.73.01.2001',
    ]);

    expect($school->village)->not->toBeNull()
        ->and($school->village->nama)->toBe('Sukarasa');
});

test('development level delete returns back with error flash on FK violation query exception', function (): void {
    $category = SportCategory::create(['name' => 'Basketball', 'slug' => 'basketball', 'is_active' => true]);
    $level = DevelopmentLevel::create([
        'sport_category_id' => $category->id,
        'name' => 'Junior Level',
        'order' => 1,
        'is_active' => true,
    ]);

    DevelopmentLevel::deleting(function (): void {
        throw new QueryException('mysql', 'DELETE FROM development_levels', [], new \Exception('FOREIGN KEY constraint failed'));
    });

    try {
        $this->actingAs($this->superadmin)
            ->from(route('admin.development-levels.index'))
            ->delete(route('admin.development-levels.destroy', $level))
            ->assertRedirect(route('admin.development-levels.index'))
            ->assertSessionHas('error', 'Jenjang sedang dipakai, nonaktifkan saja.');
    } finally {
        DevelopmentLevel::getEventDispatcher()->forget('eloquent.deleting: '.DevelopmentLevel::class);
    }
});

test('DELETE jenjang yang dipakai community_athletes ditolak baris tetap ada dan ada flash error', function (): void {
    $category = SportCategory::create(['name' => 'Basketball FK', 'slug' => 'basketball-fk', 'is_active' => true]);
    $level = DevelopmentLevel::create([
        'sport_category_id' => $category->id,
        'name' => 'KU-16 FK Test',
        'order' => 1,
        'is_active' => true,
    ]);

    $community = Community::factory()->create();
    $athleteUser = User::factory()->create(['is_active' => true]);
    CommunityAthlete::create([
        'community_id' => $community->id,
        'user_id' => $athleteUser->id,
        'development_level_id' => $level->id,
        'status' => 'pending',
        'region_status' => 'pending',
        'initiated_by' => 'community',
        'joined_at' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->superadmin)
        ->from(route('admin.development-levels.index'))
        ->delete(route('admin.development-levels.destroy', $level));

    $response->assertRedirect(route('admin.development-levels.index'))
        ->assertSessionHas('error', 'Jenjang sedang dipakai, nonaktifkan saja.');

    $this->assertDatabaseHas('development_levels', [
        'id' => $level->id,
    ]);
});
