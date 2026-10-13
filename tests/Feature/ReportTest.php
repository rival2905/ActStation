<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Report\Services\GapAnalysisService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    if (Permission::where('name', 'report.view')->doesntExist()) {
        $this->seed(RolePermissionSeeder::class);
    }
});

test('user tanpa report.view mendapat 403', function (): void {
    $staffWithoutReport = User::factory()->create(['is_active' => true]);
    $customRole = Role::firstOrCreate(['name' => 'internal_no_report', 'guard_name' => 'web'], ['panel_access' => 'internal']);
    $staffWithoutReport->assignRole($customRole);

    $response = $this->actingAs($staffWithoutReport)->get(route('admin.reports.index'));

    $response->assertForbidden();
});

test('user dengan report.view dapat mengakses reports.index', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('admin.reports.index'));

    $response->assertOk();
});

test('tanpa bbox dan tanpa filter menghasilkan 422', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->getJson(route('admin.coverage-map.markers'));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['bbox']);
});

test('marker bbox tidak mengembalikan titik di luar bbox', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    DB::table('wilayah')->insert([
        ['kode' => '32', 'nama' => 'Jawa Barat'],
        ['kode' => '32.01', 'nama' => 'Kabupaten Bogor'],
        ['kode' => '33', 'nama' => 'Jawa Tengah'],
        ['kode' => '33.01', 'nama' => 'Kabupaten Cilacap'],
    ]);

    $insideId = DB::table('schools')->insertGetId([
        'npsn' => 'SCH-INSIDE',
        'name' => 'Sekolah Dalam BBox',
        'school_level' => 'sma',
        'school_status' => 'negeri',
        'province_id' => '32',
        'city_id' => '32.01',
        'latitude' => -6.20000000,
        'longitude' => 106.81000000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $outsideId = DB::table('schools')->insertGetId([
        'npsn' => 'SCH-OUTSIDE',
        'name' => 'Sekolah Luar BBox',
        'school_level' => 'sma',
        'school_status' => 'negeri',
        'province_id' => '33',
        'city_id' => '33.01',
        'latitude' => -7.50000000,
        'longitude' => 110.00000000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.coverage-map.markers', [
        'bbox' => '-6.30,106.70,-6.10,106.90',
    ]));

    $response->assertOk();
    $data = $response->json('data');

    $ids = collect($data)->pluck('id')->all();
    expect($ids)->toContain($insideId)
        ->and($ids)->not->toContain($outsideId);
});

test('limit menghasilkan truncated=true', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    DB::table('wilayah')->insert([
        'kode' => '32.01',
        'nama' => 'Kabupaten Bogor',
    ]);

    $schools = [];
    $now = now();
    for ($i = 1; $i <= 2005; $i++) {
        $schools[] = [
            'npsn' => 'TRUNC-'.$i,
            'name' => 'Sekolah '.$i,
            'school_level' => 'smp',
            'school_status' => 'negeri',
            'province_id' => '32',
            'city_id' => '32.01',
            'latitude' => -6.20000000,
            'longitude' => 106.81000000,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    foreach (array_chunk($schools, 500) as $chunk) {
        DB::table('schools')->insert($chunk);
    }

    $response = $this->actingAs($admin)->getJson(route('admin.coverage-map.markers', [
        'city_id' => '32.01',
    ]));

    $response->assertOk();
    expect($response->json('truncated'))->toBeTrue()
        ->and(count($response->json('data')))->toBe(2000);
});

test('JSON marker tidak memuat field kontak', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    DB::table('wilayah')->insert([
        'kode' => '32.01',
        'nama' => 'Kabupaten Bogor',
    ]);

    DB::table('communities')->insert([
        'name' => 'Klub Basket Rahasia',
        'slug' => 'klub-basket-rahasia',
        'status' => 'verified',
        'province_id' => '32',
        'city_id' => '32.01',
        'latitude' => -6.20000000,
        'longitude' => 106.81000000,
        'contact_person' => 'Budi Santoso',
        'contact_phone' => '081299998888',
        'contact_email' => 'budi@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.coverage-map.markers', [
        'city_id' => '32.01',
    ]));

    $response->assertOk();
    $first = $response->json('data.0');

    expect($first)->toHaveKeys(['id', 'type', 'name', 'lat', 'lng', 'city'])
        ->and($first)->not->toHaveKey('contact_person')
        ->and($first)->not->toHaveKey('contact_phone')
        ->and($first)->not->toHaveKey('contact_email')
        ->and($first)->not->toHaveKey('phone')
        ->and($first)->not->toHaveKey('email');
});

