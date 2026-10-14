<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Community\Models\Community;
use App\Modules\MasterData\Models\School;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

uses(RefreshDatabase::class);

test('school seeder imports dataset idempotently and preserves richer existing metadata', function (): void {
    $this->seed(RolePermissionSeeder::class);

    // Pre-insert an existing school with rich metadata
    DB::table('schools')->insert([
        'npsn' => '20280168',
        'name' => 'MAS NURUL HIDAYAH SIDAMULYA ORIGINAL',
        'school_level' => 'sma',
        'school_status' => 'swasta',
        'province_id' => '32',
        'city_id' => '32.01',
        'subdistrict_id' => '32.01.99',
        'village_id' => '32.01.99.2001',
        'address' => 'Jl. Sidamulya Custom Address',
        'latitude' => -6.58912345,
        'longitude' => 106.78912345,
        'dataset_year' => 2026,
        'source' => 'custom_source',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Attach a community to this school NPSN to verify ownership relation safety
    $community = Community::create([
        'name' => 'Klub Basket Sidamulya',
        'slug' => 'klub-basket-sidamulya',
        'type' => 'club',
        'subtype' => 'school',
        'npsn' => '20280168',
        'status' => 'verified',
    ]);

    // First seed run
    $seeder = new SchoolSeeder;
    $seeder->run();

    $countFirstRun = School::count();
    expect($countFirstRun)->toBe(31325);

    // Verify richer metadata was preserved on pre-existing school
    $existing = School::where('npsn', '20280168')->first();
    expect($existing)->not->toBeNull()
        ->and($existing->name)->toBe('MAS NURUL HIDAYAH SIDAMULYA')
        ->and($existing->school_level)->toBe('sma')
        ->and($existing->school_status)->toBe('swasta')
        ->and($existing->subdistrict_id)->toBe('32.01.99')
        ->and($existing->village_id)->toBe('32.01.99.2001')
        ->and((float) $existing->latitude)->toBe(-6.58912345)
        ->and((float) $existing->longitude)->toBe(106.78912345);

    // Verify community relation remains intact
    expect(Community::where('npsn', '20280168')->first()?->id)->toBe($community->id);

    // Second seed run (Idempotency test)
    $seeder->run();

    $countSecondRun = School::count();
    expect($countSecondRun)->toBe(31325);

    // Verify aggregate counts by level match expected source master distribution
    $levelCounts = School::query()
        ->select('school_level', DB::raw('count(*) as aggregate'))
        ->groupBy('school_level')
        ->pluck('aggregate', 'school_level')
        ->toArray();

    expect($levelCounts)->toEqual([
        'sd' => 19457,
        'smp' => 5966,
        'sma' => 3214,
        'smk' => 2688,
    ]);

    // Verify status counts match expected distribution
    $statusCounts = School::query()
        ->select('school_status', DB::raw('count(*) as aggregate'))
        ->groupBy('school_status')
        ->pluck('aggregate', 'school_status')
        ->toArray();

    expect($statusCounts)->toEqual([
        'swasta' => 11764,
        'negeri' => 19561,
    ]);
});

test('school seeder fails on invalid npsn format or duplicate npsn', function (): void {
    $tempCsv = tempnam(sys_get_temp_dir(), 'invalid_school_').'.csv';

    // Test 1: Invalid NPSN format
    file_put_contents($tempCsv, implode("\n", [
        'npsn,name,school_level,school_status',
        'INVALIDNPSN,Test School,sd,negeri',
    ]));

    $csvProperty = new \ReflectionClass(SchoolSeeder::class);
    $method = $csvProperty->getMethod('seedFromCsv');
    $method->setAccessible(true);

    $seeder = new SchoolSeeder;

    expect(fn () => $method->invoke($seeder, $tempCsv))
        ->toThrow(RuntimeException::class, 'Invalid NPSN format');

    // Test 2: Duplicate NPSN
    file_put_contents($tempCsv, implode("\n", [
        'npsn,name,school_level,school_status',
        '10000001,Test School 1,sd,negeri',
        '10000001,Test School 2,sd,negeri',
    ]));

    expect(fn () => $method->invoke($seeder, $tempCsv))
        ->toThrow(RuntimeException::class, 'Duplicate NPSN');

    @unlink($tempCsv);
});

test('school seeder normalizes broad school levels with compatibility and rejects unknown levels', function (): void {
    $tempCsv = tempnam(sys_get_temp_dir(), 'levels_school_').'.csv';

    file_put_contents($tempCsv, implode("\n", [
        'npsn,name,school_level,school_status',
        '10000001,SD Test,sd,negeri',
        '10000002,MI Test,mi,swasta',
        '10000003,SMP Test,smp,negeri',
        '10000004,MTs Test,mts,swasta',
        '10000005,SMA Test,sma,negeri',
        '10000006,MA Test,ma,swasta',
        '10000007,SMK Test,smk,negeri',
    ]));

    $ref = new \ReflectionClass(SchoolSeeder::class);
    $method = $ref->getMethod('seedFromCsv');
    $method->setAccessible(true);

    $seeder = new SchoolSeeder;
    $method->invoke($seeder, $tempCsv);

    expect(School::where('npsn', '10000001')->value('school_level'))->toBe('sd')
        ->and(School::where('npsn', '10000002')->value('school_level'))->toBe('sd')
        ->and(School::where('npsn', '10000003')->value('school_level'))->toBe('smp')
        ->and(School::where('npsn', '10000004')->value('school_level'))->toBe('smp')
        ->and(School::where('npsn', '10000005')->value('school_level'))->toBe('sma')
        ->and(School::where('npsn', '10000006')->value('school_level'))->toBe('sma')
        ->and(School::where('npsn', '10000007')->value('school_level'))->toBe('smk');

    // Unknown level test
    file_put_contents($tempCsv, implode("\n", [
        'npsn,name,school_level,school_status',
        '10000008,Unknown Level School,universitas,negeri',
    ]));

    expect(fn () => $method->invoke($seeder, $tempCsv))
        ->toThrow(RuntimeException::class, 'Unknown school level [universitas]');

    @unlink($tempCsv);
});
