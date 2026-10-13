<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\MasterData\Models\Venue;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web', 'panel_access' => 'external']);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->venue = Venue::query()->firstOrCreate(
        ['name' => 'GOR Pancasila'],
        [
            'address' => 'Jl. Pemuda No. 1',
            'city_id' => '3273',
            'is_active' => true,
        ]
    );
    $this->organizer = User::factory()->create([
        'email' => 'organizer_test@actstation.test',
        'is_active' => true,
    ]);
    $this->organizer->assignRole('organizer');

    $this->event = Event::create([
        'title' => 'Kejuaraan Olahraga Terbuka 2026',
        'slug' => 'kejuaraan-olahraga-terbuka-2026',
        'sport_category_id' => $this->sport->id,
        'venue_id' => $this->venue->id,
        'user_id' => $this->organizer->id,
        'status' => 'draft',
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(15),
    ]);
});

test('organizer dapat mengakses overview workspace event dan melihat section dokumen', function (): void {
    $response = $this->actingAs($this->organizer)->get(route('panel.organizer.events.manage', $this->event->id));

    $response->assertOk();
    $response->assertSee('Dokumen Legalitas &amp; Teknis Turnamen', false);
    $response->assertSee('+ Unggah Dokumen');
});

test('organizer dan admin dapat mengakses halaman daftar dokumen workspace event', function (): void {
    // Organizer
    $respOrganizer = $this->actingAs($this->organizer)->get(route('panel.organizer.documents.index', $this->event->id));
    $respOrganizer->assertOk();
    $respOrganizer->assertSee('Dokumen Turnamen:');
    $respOrganizer->assertSee('Technical Handbook');

    // Admin
    $admin = User::where('email', 'superadmin@actstation.test')->first();
    $respAdmin = $this->actingAs($admin)->get(route('admin.events.workspace.documents.index', $this->event->id));
    $respAdmin->assertOk();
    $respAdmin->assertSee('Dokumen Turnamen:');

    // Admin overview manage
    $respAdminManage = $this->actingAs($admin)->get(route('admin.events.workspace.manage', $this->event->id));
    $respAdminManage->assertOk();
    $respAdminManage->assertSee('Dokumen Legalitas &amp; Teknis Turnamen', false);
});