test('angka gap analysis benar untuk data fixture kecil (mis. 4 sekolah, 1 punya komunitas verified = 25%)', function (): void {
    $this->seed(RolePermissionSeeder::class);

    DB::table('wilayah')->insert([
        'kode' => '32',
        'nama' => 'Jawa Barat',
    ]);

    $now = now();
    DB::table('schools')->insert([
        ['npsn' => 'SCH-01', 'name' => 'Sekolah 1', 'school_level' => 'sma', 'school_status' => 'negeri', 'province_id' => '32', 'city_id' => '32.01', 'created_at' => $now, 'updated_at' => $now],
        ['npsn' => 'SCH-02', 'name' => 'Sekolah 2', 'school_level' => 'sma', 'school_status' => 'negeri', 'province_id' => '32', 'city_id' => '32.01', 'created_at' => $now, 'updated_at' => $now],
        ['npsn' => 'SCH-03', 'name' => 'Sekolah 3', 'school_level' => 'sma', 'school_status' => 'negeri', 'province_id' => '32', 'city_id' => '32.01', 'created_at' => $now, 'updated_at' => $now],
        ['npsn' => 'SCH-04', 'name' => 'Sekolah 4', 'school_level' => 'sma', 'school_status' => 'negeri', 'province_id' => '32', 'city_id' => '32.01', 'created_at' => $now, 'updated_at' => $now],
    ]);

    // Hanya 1 sekolah punya komunitas verified
    DB::table('communities')->insert([
        'npsn' => 'SCH-01',
        'name' => 'Komunitas Sekolah 1',
        'slug' => 'komunitas-sekolah-1',
        'status' => 'verified',
        'province_id' => '32',
        'city_id' => '32.01',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $service = app(GapAnalysisService::class);
    $result = $service->getGapAnalysis(['level' => 'province']);

    expect($result['summary']['total_schools'])->toBe(4)
        ->and($result['summary']['reached_schools'])->toBe(1)
        ->and($result['summary']['gap_schools'])->toBe(3)
        ->and($result['summary']['overall_percentage'])->toBe(25.0)
        ->and($result['summary']['overall_classification'])->toBe('medium');

    $region = $result['regions'][0];
    expect($region['region_code'])->toBe('32')
        ->and($region['total'])->toBe(4)
        ->and($region['reached'])->toBe(1)
        ->and($region['gap'])->toBe(3)
        ->and($region['percentage'])->toBe(25.0)
        ->and($region['classification'])->toBe('medium');
});

test('drill-down kota hanya menghitung sekolah dengan prefix kode itu', function (): void {
    $this->seed(RolePermissionSeeder::class);

    DB::table('wilayah')->insert([
        ['kode' => '32', 'nama' => 'Jawa Barat'],
        ['kode' => '32.01', 'nama' => 'Kabupaten Bogor'],
        ['kode' => '32.02', 'nama' => 'Kabupaten Sukabumi'],
        ['kode' => '31', 'nama' => 'DKI Jakarta'],
        ['kode' => '31.71', 'nama' => 'Jakarta Pusat'],
    ]);

    $now = now();
    DB::table('schools')->insert([
        ['npsn' => 'BG-1', 'name' => 'SMA Bogor 1', 'school_level' => 'sma', 'school_status' => 'negeri', 'province_id' => '32', 'city_id' => '32.01', 'created_at' => $now, 'updated_at' => $now],
        ['npsn' => 'BG-2', 'name' => 'SMA Bogor 2', 'school_level' => 'sma', 'school_status' => 'negeri', 'province_id' => '32', 'city_id' => '32.01', 'created_at' => $now, 'updated_at' => $now],
        ['npsn' => 'SK-1', 'name' => 'SMA Sukabumi 1', 'school_level' => 'sma', 'school_status' => 'negeri', 'province_id' => '32', 'city_id' => '32.02', 'created_at' => $now, 'updated_at' => $now],
        ['npsn' => 'JK-1', 'name' => 'SMA Jakarta 1', 'school_level' => 'sma', 'school_status' => 'negeri', 'province_id' => '31', 'city_id' => '31.71', 'created_at' => $now, 'updated_at' => $now],
    ]);

    $service = app(GapAnalysisService::class);
    $result = $service->getGapAnalysis([
        'level' => 'city',
        'parent_code' => '32',
    ]);

    $regionCodes = collect($result['regions'])->pluck('region_code')->all();

    expect($regionCodes)->toContain('32.01')
        ->and($regionCodes)->toContain('32.02')
        ->and($regionCodes)->not->toContain('31.71');
});

test('CSV diawali BOM dan berdelimiter titik koma', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    // Summary export
    $summaryResponse = $this->actingAs($admin)->get(route('admin.reports.export', [
        'type' => 'summary',
        'format' => 'csv',
    ]));

    $summaryResponse->assertOk();
    $summaryContent = $summaryResponse->streamedContent();

    expect($summaryContent)->toStartWith("\xEF\xBB\xBF")
        ->and($summaryContent)->toContain(';')
        ->and($summaryResponse->headers->get('Content-Type'))->toContain('text/csv');

    // Gap analysis export
    $gapResponse = $this->actingAs($admin)->get(route('admin.coverage-map.gap-export'));

    $gapResponse->assertOk();
    $gapContent = $gapResponse->streamedContent();

    expect($gapContent)->toStartWith("\xEF\xBB\xBF")
        ->and($gapContent)->toContain(';')
        ->and($gapResponse->headers->get('Content-Type'))->toContain('text/csv');
});

test('setelah seeder dijalankan dua kali, password superadmin yang sudah diubah tetap sama dan permission tambahan manual tidak dicabut', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->firstOrFail();
    $newHash = Hash::make('manual-secret-password-123');
    $superadmin->update(['password' => $newHash]);

    $staffRole = Role::findByName('staff', 'web');
    // Berikan permission manual tambahan yang aslinya bukan milik staff di seeder
    $extraPermission = Permission::where('name', 'user.create')->firstOrFail();
    $staffRole->givePermissionTo($extraPermission);

    expect($staffRole->hasPermissionTo('user.create'))->toBeTrue();

    // Jalankan seeder kedua kali
    $this->seed(RolePermissionSeeder::class);

    $superadmin->refresh();
    expect(Hash::check('manual-secret-password-123', $superadmin->password))->toBeTrue();

    $staffRole->refresh();
    expect($staffRole->hasPermissionTo('user.create'))->toBeTrue();
});

