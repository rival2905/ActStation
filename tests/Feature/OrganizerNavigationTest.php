<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

test('external users can find organizer application from their requests page', function (string $role): void {
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'], ['panel_access' => 'external']);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)->get(route('panel.my-requests.index'))
        ->assertOk()
        ->assertSee('Ajukan Penyelenggara Event')
        ->assertSee('href="'.route('panel.organizer.apply').'"', false);
    $this->get(route('panel.organizer.apply'))->assertOk();
    $this->get(route('panel.organizer.events.create'))->assertRedirect(route('panel.organizer.apply'));

    expect($user->fresh()->hasRole('organizer'))->toBeFalse();
})->with(['athlete', 'member', 'community_manager']);

test('public organizer guide states admin review rather than instant verification', function (): void {
    $this->get(route('panduan.event'))->assertOk()
        ->assertSee('menunggu review admin')
        ->assertDontSee('otomatis meng-upgrade role')
        ->assertSee('Kategori &amp; Informasi Tim', false)
        ->assertSee('Tambah Atlet dari Anggota Aktif')
        ->assertSee('dropdown jabatan')
        ->assertSee('hasil pengiriman belum pasti')
        ->assertSee('bukan otomatis terverifikasi')
        ->assertSee('verifikasi wilayah bukan syarat')
        ->assertSee('12_bulk_atlet.png')
        ->assertSee('13_bulk_pendukung.png')
        ->assertSee('14_hasil_validasi.png')
        ->assertSee('Transfer manual langsung ke penyelenggara tidak masuk perhitungan escrow platform')
        ->assertSee('tidak dapat mengubah rekening atau mencairkan dana')
        ->assertDontSee('jaminan proteksi dana 100%')
        ->assertDontSee('Turnamen Basket &amp; Regulasi Escrow', false);

    foreach (['12_bulk_atlet.png', '13_bulk_pendukung.png', '14_hasil_validasi.png'] as $file) {
        $public = public_path('docs/screenshots/'.$file);
        expect(is_file($public))->toBeTrue()
            ->and(file_get_contents($public))->toBe(file_get_contents(base_path('docs/screenshots/'.$file)));
        [$width, $height] = getimagesize($public);
        expect($width)->toBeGreaterThanOrEqual(500)->and($height)->toBeGreaterThanOrEqual(250);
    }
});

test('organizers can find create and manage event links in sidebar', function (): void {
    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $user = User::factory()->create(['kyc_status' => 'verified']);
    $user->assignRole('organizer');

    $this->actingAs($user)->get(route('panel.my-requests.index'))
        ->assertOk()->assertSee('Buat Event')->assertSee('Kelola Event Saya')
        ->assertSee('href="'.route('panel.organizer.events.create').'"', false)
        ->assertSee('href="'.route('panel.organizer.events.index').'"', false)
        ->assertDontSee('Ajukan Penyelenggara Event');
});