test('organizer dapat mengunggah dokumen rekomendasi cabor, izin polres, dan THB', function (): void {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('rekomendasi_cabor.pdf', 500, 'application/pdf');

    $response = $this->actingAs($this->organizer)->post(route('panel.organizer.documents.store', $this->event->id), [
        'category' => 'federation_recommendation',
        'name' => 'Surat Rekomendasi Cabor Pengprov',
        'document_number' => 'SR/001/2026',
        'file' => $file,
        'notes' => 'Surat rekomendasi resmi induk olahraga',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('documents', [
        'documentable_type' => Event::class,
        'documentable_id' => $this->event->id,
        'category' => 'federation_recommendation',
        'name' => 'Surat Rekomendasi Cabor Pengprov',
        'document_number' => 'SR/001/2026',
        'status' => 'pending',
    ]);
});

test('organizer dapat mengunduh dokumen yang telah diunggah', function (): void {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('thb.pdf', 200, 'application/pdf');
    $path = $file->store('event_documents', 'public');

    $document = $this->event->documents()->create([
        'category' => 'technical_handbook',
        'name' => 'Technical Handbook THB',
        'file_path' => $path,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->organizer)->get(route('panel.organizer.documents.download', [$this->event->id, $document->id]));

    $response->assertOk();
});

test('organizer dapat menghapus dokumen miliknya', function (): void {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('hapus.pdf', 200, 'application/pdf');
    $path = $file->store('event_documents', 'public');

    $document = $this->event->documents()->create([
        'category' => 'other',
        'name' => 'Dokumen Tambahan',
        'file_path' => $path,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->organizer)->delete(route('panel.organizer.documents.destroy', [$this->event->id, $document->id]));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('documents', [
        'id' => $document->id,
    ]);
});

test('organizer lain ditolak mengakses atau mengunggah dokumen ke event bukan miliknya', function (): void {
    $otherOrganizer = User::factory()->create(['is_active' => true]);
    $otherOrganizer->assignRole('organizer');

    $response = $this->actingAs($otherOrganizer)->get(route('panel.organizer.documents.index', $this->event->id));
    $response->assertForbidden();

    $responsePost = $this->actingAs($otherOrganizer)->post(route('panel.organizer.documents.store', $this->event->id), [
        'category' => 'other',
        'name' => 'Ilegal',
        'file' => UploadedFile::fake()->create('file.pdf', 100),
    ]);
    $responsePost->assertForbidden();
});

test('admin dapat memverifikasi dokumen event', function (): void {
    Storage::fake('public');

    $admin = User::where('email', 'superadmin@actstation.test')->first();

    $file = UploadedFile::fake()->create('izin_polres.pdf', 200, 'application/pdf');
    $path = $file->store('event_documents', 'public');

    $document = $this->event->documents()->create([
        'category' => 'police_permit',
        'name' => 'Izin Keramaian Polres',
        'file_path' => $path,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.events.workspace.documents.verify', [$this->event->id, $document->id]), [
        'notes' => 'Lolos verifikasi kepolisian',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $document->refresh();
    expect($document->status)->toBe('verified')
        ->and($document->verified_by)->toBe($admin->id);
});

test('organizer dapat menyetel opsi is_public pada dokumen dinamis dan THB default publik', function (): void {
    Storage::fake('public');

    // Dokumen dinamis dengan opsi publik
    $this->actingAs($this->organizer)->post(route('panel.organizer.documents.store', $this->event->id), [
        'category' => 'police_permit',
        'name' => 'Surat Izin Keramaian Polres',
        'document_number' => 'POL/01/2026',
        'is_public' => '1',
        'file' => UploadedFile::fake()->create('polres.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    $this->assertDatabaseHas('documents', [
        'category' => 'police_permit',
        'is_public' => true,
    ]);

    // Dokumen dinamis internal (is_public = 0)
    $this->actingAs($this->organizer)->post(route('panel.organizer.documents.store', $this->event->id), [
        'category' => 'other',
        'name' => 'Arsip Internal Panitia',
        'is_public' => '0',
        'file' => UploadedFile::fake()->create('internal.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    $this->assertDatabaseHas('documents', [
        'category' => 'other',
        'is_public' => false,
    ]);

    // Dokumen THB default publik
    $this->actingAs($this->organizer)->post(route('panel.organizer.documents.store', $this->event->id), [
        'category' => 'technical_handbook',
        'name' => 'Technical Handbook Resmi',
        'file' => UploadedFile::fake()->create('thb.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    $this->assertDatabaseHas('documents', [
        'category' => 'technical_handbook',
        'is_public' => true,
    ]);
});

test('organizer dan admin dapat toggle visibilitas publik dokumen event', function (): void {
    Storage::fake('public');
    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
    $path = $file->store('event_documents', 'public');

    $doc = $this->event->documents()->create([
        'category' => 'police_permit',
        'name' => 'Izin Keramaian',
        'file_path' => $path,
        'status' => 'pending',
        'is_public' => false,
    ]);

    // Organizer toggle ke publik
    $this->actingAs($this->organizer)
        ->post(route('panel.organizer.documents.togglePublic', [$this->event->id, $doc->id]))
        ->assertRedirect();

    expect($doc->fresh()->is_public)->toBeTrue();

    // Admin toggle kembali ke internal
    $admin = User::where('email', 'superadmin@actstation.test')->first();
    $this->actingAs($admin)
        ->post(route('admin.events.workspace.documents.togglePublic', [$this->event->id, $doc->id]))
        ->assertRedirect();

    expect($doc->fresh()->is_public)->toBeFalse();
});

test('pengunjung publik dapat mengunduh dokumen publik dan ditolak jika dokumen internal', function (): void {
    Storage::fake('public');
    $file = UploadedFile::fake()->create('panduan.pdf', 100, 'application/pdf');
    $path = $file->store('event_documents', 'public');

    $publicDoc = $this->event->documents()->create([
        'category' => 'technical_handbook',
        'name' => 'THB Publik',
        'file_path' => $path,
        'status' => 'verified',
        'is_public' => true,
    ]);

    $privateDoc = $this->event->documents()->create([
        'category' => 'other',
        'name' => 'Internal Dokumen',
        'file_path' => $path,
        'status' => 'pending',
        'is_public' => false,
    ]);

    // Publik dapat mengunduh dokumen dengan is_public = true
    $this->get(route('events.documents.download', [$this->event->slug, $publicDoc->id]))
        ->assertOk();

    // Dokumen dengan is_public = false ditolak 404 bagi publik
    $this->get(route('events.documents.download', [$this->event->slug, $privateDoc->id]))
        ->assertNotFound();
});

test('halaman publik menampilkan dokumen publik turnamen dan banner verifikasi', function (): void {
    Storage::fake('public');
    $file = UploadedFile::fake()->create('panduan.pdf', 100, 'application/pdf');
    $path = $file->store('event_documents', 'public');

    $this->event->update(['status' => 'published']);

    $this->event->documents()->create([
        'category' => 'technical_handbook',
        'name' => 'Panduan Teknis Resmi THB',
        'file_path' => $path,
        'status' => 'verified',
        'is_public' => true,
    ]);

    $response = $this->get(route('events.show', $this->event->slug));
    $response->assertOk();
    $response->assertSee('Panduan Teknis Resmi THB');
    $response->assertSee('Dokumen Resmi &amp; Teknis Turnamen', false);
});
