<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\EntityRequirements;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\MasterData\Models\School;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web', 'panel_access' => 'external']);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->user = User::factory()->create([
        'name' => 'Test Community User',
        'email' => 'comm.user.'.uniqid().'@example.com',
        'phone_number' => '081299990001',
        'is_active' => true,
    ]);
    $this->user->assignRole('member');

    // Create real master schools fixtures
    $this->schoolSd = School::query()->firstOrCreate(
        ['npsn' => '10000001'],
        [
            'name' => 'SD Negeri 1 Test Master',
            'school_level' => 'sd',
            'school_status' => 'negeri',
            'province_id' => '32',
            'city_id' => '32.73',
            'address' => 'Jl. Pendidikan SD No. 1',
            'dataset_year' => 2026,
            'source' => 'test',
        ]
    );

    $this->schoolSmp = School::query()->firstOrCreate(
        ['npsn' => '20000002'],
        [
            'name' => 'SMP Negeri 2 Test Master',
            'school_level' => 'smp',
            'school_status' => 'negeri',
            'province_id' => '32',
            'city_id' => '32.73',
            'address' => 'Jl. Pendidikan SMP No. 2',
            'dataset_year' => 2026,
            'source' => 'test',
        ]
    );

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

    $this->schoolSmk = School::query()->firstOrCreate(
        ['npsn' => '40000004'],
        [
            'name' => 'SMK Negeri 4 Test Master',
            'school_level' => 'smk',
            'school_status' => 'negeri',
            'province_id' => '32',
            'city_id' => '32.73',
            'address' => 'Jl. Pendidikan SMK No. 4',
            'dataset_year' => 2026,
            'source' => 'test',
        ]
    );
});

test('/panel/community/create displays separate SD/MI, SMP/MTs, SMA/SMK choices and absence of combined Sekolah', function (): void {
    $response = $this->actingAs($this->user)
        ->get(route('panel.community.create'));

    $response->assertOk();

    // Verify all 7 specific choices present
    foreach (EntityRequirements::COMMUNITY_FORM_SUBTYPES as $key => $label) {
        $response->assertSee($label);
        $response->assertSee('value="'.$key.'"', false);
    }

    // Verify absence of combined Sekolah option
    $response->assertDontSee('Sekolah (SD / SMP / SMA)');
    $response->assertDontSee('<option value="school"', false);
});

test('panel.schools.search authorizes external panel users and filters by q, level, and city', function (): void {
    // Unauthorized access redirects / forbids
    $this->getJson(route('panel.schools.search', ['q' => 'Test Master']))
        ->assertUnauthorized();

    // Authorized external member search
    $response = $this->actingAs($this->user)
        ->getJson(route('panel.schools.search', ['q' => 'Test Master']));

    $response->assertOk()
        ->assertJsonCount(4);

    // Filter by level sd
    $sdResponse = $this->actingAs($this->user)
        ->getJson(route('panel.schools.search', ['q' => 'Test Master', 'level' => 'school_sd']));

    $sdResponse->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['npsn' => '10000001', 'school_level' => 'sd']);

    // Filter by level smp
    $smpResponse = $this->actingAs($this->user)
        ->getJson(route('panel.schools.search', ['q' => 'Test Master', 'level' => 'smp']));

    $smpResponse->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['npsn' => '20000002', 'school_level' => 'smp']);

    // Filter by level sma (matches both sma and smk)
    $smaResponse = $this->actingAs($this->user)
        ->getJson(route('panel.schools.search', ['q' => 'Test Master', 'level' => 'school_sma']));

    $smaResponse->assertOk()
        ->assertJsonCount(2);

    // Query validation: q required and min:2
    $invalidResponse = $this->actingAs($this->user)
        ->getJson(route('panel.schools.search', ['q' => 'a']));

    $invalidResponse->assertStatus(422)
        ->assertJsonValidationErrors(['q']);
});

test('pendaftaran sekolah dengan spoofed atau unknown NPSN ditolak 422', function (): void {
    $response = $this->actingAs($this->user)
        ->post(route('panel.community.store'), [
            'name' => 'Sekolah Fiktif Tidak Ada di Master',
            'subtype' => 'school_sma',
            'npsn' => '99999999', // Unknown NPSN
            'sport_category_id' => $this->sport->id,
            'province_id' => '32',
            'city_id' => '32.73',
            'contact_person' => 'Guru Olahraga',
            'contact_phone' => '081234567890',
            'contact_email' => 'guru@sekolah.sch.id',
        ]);

    $response->assertSessionHasErrors(['npsn']);
    expect(session('errors')->get('npsn')[0])->toContain('NPSN tidak ditemukan dalam basis data master sekolah.');
});

