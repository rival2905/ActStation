<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->newUser = User::factory()->create([
        'name' => 'Rival Test User',
        'email' => 'rival.test.'.uniqid().'@example.com',
        'phone_number' => '081234567890',
        'is_active' => true,
    ]);
    $this->newUser->assignRole('member');
});

test('user baru tanpa komunitas melihat menu buat komunitas dan tidak melihat menu manajemen komunitas', function (): void {
    $response = $this->actingAs($this->newUser)
        ->get(route('panel.community-events.index'));

    $response->assertOk()
        ->assertSee('Buat Komunitas')
        ->assertSee('Anda belum memiliki komunitas terdaftar')
        ->assertDontSee('Roster Atlet')
        ->assertDontSee('Daftar Pelatih Komunitas');
});

test('user baru dapat membuka formulir pembuatan komunitas baru', function (): void {
    $response = $this->actingAs($this->newUser)
        ->get(route('panel.community.create'));

    $response->assertOk()
        ->assertSee('Formulir Pendirian Komunitas')
        ->assertSee('Klub Olahraga')
        ->assertSee('SD/MI')
        ->assertSee('SMP/MTs')
        ->assertSee('SMA/SMK')
        ->assertDontSee('Sekolah (SD / SMP / SMA)');
});

test('user baru berhasil mendaftarkan komunitas dan otomatis menjadi manajer aktif', function (): void {
    $response = $this->actingAs($this->newUser)
        ->post(route('panel.community.store'), [
            'name' => 'Klub Basket Satria Muda Pratama',
            'subtype' => 'club',
            'sport_category_id' => $this->sport->id,
            'description' => 'Komunitas basket usia muda pembinaan prestasi',
            'province_id' => '32',
            'city_id' => '32.73',
            'subdistrict_id' => '32.73.01',
            'village_id' => '32.73.01.1001',
            'postal_code' => '40111',
            'address' => 'Jl. Merdeka No. 45, Kota Bandung',
            'contact_person' => 'Rival Test User',
            'contact_phone' => '081234567890',
            'contact_email' => $this->newUser->email,
        ]);

    $response->assertRedirect(route('panel.profile.show'))
        ->assertSessionHas('success');

    $community = Community::where('name', 'Klub Basket Satria Muda Pratama')->first();
    expect($community)->not->toBeNull()
        ->and($community->status)->toBe('draft')
        ->and($community->subtype)->toBe('club');

    $official = CommunityOfficial::where('community_id', $community->id)
        ->where('user_id', $this->newUser->id)
        ->first();

    expect($official)->not->toBeNull()
        ->and($official->position)->toBe('manager')
        ->and($official->status)->toBe('active');

    expect($this->newUser->fresh()->hasRole('community_manager'))->toBeTrue();

    // Sekarang user sudah memiliki komunitas, menu lengkap harus tampil
    $panelResponse = $this->actingAs($this->newUser->fresh())
        ->get(route('panel.community-events.index'));

    $panelResponse->assertOk()
        ->assertSee('Profil Komunitas')
        ->assertSee('Pengurus')
        ->assertSee('Pelatih')
        ->assertSee('Roster Atlet')
        ->assertSee('Dokumen');
});

test('pendaftaran komunitas sekolah mewajibkan nomor NPSN', function (): void {
    $response = $this->actingAs($this->newUser)
        ->post(route('panel.community.store'), [
            'name' => 'SMA Negeri 1 Bandung Basket',
            'subtype' => 'school',
            'npsn' => '', // kosong
            'sport_category_id' => $this->sport->id,
            'province_id' => '32',
            'city_id' => '32.73',
            'contact_person' => 'Guru Olahraga',
            'contact_phone' => '081234567890',
            'contact_email' => 'sman1@sekolah.sch.id',
        ]);

    $response->assertSessionHasErrors(['npsn']);
});

test('user yang sudah menjadi pengurus komunitas dilarang membuat komunitas kedua secara duplikat', function (): void {
    $community = Community::create([
        'name' => 'Klub Pertama Rival',
        'slug' => 'klub-pertama-rival',
        'subtype' => 'club',
        'status' => 'draft',
    ]);

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $this->newUser->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now(),
    ]);

    // Akses create di-redirect
    $this->actingAs($this->newUser)
        ->get(route('panel.community.create'))
        ->assertRedirect(route('panel.profile.show'));

    // Akses POST store ditolak 403
    $this->actingAs($this->newUser)
        ->post(route('panel.community.store'), [
            'name' => 'Klub Kedua Duplikat',
            'subtype' => 'club',
            'province_id' => '32',
            'city_id' => '32.73',
            'contact_person' => 'Rival Test User',
            'contact_phone' => '081234567890',
            'contact_email' => $this->newUser->email,
        ])
        ->assertForbidden();
});
