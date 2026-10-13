<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\RegionOverseer;
use App\Modules\Community\Services\RegionOversightService;
use Database\Seeders\RolePermissionSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('admin');

    $this->overseerX = User::factory()->create(['is_active' => true]);
    $this->overseerX->assignRole('region_overseer');

    RegionOverseer::create([
        'user_id' => $this->overseerX->id,
        'city_id' => '32.73', // Kota Bandung
    ]);

    $this->communityX = Community::factory()->create([
        'city_id' => '32.73',
        'status' => 'verified',
    ]);

    $this->communityY = Community::factory()->create([
        'city_id' => '32.71', // Kota Bogor
        'status' => 'verified',
    ]);

    $this->athleteUserX = User::factory()->create([
        'name' => 'Atlet Bandung',
        'gender' => 'male',
        'identity_number' => '3273010101010001',
        'phone_number' => '081234567891',
        'phone_normalized' => '+6281234567891',
        'identity_image' => 'identities/ktp-bandung.jpg',
        'birth_certificate' => 'certificates/akta-bandung.jpg',
        'is_active' => true,
    ]);

    $this->athleteX = CommunityAthlete::factory()->create([
        'community_id' => $this->communityX->id,
        'user_id' => $this->athleteUserX->id,
        'status' => 'active',
        'region_status' => 'pending',
    ]);

    $this->athleteUserY = User::factory()->create([
        'name' => 'Atlet Bogor',
        'gender' => 'male',
        'identity_number' => '3271020202020002',
        'phone_number' => '081234567892',
        'phone_normalized' => '+6281234567892',
        'identity_image' => 'identities/ktp-bogor.jpg',
        'birth_certificate' => 'certificates/akta-bogor.jpg',
        'is_active' => true,
    ]);

    $this->athleteY = CommunityAthlete::factory()->create([
        'community_id' => $this->communityY->id,
        'user_id' => $this->athleteUserY->id,
        'status' => 'active',
        'region_status' => 'pending',
    ]);
});

test('overseer kota X tidak bisa melihat atau memverifikasi data kota Y', function (): void {
    $service = app(RegionOversightService::class);

    // 1. Cek query scope di service
    $scopedQuery = $service->applyAthleteScope(CommunityAthlete::query(), $this->overseerX);
    $scopedIds = $scopedQuery->pluck('id')->all();

    expect($scopedIds)->toContain($this->athleteX->id)
        ->and($scopedIds)->not->toContain($this->athleteY->id);

    // 2. HTTP index: overseer hanya melihat atlet kota X
    $response = $this->actingAs($this->overseerX)
        ->get(route('admin.region-oversight.athletes.index'));

    $response->assertOk();
    $response->assertSee('Atlet Bandung');
    $response->assertDontSee('Atlet Bogor');

    // 3. HTTP verify: overseer mencoba memverifikasi data kota Y ditolak 403
    $this->actingAs($this->overseerX)
        ->post(route('admin.region-oversight.athletes.verify', $this->athleteY), [
            'decision' => 'verified',
        ])
        ->assertForbidden();

    // 4. Service verify: langsung lempar DomainException
    expect(fn () => $service->verifyAthlete($this->athleteY, $this->overseerX, 'verified'))
        ->toThrow(DomainException::class);

    // Verifikasi database tidak berubah
    $this->assertDatabaseHas('community_athletes', [
        'id' => $this->athleteY->id,
        'region_status' => 'pending',
    ]);
});

test('batch campuran ditolak seluruhnya', function (): void {
    $service = app(RegionOversightService::class);

    // HTTP bulk verify dengan ID campuran (kota X dan kota Y)
    $this->actingAs($this->overseerX)
        ->post(route('admin.region-oversight.athletes.verify-bulk'), [
            'ids' => [$this->athleteX->id, $this->athleteY->id],
            'decision' => 'verified',
            'notes' => 'Catatan verifikasi',
        ])
        ->assertForbidden();

    // Kedua atlet harus tetap berstatus 'pending' (atomisitas transaksi)
    $this->assertDatabaseHas('community_athletes', [
        'id' => $this->athleteX->id,
        'region_status' => 'pending',
    ]);
    $this->assertDatabaseHas('community_athletes', [
        'id' => $this->athleteY->id,
        'region_status' => 'pending',
    ]);

    // Service langsung juga menolak batch campuran
    expect(fn () => $service->verifyAthletesBulk(
        [$this->athleteX->id, $this->athleteY->id],
        $this->overseerX,
        'verified'
    ))->toThrow(DomainException::class);
});

