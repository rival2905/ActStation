<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\MasterData\Models\School;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin schools table renders falcon advance ajax table layout with search', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();

    School::create([
        'npsn' => '20101234',
        'name' => 'SMAN 1 Falcon Test',
        'school_level' => 'sma',
        'school_status' => 'negeri',
    ]);

    $response = $this->actingAs($superadmin)->get(route('admin.schools.index'));

    $response->assertOk();
    $response->assertSee('id="schoolsTableContainer"', false);
    $response->assertSee('id="schoolsTable"', false);
    $response->assertSee('id="schoolSearchInput"', false);
    $response->assertSee('id="schoolLevelFilter"', false);
    $response->assertSee('id="schoolStatusFilter"', false);
    $response->assertSee('Daftar Sekolah');
    $response->assertSee('data-sort="npsn"', false);
    $response->assertSee('data-sort="name"', false);
    $response->assertSee('data-sort="school_level"', false);
    $response->assertSee('data-sort="school_status"', false);
    $response->assertSee('data-sort="city_id"', false);
    $response->assertSee('20101234');
    $response->assertSee('SMAN 1 Falcon Test');
    $response->assertSee('id="schoolsPaginationPrev"', false);
    $response->assertSee('id="schoolsPaginationNext"', false);
    $response->assertSee('id="schoolsListInfo"', false);
});

test('admin schools returns json pagination when requested via ajax', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();

    School::create([
        'npsn' => '20101234',
        'name' => 'SMAN 1 Falcon Test',
        'school_level' => 'sma',
        'school_status' => 'negeri',
    ]);

    $response = $this->actingAs($superadmin)->getJson(route('admin.schools.index'));

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'npsn', 'name', 'school_level', 'school_status', 'city_name', 'show_url'],
        ],
        'current_page',
        'last_page',
        'per_page',
        'total',
        'from',
        'to',
    ]);
    expect($response->json('total'))->toBeGreaterThanOrEqual(1);
});

test('admin schools search via ajax filters records properly', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();

    School::create([
        'npsn' => '99887766',
        'name' => 'SMK Unik Search Test',
        'school_level' => 'smk',
        'school_status' => 'swasta',
    ]);

    $response = $this->actingAs($superadmin)->getJson(route('admin.schools.index', ['search' => '99887766']));

    $response->assertOk();
    $items = collect($response->json('data'));
    expect($items->pluck('npsn'))->toContain('99887766');
});
