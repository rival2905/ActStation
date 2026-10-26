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

test('public guide includes screenshot based video for first time event participants', function (): void {
    $this->get(route('panduan.event'))->assertOk()
        ->assertSee('id="peserta-baru"', false)
        ->assertSee('pendaftaran-awal.mp4')->assertSee('pendaftaran-awal.vtt')
        ->assertSee('Daftar Tim pada Kategori Ini')->assertSee('Daftar Akun')
        ->assertSee('controls', false)->assertSee('playsinline', false)
        ->assertSee('preload="none"', false);
    foreach (['15_event_mulai.png', '16_login_peserta.png', '17_daftar_akun.png', '18_buat_komunitas.png'] as $file) {
        expect(is_file(public_path('docs/screenshots/'.$file)))->toBeTrue();
        [$width, $height] = getimagesize(public_path('docs/screenshots/'.$file));
        expect($width)->toBe(1280)->and($height)->toBe(900);
    }
    expect(is_file(public_path('docs/videos/pendaftaran-awal.mp4')))->toBeTrue()
        ->and(file_get_contents(public_path('docs/videos/pendaftaran-awal.vtt')))->toStartWith('WEBVTT');
});

test('team guide documents current draft withdrawal and post submission steps with screenshots', function (): void {
    $this->get(route('panduan.event'))->assertOk()
        ->assertSee('Pendaftaran Sedang Berjalan')
        ->assertSee('Simpan &amp; Lengkapi Tim', false)
        ->assertSee('Tarik Pengajuan ke Draf')
        ->assertSee('Langkah Berikutnya')
        ->assertSee('belum memakai slot peserta')
        ->assertSee('tautan undangan lama tidak aktif kembali')
        ->assertSee('7 hari')
        ->assertSee('Kategori gratis tidak memiliki tahap pembayaran.');
    foreach ([
        '19_tim_identitas.png', '20_tim_roster.png', '21_tim_tinjau.png',
        '22_tim_langkah_berikutnya.png', '23_tim_revisi.png', '24_tim_pembayaran.png', '25_tim_terkonfirmasi.png',
    ] as $file) {
        $this->get(route('panduan.event'))->assertSee($file);
        expect(is_file(public_path('docs/screenshots/'.$file)))->toBeTrue();
        [$width, $height] = getimagesize(public_path('docs/screenshots/'.$file));
        expect($width)->toBeGreaterThanOrEqual(800)->and($height)->toBeGreaterThanOrEqual(150);
    }
});

test('public guide covers empty roster athlete invitations personnel gates and self service documents', function (): void {
    $this->get(route('panduan.event'))->assertOk()
        ->assertSee('id="undangan-atlet"', false)->assertSee('id="undangan-personel"', false)->assertSee('id="berkas-atlet"', false)
        ->assertSee('Belum ada atlet di roster tim ini')->assertSee('Undang Atlet ke Tim Ini')
        ->assertSee('Link Roster Atlet')->assertSee('Salin Link')->assertSee('bukan link keanggotaan global')
        ->assertSee('Periksa Kesesuaian')->assertSee('Periksa Akun')->assertSee('Masuk &amp; Lanjutkan', false)
        ->assertSee('Setujui Keanggotaan')->assertSee('Turnamen Saya')->assertSee('2 MB')
        ->assertSee('foto profil publik')->assertSee('konfirmasi sebelum pengiriman')
        ->assertSee('tidak otomatis menjadi atlet')->assertSee('bukan panitia event')
        ->assertDontSee('Buka <strong>Ikuti Turnamen</strong>', false);
    $text = file_get_contents(base_path('PANDUAN_OPERASIONAL_EVENT.md'));
    foreach (['Belum ada atlet di roster tim ini', 'Periksa Akun', 'Turnamen Saya', '2 MB', 'konfirmasi sebelum pengiriman'] as $term) {
        expect($text)->toContain($term);
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
