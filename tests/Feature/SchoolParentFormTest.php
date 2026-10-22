<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\MasterData\Models\School;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();

    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->user = User::factory()->create([
        'name' => 'Test School Manager',
        'email' => 'school.manager.'.uniqid().'@example.com',
        'phone_number' => '081299990002',
        'is_active' => true,
    ]);
    $this->user->assignRole('member');

    $this->schoolSma = School::query()->firstOrCreate(
        ['npsn' => '30000003'],
        [
            'name' => 'SMA Negeri 3 Test Master',
            'school_level' => 'sma',
            'school_status' => 'negeri',
            'province_id' => '32',
            'city_id' => '32.73',
            'address' => 'Jl. Pendidikan SMA No. 3',
            'dataset_year' => 2026,
            'source' => 'test',
        ]
    );
});

test('/panel/community/create renders school multi-ekskul info, sport toggle controls, and official school email guidance', function (): void {
    $response = $this->actingAs($this->user)
        ->get(route('panel.community.create'));

    $response->assertOk();

    // Verify school multi-ekskul guidance is rendered
    $response->assertSee('sekolah umum multi-ekskul otomatis saat daftar event', false);

    // Verify sport category control wrapper can be hidden/disabled for schools
    $response->assertSee('id="sport_category_wrapper"', false);

    // Verify email field label/guidance specifies official school email
    $response->assertSee('Email Resmi Sekolah', false);
    $response->assertSee('Email sekolah terpisah dari email login', false);
});

test('pendaftaran sekolah mengabaikan primary sport dan menormalkan ke null', function (): void {
    $response = $this->actingAs($this->user)
        ->post(route('panel.community.store'), [
            'name' => 'SMA Negeri 3 Test Master',
            'subtype' => 'school_sma',
            'npsn' => $this->schoolSma->npsn,
            'sport_category_id' => $this->sport->id, // Posted sport should be ignored/normalized to null
            'province_id' => '32',
            'city_id' => '32.73',
            'address' => 'Jl. Pendidikan SMA No. 3',
            'contact_person' => 'Drs. Pembina Basket',
            'contact_phone' => '081234567890',
            'contact_email' => 'admin@sman3bandung.sch.id',
        ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect()
        ->assertSessionHas('success');

    $community = Community::where('npsn', $this->schoolSma->npsn)->first();
    expect($community)->not->toBeNull()
        ->and($community->subtype)->toBe('school')
        ->and($community->sport_category_id)->toBeNull();
});

test('pendaftaran sekolah wajib menyertakan contact_email', function (): void {
    $response = $this->actingAs($this->user)
        ->post(route('panel.community.store'), [
            'name' => 'SMA Negeri 3 Test Master',
            'subtype' => 'school_sma',
            'npsn' => $this->schoolSma->npsn,
            'sport_category_id' => $this->sport->id,
            'province_id' => '32',
            'city_id' => '32.73',
            'address' => 'Jl. Pendidikan SMA No. 3',
            'contact_person' => 'Drs. Pembina Basket',
            'contact_phone' => '081234567890',
            'contact_email' => '', // Empty email should fail validation
        ]);

    $response->assertSessionHasErrors(['contact_email']);
});

test('pendaftaran non-sekolah tetap mempertahankan cabang olahraga pilihan', function (): void {
    $response = $this->actingAs($this->user)
        ->post(route('panel.community.store'), [
            'name' => 'Klub Mandiri Terpilih',
            'subtype' => 'club',
            'sport_category_id' => $this->sport->id,
            'province_id' => '32',
            'city_id' => '32.73',
            'address' => 'Jl. Klub No. 1',
            'contact_person' => 'Manager Klub',
            'contact_phone' => '081234567890',
            'contact_email' => 'klub@example.com',
        ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect()
        ->assertSessionHas('success');

    $community = Community::where('name', 'Klub Mandiri Terpilih')->first();
    expect($community)->not->toBeNull()
        ->and($community->subtype)->toBe('club')
        ->and($community->sport_category_id)->toBe($this->sport->id);
});