test('pendaftaran sekolah dengan jenjang tidak sesuai pilihan ditolak 422', function (): void {
    // Pilih school_sd tapi masukkan NPSN SMP
    $response1 = $this->actingAs($this->user)
        ->post(route('panel.community.store'), [
            'name' => 'SD Harapan',
            'subtype' => 'school_sd',
            'npsn' => $this->schoolSmp->npsn, // NPSN SMP (level: smp)
            'sport_category_id' => $this->sport->id,
            'province_id' => '32',
            'city_id' => '32.73',
            'contact_person' => 'Guru Olahraga',
            'contact_phone' => '081234567890',
            'contact_email' => 'guru@sekolah.sch.id',
        ]);

    $response1->assertSessionHasErrors(['npsn']);
    expect(session('errors')->get('npsn')[0])->toContain('tidak sesuai dengan sub-tipe');

    // Pilih school_sma tapi masukkan NPSN SD
    $response2 = $this->actingAs($this->user)
        ->post(route('panel.community.store'), [
            'name' => 'SMA Harapan',
            'subtype' => 'school_sma',
            'npsn' => $this->schoolSd->npsn, // NPSN SD (level: sd)
            'sport_category_id' => $this->sport->id,
            'province_id' => '32',
            'city_id' => '32.73',
            'contact_person' => 'Guru Olahraga',
            'contact_phone' => '081234567890',
            'contact_email' => 'guru@sekolah.sch.id',
        ]);

    $response2->assertSessionHasErrors(['npsn']);
    expect(session('errors')->get('npsn')[0])->toContain('tidak sesuai dengan sub-tipe');
});

test('pendaftaran sekolah valid menetapkan nama kanonikal master dan menormalkan subtype ke school', function (): void {
    $response = $this->actingAs($this->user)
        ->post(route('panel.community.store'), [
            'name' => 'Nama Bebas Diketik User Yang Berbeda',
            'subtype' => 'school_sma',
            'npsn' => $this->schoolSma->npsn,
            'sport_category_id' => $this->sport->id,
            'province_id' => '32',
            'city_id' => '32.73',
            'address' => 'Jl. Pendidikan SMA No. 3 Komplek Olahraga',
            'contact_person' => 'Drs. Pembina Basket',
            'contact_phone' => '081234567890',
            'contact_email' => 'sma3@sekolah.sch.id',
        ]);

    $response->assertRedirect(route('panel.profile.show'))
        ->assertSessionHas('success');

    $community = Community::where('npsn', $this->schoolSma->npsn)->first();
    expect($community)->not->toBeNull()
        // Canonical name from master school enforced
        ->and($community->name)->toBe($this->schoolSma->name)
        // Subtype normalized to broad 'school' in DB
        ->and($community->subtype)->toBe('school')
        ->and($community->status)->toBe('draft')
        // Address allowed custom detail
        ->and($community->address)->toBe('Jl. Pendidikan SMA No. 3 Komplek Olahraga');

    $official = CommunityOfficial::where('community_id', $community->id)
        ->where('user_id', $this->user->id)
        ->first();

    expect($official)->not->toBeNull()
        ->and($official->position)->toBe('manager')
        ->and($official->status)->toBe('active');
});

test('pendaftaran sekolah dengan duplikat NPSN komunitas yang sudah ada ditolak 422', function (): void {
    // Existing registered community with same NPSN
    Community::create([
        'name' => 'Komunitas Eksisting',
        'slug' => 'komunitas-eksisting',
        'subtype' => 'school',
        'status' => 'draft',
        'npsn' => $this->schoolSmp->npsn,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('panel.community.store'), [
            'name' => 'Komunitas Baru Mau Pakai NPSN Sama',
            'subtype' => 'school_smp',
            'npsn' => $this->schoolSmp->npsn,
            'sport_category_id' => $this->sport->id,
            'province_id' => '32',
            'city_id' => '32.73',
            'contact_person' => 'Guru Olahraga',
            'contact_phone' => '081234567890',
            'contact_email' => 'guru@smp2.sch.id',
        ]);

    $response->assertSessionHasErrors(['npsn']);
    expect(session('errors')->get('npsn')[0])->toContain('sudah terdaftar pada komunitas lain');
});

test('pendaftaran entitas non-sekolah membersihkan/mengabaikan NPSN', function (): void {
    $response = $this->actingAs($this->user)
        ->post(route('panel.community.store'), [
            'name' => 'Klub Mandiri Kota Bandung',
            'subtype' => 'club',
            'npsn' => '99999999', // Spoof/irrelevant NPSN posted
            'sport_category_id' => $this->sport->id,
            'province_id' => '32',
            'city_id' => '32.73',
            'address' => 'Jl. Lapangan Basket No. 10',
            'contact_person' => 'Ketua Klub',
            'contact_phone' => '081234567890',
            'contact_email' => 'klub@bandung.com',
        ]);

    $response->assertRedirect(route('panel.profile.show'))
        ->assertSessionHas('success');

    $community = Community::where('name', 'Klub Mandiri Kota Bandung')->first();
    expect($community)->not->toBeNull()
        ->and($community->subtype)->toBe('club')
        ->and($community->npsn)->toBeNull();
});

test('profil komunitas diturunkan school token yang tepat dari master school level', function (): void {
    $community = Community::create([
        'name' => $this->schoolSmp->name,
        'slug' => 'smpn-2-test',
        'subtype' => 'school',
        'status' => 'draft',
        'npsn' => $this->schoolSmp->npsn,
    ]);

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $this->user->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('panel.profile.show'));

    $response->assertOk()
        // Derives 'school_smp' selected in dropdown from master school_level 'smp'
        ->assertSee('value="school_smp" selected', false);
});