test('CSV tidak memuat kolom/nilai PII', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.athletes.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->streamedContent();

    // Pastikan nama kolom PII tidak ada
    expect($content)->not->toContain('identity_number')
        ->and($content)->not->toContain('identity_image')
        ->and($content)->not->toContain('birth_certificate')
        ->and($content)->not->toContain('phone_number')
        ->and($content)->not->toContain('phone_normalized');

    // Pastikan nilai sensitif PII atlet tidak bocor
    expect($content)->not->toContain('3273010101010001')
        ->and($content)->not->toContain('3271020202020002')
        ->and($content)->not->toContain('081234567891')
        ->and($content)->not->toContain('+6281234567891')
        ->and($content)->not->toContain('ktp-bandung')
        ->and($content)->not->toContain('akta-bandung');

    // Pastikan data non-PII tetap ada
    expect($content)->toContain('Atlet Bandung')
        ->and($content)->toContain('Atlet Bogor');
});

test('sync dijalankan dua kali menghasilkan jumlah baris sama', function (): void {
    $payload = [
        'communities' => [
            [
                'external_ref' => 'PERBASI-COMM-101',
                'name' => 'Garuda Basketball Club',
                'subtype' => 'club',
                'province_id' => '32',
                'city_id' => '32.73',
                'athletes' => [
                    [
                        'external_ref' => 'PERBASI-ATH-101',
                        'name' => 'Pemain Garuda Satu',
                        'identity_number' => '3273888899990001',
                        'jersey_number' => 10,
                        'position' => 'Guard',
                    ],
                ],
            ],
        ],
    ];

    $tempFile = storage_path('app/temp_perbasi_test.json');
    File::put($tempFile, json_encode($payload));

    try {
        // Run 1
        $exit1 = Artisan::call('community:sync-perbasi', ['--file' => $tempFile]);
        expect($exit1)->toBe(0);

        $communityCount1 = Community::where('external_ref', 'PERBASI-COMM-101')->count();
        $athleteCount1 = CommunityAthlete::where('external_ref', 'PERBASI-ATH-101')->count();
        $userCount1 = User::where('external_ref', 'PERBASI-ATH-101')->count();

        expect($communityCount1)->toBe(1)
            ->and($athleteCount1)->toBe(1)
            ->and($userCount1)->toBe(1);

        $syncedCommunity = Community::where('external_ref', 'PERBASI-COMM-101')->first();
        $syncedAthlete = CommunityAthlete::where('external_ref', 'PERBASI-ATH-101')->first();

        expect($syncedCommunity->synced_at)->not->toBeNull()
            ->and($syncedAthlete->synced_at)->not->toBeNull();

        // Run 2 (Idempoten)
        $exit2 = Artisan::call('community:sync-perbasi', ['--file' => $tempFile]);
        expect($exit2)->toBe(0);

        $communityCount2 = Community::where('external_ref', 'PERBASI-COMM-101')->count();
        $athleteCount2 = CommunityAthlete::where('external_ref', 'PERBASI-ATH-101')->count();
        $userCount2 = User::where('external_ref', 'PERBASI-ATH-101')->count();

        expect($communityCount2)->toBe($communityCount1)
            ->and($athleteCount2)->toBe($athleteCount1)
            ->and($userCount2)->toBe($userCount1);
    } finally {
        if (File::exists($tempFile)) {
            File::delete($tempFile);
        }
    }
});