test('endpoint gap-schools mengembalikan daftar sekolah belum terjangkau terpaginasi', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    DB::table('wilayah')->insert([
        'kode' => '32.01',
        'nama' => 'Kabupaten Bogor',
    ]);

    DB::table('schools')->insert([
        'npsn' => 'UNREACHED-01',
        'name' => 'SMA Belum Terjangkau',
        'school_level' => 'sma',
        'school_status' => 'negeri',
        'province_id' => '32',
        'city_id' => '32.01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.coverage-map.gap-schools', [
        'city_id' => '32.01',
    ]));

    $response->assertOk();
    expect($response->json('data.0.npsn'))->toBe('UNREACHED-01')
        ->and($response->json('total'))->toBeGreaterThanOrEqual(1);
});

test('endpoint search peta mengembalikan hasil pencarian yang memiliki koordinat', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    DB::table('venues')->insert([
        'name' => 'GOR Bulungan Cabor Basket',
        'province_id' => '31',
        'city_id' => '31.71',
        'latitude' => -6.24000000,
        'longitude' => 106.79000000,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.coverage-map.search', [
        'q' => 'Bulungan',
    ]));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->not->toBeEmpty()
        ->and($data[0]['name'])->toContain('Bulungan')
        ->and($data[0]['type'])->toBe('venue');
});

test('export PDF mengembalikan view print CSS', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('admin.reports.export', [
        'type' => 'summary',
        'format' => 'pdf',
    ]));

    $response->assertOk();
    $response->assertViewIs('admin.reports.print');
    $response->assertSee('window.print()', false);
});