test('dry-run tidak menulis', function (): void {
    $payload = [
        'communities' => [
            [
                'external_ref' => 'PERBASI-COMM-DRY',
                'name' => 'Dry Run Community',
                'subtype' => 'club',
                'city_id' => '32.73',
                'athletes' => [
                    [
                        'external_ref' => 'PERBASI-ATH-DRY',
                        'name' => 'Dry Run Athlete',
                        'identity_number' => '3273777788880001',
                    ],
                ],
            ],
        ],
    ];

    $tempFile = storage_path('app/temp_dry_run_test.json');
    File::put($tempFile, json_encode($payload));

    try {
        $exit = Artisan::call('community:sync-perbasi', [
            '--file' => $tempFile,
            '--dry-run' => true,
        ]);

        expect($exit)->toBe(0);

        $this->assertDatabaseMissing('communities', [
            'external_ref' => 'PERBASI-COMM-DRY',
        ]);
        $this->assertDatabaseMissing('community_athletes', [
            'external_ref' => 'PERBASI-ATH-DRY',
        ]);
        $this->assertDatabaseMissing('users', [
            'external_ref' => 'PERBASI-ATH-DRY',
        ]);
    } finally {
        if (File::exists($tempFile)) {
            File::delete($tempFile);
        }
    }
});

test('output command tidak memuat identity_number', function (): void {
    $secretIdentityNumber = '3273999988887777';

    $payload = [
        'communities' => [
            [
                'external_ref' => 'PERBASI-COMM-SECRET',
                'name' => 'Secret Test Community',
                'city_id' => '32.73',
                'athletes' => [
                    [
                        'external_ref' => 'PERBASI-ATH-SECRET',
                        'name' => 'Secret Athlete',
                        'identity_number' => $secretIdentityNumber,
                    ],
                ],
            ],
        ],
    ];

    $tempFile = storage_path('app/temp_secret_test.json');
    File::put($tempFile, json_encode($payload));

    try {
        Artisan::call('community:sync-perbasi', ['--file' => $tempFile]);
        $output = Artisan::output();

        expect($output)->not->toContain($secretIdentityNumber)
            ->and($output)->not->toContain('identity_number');
    } finally {
        if (File::exists($tempFile)) {
            File::delete($tempFile);
        }
    }
});

test('CRUD penugasan region_overseers untuk admin', function (): void {
    $newOverseerUser = User::factory()->create(['is_active' => true]);

    // 1. Admin store penugasan baru
    $response = $this->actingAs($this->admin)
        ->post(route('admin.region-overseers.store'), [
            'user_id' => $newOverseerUser->id,
            'city_id' => '32.71',
        ]);

    $response->assertRedirect(route('admin.region-overseers.index'));
    $this->assertDatabaseHas('region_overseers', [
        'user_id' => $newOverseerUser->id,
        'city_id' => '32.71',
    ]);

    $overseerRecord = RegionOverseer::where('user_id', $newOverseerUser->id)
        ->where('city_id', '32.71')
        ->first();

    // 2. Admin delete penugasan
    $deleteResponse = $this->actingAs($this->admin)
        ->delete(route('admin.region-overseers.destroy', $overseerRecord));

    $deleteResponse->assertRedirect(route('admin.region-overseers.index'));
    $this->assertDatabaseMissing('region_overseers', [
        'id' => $overseerRecord->id,
    ]);
});

test('admin dan superadmin bypass scope pengawasan wilayah', function (): void {
    $service = app(RegionOversightService::class);

    // Admin bypass scope
    expect($service->isBypassed($this->admin))->toBeTrue()
        ->and($service->getScopedCityIds($this->admin))->toBeNull();

    // Admin dapat memverifikasi atlet kota X dan kota Y sekaligus
    $this->actingAs($this->admin)
        ->post(route('admin.region-oversight.athletes.verify-bulk'), [
            'ids' => [$this->athleteX->id, $this->athleteY->id],
            'decision' => 'verified',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('community_athletes', [
        'id' => $this->athleteX->id,
        'region_status' => 'verified',
    ]);
    $this->assertDatabaseHas('community_athletes', [
        'id' => $this->athleteY->id,
        'region_status' => 'verified',
    ]);
});