test('dashboard reports tidak crash pada cache hit kedua ketika serializable_classes false', function (): void {
    config(['cache.default' => 'database']);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    DB::table('transactions')->insert([
        'invoice_number' => 'INV-CACHE-TEST-1',
        'user_id' => $admin->id,
        'type' => 'ticket',
        'payable_type' => 'order',
        'payable_id' => 1,
        'status' => 'paid',
        'amount' => 50000,
        'fee' => 0,
        'total' => 50000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $res1 = $this->actingAs($admin)->get(route('admin.reports.index', ['period' => 'month']));
    $res1->assertOk();

    $res2 = $this->actingAs($admin)->get(route('admin.reports.index', ['period' => 'month']));
    $res2->assertOk();
});

test('bbox di luar rentang koordinat valid mengembalikan 422', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->getJson(route('admin.coverage-map.markers', [
        'bbox' => '-9999,-9999,9999,9999',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['bbox']);
});

test('bbox dengan south melebihi north mengembalikan 422', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->getJson(route('admin.coverage-map.markers', [
        'bbox' => '-6.0,106.7,-6.5,106.9',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['bbox']);
});

test('gap analysis cache key dinormalisasi sehingga refresh dan pagination membersihkan cache yang tepat', function (): void {
    $service = app(GapAnalysisService::class);

    $result1 = $service->getGapAnalysis(['level' => 'province']);

    DB::table('wilayah')->insertOrIgnore(['kode' => '32', 'nama' => 'Jawa Barat']);
    $now = now();
    DB::table('schools')->insert([
        'npsn' => 'SCH-GAP-REFRESH-TEST',
        'name' => 'Sekolah Refresh Test',
        'school_level' => 'sma',
        'school_status' => 'negeri',
        'province_id' => '32',
        'city_id' => '32.01',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Refresh with extra filters (refresh, page, per_page)
    $service->getGapAnalysis(['level' => 'province', 'refresh' => 1, 'page' => 2, 'per_page' => 10], true);

    // Normal call again - must see the new school because cache was refreshed
    $result3 = $service->getGapAnalysis(['level' => 'province']);
    expect($result3['summary']['total_schools'])->toBe($result1['summary']['total_schools'] + 1);
});

test('banyak sekolah tidak membuat venue dan komunitas kelaparan starvation', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    DB::table('wilayah')->insertOrIgnore([
        'kode' => '32.73',
        'nama' => 'Kota Bandung',
    ]);

    $schools = [];
    $now = now();
    for ($i = 1; $i <= 2005; $i++) {
        $schools[] = [
            'npsn' => 'STARV-'.$i,
            'name' => 'Sekolah Starve '.$i,
            'school_level' => 'sma',
            'school_status' => 'negeri',
            'province_id' => '32',
            'city_id' => '32.73',
            'latitude' => -6.91000000,
            'longitude' => 107.61000000,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
    foreach (array_chunk($schools, 500) as $chunk) {
        DB::table('schools')->insert($chunk);
    }

    DB::table('venues')->insertGetId([
        'name' => 'GOR Bandung Juara',
        'city_id' => '32.73',
        'latitude' => -6.91400000,
        'longitude' => 107.61400000,
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('communities')->insertGetId([
        'name' => 'Komunitas Basket Bandung',
        'slug' => 'komunitas-basket-bandung',
        'status' => 'verified',
        'city_id' => '32.73',
        'latitude' => -6.91500000,
        'longitude' => 107.61500000,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.coverage-map.markers', [
        'city_id' => '32.73',
    ]));

    $response->assertOk();
    $types = collect($response->json('data'))->pluck('type')->unique()->all();

    expect($types)->toContain('venue')
        ->and($types)->toContain('verified_comm');
});

test('pencarian peta dengan karakter wildcard persen tidak mencocokkan semua data', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    DB::table('venues')->insert([
        'name' => 'Stadion Normal',
        'city_id' => '32.73',
        'latitude' => -6.90,
        'longitude' => 107.60,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.coverage-map.search', [
        'q' => '%',
    ]));

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

test('gap analysis tidak menghilangkan sekolah yang belum memiliki relasi wilayah valid', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $now = now();
    DB::table('schools')->insert([
        'npsn' => 'SCH-ORPHAN-1',
        'name' => 'Sekolah Tanpa Wilayah',
        'school_level' => 'sma',
        'school_status' => 'negeri',
        'province_id' => '99',
        'city_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $service = app(GapAnalysisService::class);
    $result = $service->getGapAnalysis(['level' => 'province'], true);

    $codes = collect($result['regions'])->pluck('region_code')->all();
    expect($codes)->toContain('UNKNOWN')
        ->and($result['summary']['total_schools'])->toBeGreaterThanOrEqual(1);
});
