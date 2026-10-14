<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\DocumentType;
use App\Modules\MasterData\Models\SportCategory;
use Carbon\Carbon;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web', 'panel_access' => 'both']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'panel_access' => 'internal']);
    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web', 'panel_access' => 'external']);

    Permission::firstOrCreate(['name' => 'document-type.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'document-type.create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'document-type.update', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'document-type.delete', 'guard_name' => 'web']);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->devLevel = DevelopmentLevel::query()->firstOrCreate(
        ['name' => 'Remaja (13-16)', 'sport_category_id' => $this->sport->id],
        ['min_age' => 13, 'max_age' => 16, 'sort_order' => 2]
    );
    $this->seed(DocumentTypeSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('superadmin');
});

test('admin dapat melakukan CRUD pada master jenis dokumen', function (): void {
    // 1. Index
    $this->actingAs($this->admin)
        ->get(route('admin.document-types.index'))
        ->assertOk()
        ->assertSee('Jenis Dokumen');

    // 2. Store
    $response = $this->actingAs($this->admin)
        ->post(route('admin.document-types.store'), [
            'code' => 'test_medical_cert',
            'name' => 'Surat Keterangan Sehat Dokter',
            'scope' => 'athlete',
            'applicable_entities' => ['club', 'school'],
            'description' => 'Wajib dari faskes terakreditasi.',
            'sort_order' => 99,
            'is_active' => 1,
        ]);

    $docType = DocumentType::where('code', 'test_medical_cert')->first();
    expect($docType)->not->toBeNull()
        ->and($docType->name)->toBe('Surat Keterangan Sehat Dokter')
        ->and($docType->scope)->toBe('athlete')
        ->and($docType->applicable_entities)->toContain('club');

    $response->assertRedirect(route('admin.document-types.index'));

    // 3. Update
    $this->actingAs($this->admin)
        ->put(route('admin.document-types.update', $docType), [
            'code' => 'test_medical_cert',
            'name' => 'Surat Bebas Narkoba & Sehat',
            'scope' => 'athlete',
            'applicable_entities' => ['institution'],
            'is_active' => 1,
            'sort_order' => 10,
        ]);

    expect($docType->fresh()->name)->toBe('Surat Bebas Narkoba & Sehat');

    // 4. Delete
    $this->actingAs($this->admin)
        ->delete(route('admin.document-types.destroy', $docType))
        ->assertRedirect(route('admin.document-types.index'));

    expect(DocumentType::where('code', 'test_medical_cert')->exists())->toBeFalse();
});

test('organizer dapat menyetel required_documents per kategori (Instansi: BPJS & JMO, Pelajar: Kartu Pelajar & Raport)', function (): void {
    $organizer = User::factory()->create(['kyc_status' => 'verified', 'bank_name' => 'BCA', 'bank_account_number' => '123']);
    $organizer->assignRole('organizer');

    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $organizer->id,
        'status' => 'draft',
    ]);

    // Tambah Kategori Instansi dengan dokumen bpjs_tk dan jmo_screenshot
    $this->actingAs($organizer)
        ->post(route('panel.organizer.categories.store', $event->id), [
            'name' => 'Kategori Antar Instansi & BUMN',
            'match_type' => '5x5',
            'gender' => 'male',
            'registration_flow' => 'screening_first',
            'required_documents' => ['bpjs_tk', 'jmo_screenshot', 'employee_card'],
        ]);

    $instansiCat = EventCategory::where('name', 'Kategori Antar Instansi & BUMN')->first();
    expect($instansiCat)->not->toBeNull()
        ->and($instansiCat->required_documents)->toBe(['bpjs_tk', 'jmo_screenshot', 'employee_card']);

    // Tambah Kategori Pelajar dengan dokumen student_card, report_card, birth_certificate
    $this->actingAs($organizer)
        ->post(route('panel.organizer.categories.store', $event->id), [
            'name' => 'Piala Pelajar SMP',
            'match_type' => '5x5',
            'gender' => 'mixed',
            'registration_flow' => 'screening_first',
            'required_documents' => ['student_card', 'report_card', 'birth_certificate'],
        ]);

    $pelajarCat = EventCategory::where('name', 'Piala Pelajar SMP')->first();
    expect($pelajarCat)->not->toBeNull()
        ->and($pelajarCat->required_documents)->toBe(['student_card', 'report_card', 'birth_certificate']);
});

test('pendaftaran roster menolak atlet tanpa dokumen wajib dan mengizinkan pakai ulang berkas vault yang tersedia', function (): void {
    Storage::fake('public');

    $organizer = User::factory()->create();
    $organizer->assignRole('organizer');

    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $organizer->id,
        'status' => 'published',
        'registration_open' => true,
    ]);

    $category = EventCategory::factory()->create([
        'event_id' => $event->id,
        'name' => 'Liga Perusahaan BUMN',
        'gender' => 'male',
        'min_roster' => 1,
        'max_roster' => 5,
        'required_documents' => ['bpjs_tk', 'jmo_screenshot'],
    ]);

    $manager = User::factory()->create();
    $manager->assignRole('community_manager');

    $community = Community::create([
        'name' => 'Tim Basket Bank Mandiri',
        'slug' => 'tim-basket-bank-mandiri',
        'subtype' => 'institution',
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
    ]);

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $manager->id,
        'position' => 'Manajer Tim',
        'status' => 'active',
        'started_at' => now(),
    ]);

    $athlete = User::factory()->create([
        'name' => 'Karyawan Doni',
        'gender' => 'male',
        'birth_date' => Carbon::now()->subYears(28)->format('Y-m-d'),
        'identity_number' => '3273999988887777',
    ]);
    $athlete->assignRole('athlete');

    CommunityAthlete::create([
        'community_id' => $community->id,
        'user_id' => $athlete->id,
        'jersey_number' => 10,
        'status' => 'active',
        'region_status' => 'verified',
        'joined_at' => now(),
    ]);

    // 1. Submit tanpa berkas bpjs & jmo -> Ditolak
    $respFail = $this->actingAs($manager)
        ->post(route('panel.community-events.store', $event->slug), [
            'event_category_id' => $category->id,
            'team_name' => 'Mandiri Squad A',
            'contact_person' => 'Budi',
            'contact_phone' => '081234567890',
            'members' => [
                [
                    'user_id' => $athlete->id,
                    'jersey_number' => 10,
                    'is_captain' => 1,
                    'position' => 'Guard',
                    'team_role' => 'player',
                ],
            ],
        ]);

    $respFail->assertSessionHas('error');
    expect(EventParticipant::where('team_name', 'Mandiri Squad A')->exists())->toBeFalse();

    // 2. Submit dengan upload berkas baru -> Berhasil dan tersimpan di Vault User
    $bpjsFile = UploadedFile::fake()->create('kartu_bpjs.pdf', 100);
    $jmoFile = UploadedFile::fake()->image('screenshot_jmo.png');

    $respSuccess = $this->actingAs($manager)
        ->post(route('panel.community-events.store', $event->slug), [
            'event_category_id' => $category->id,
            'team_name' => 'Mandiri Squad A',
            'contact_person' => 'Budi',
            'contact_phone' => '081234567890',
            'members' => [
                [
                    'user_id' => $athlete->id,
                    'jersey_number' => 10,
                    'is_captain' => 1,
                    'position' => 'Guard',
                    'team_role' => 'player',
                ],
            ],
            'athlete_documents' => [
                $athlete->id => [
                    'bpjs_tk' => $bpjsFile,
                    'jmo_screenshot' => $jmoFile,
                ],
            ],
        ]);

    $participant = EventParticipant::where('team_name', 'Mandiri Squad A')->first();
    expect($participant)->not->toBeNull();

    // Verifikasi Dokumen tersimpan di Vault User
    $vaultDocs = Document::where('documentable_type', User::class)
        ->where('documentable_id', $athlete->id)
        ->pluck('category')
        ->toArray();

    expect($vaultDocs)->toContain('bpjs_tk')
        ->and($vaultDocs)->toContain('jmo_screenshot');

    // 3. Event Kedua di masa mendatang dengan syarat BPJS yang sama:
    // Atlet tidak perlu upload ulang lagi! Dokumen vault otomatis dipakai!
    $event2 = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $organizer->id,
        'status' => 'published',
        'registration_open' => true,
    ]);

    $category2 = EventCategory::factory()->create([
        'event_id' => $event2->id,
        'name' => 'Turnamen Antar BUMN Season 2',
        'gender' => 'male',
        'min_roster' => 1,
        'max_roster' => 5,
        'required_documents' => ['bpjs_tk'], // Syarat BPJS
    ]);

    $respEvent2 = $this->actingAs($manager)
        ->post(route('panel.community-events.store', $event2->slug), [
            'event_category_id' => $category2->id,
            'team_name' => 'Mandiri Squad B (Season 2)',
            'contact_person' => 'Budi',
            'contact_phone' => '081234567890',
            'members' => [
                [
                    'user_id' => $athlete->id,
                    'jersey_number' => 10,
                    'is_captain' => 1,
                    'position' => 'Guard',
                    'team_role' => 'player',
                ],
            ],
            // Tanpa upload berkas sama sekali karena sudah ada di vault!
        ]);

    $participant2 = EventParticipant::where('team_name', 'Mandiri Squad B (Season 2)')->first();
    expect($participant2)->not->toBeNull();
});

test('alur pendaftaran dinamis per kategori (screening_first vs payment_first)', function (): void {
    $organizer = User::factory()->create();
    $organizer->assignRole('organizer');

    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $organizer->id,
        'status' => 'published',
        'registration_open' => true,
    ]);

    // Kategori 1: screening_first (Kurasi berkas dulu, bayar setelah diverifikasi panitia)
    $catScreening = EventCategory::factory()->create([
        'event_id' => $event->id,
        'name' => 'SMP Putra (Screening First)',
        'gender' => 'male',
        'min_roster' => 1,
        'max_roster' => 5,
        'registration_flow' => 'screening_first',
        'registration_fee' => 500000,
        'payment_time_limit_hours' => 24,
    ]);

    // Kategori 2: payment_first (Langsung bayar kunci slot dulu)
    $catPayment = EventCategory::factory()->create([
        'event_id' => $event->id,
        'name' => '3x3 Open (Payment First)',
        'gender' => 'male',
        'min_roster' => 1,
        'max_roster' => 5,
        'registration_flow' => 'payment_first',
        'registration_fee' => 500000,
        'registration_start' => now()->subDay(),
        'payment_start' => now()->subHour(),
        'registration_end' => now()->addDays(3),
        'payment_end' => now()->addDays(3),
    ]);

    $manager = User::factory()->create();
    $manager->assignRole('community_manager');

    $community = Community::create([
        'name' => 'SMPN 1 Bandung',
        'slug' => 'smpn-1-bandung',
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
    ]);

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $manager->id,
        'position' => 'Guru Olahraga',
        'status' => 'active',
        'started_at' => now(),
    ]);

    $student = User::factory()->create([
        'name' => 'Siswa SMP 1',
        'gender' => 'male',
        'birth_date' => Carbon::now()->subYears(14)->format('Y-m-d'),
        'identity_number' => '3273111199990001',
    ]);
    $student->assignRole('athlete');

    CommunityAthlete::create([
        'community_id' => $community->id,
        'user_id' => $student->id,
        'jersey_number' => 4,
        'status' => 'active',
        'region_status' => 'verified',
        'joined_at' => now(),
    ]);

    // 1. Daftar di Kategori 1 (screening_first):
    // Status awal harus registered, belum bisa bayar
    $this->actingAs($manager)
        ->post(route('panel.community-events.store', $event->slug), [
            'event_category_id' => $catScreening->id,
            'team_name' => 'SMPN 1 Bandung A',
            'contact_person' => 'Guru',
            'contact_phone' => '081234567890',
            'members' => [
                [
                    'user_id' => $student->id,
                    'jersey_number' => 4,
                    'is_captain' => 1,
                    'position' => 'Guard',
                    'team_role' => 'player',
                ],
            ],
        ]);

    $partScreening = EventParticipant::where('team_name', 'SMPN 1 Bandung A')->first();
    expect($partScreening)->not->toBeNull()
        ->and($partScreening->status)->toBe('registered');

    // Cek checkout: Belum bisa bayar, di-redirect ke roster dengan notice
    $this->actingAs($manager)
        ->get(route('panel.community-events.checkout', $partScreening->id))
        ->assertRedirect(route('panel.community-events.roster', $partScreening->id));

    // Panitia memverifikasi berkas di panel organizer:
    // Status beralih ke pending_payment & invoice aktif
    $this->actingAs($organizer)
        ->post(route('panel.organizer.teams.verify', [$event->id, $partScreening->id]));

    $verifiedPart = $partScreening->fresh();
    expect($verifiedPart->status)->toBe('pending_payment')
        ->and($verifiedPart->payment_deadline_at)->not->toBeNull();

    // Sekarang tombol checkout aktif dan bisa bayar
    $this->actingAs($manager)
        ->get(route('panel.community-events.checkout', $partScreening->id))
        ->assertOk();

    // 2. Daftar di Kategori 2 (payment_first):
    // Status awal langsung pending_payment & langsung ke kasir checkout
    $respPayment = $this->actingAs($manager)
        ->post(route('panel.community-events.store', $event->slug), [
            'event_category_id' => $catPayment->id,
            'team_name' => 'SMPN 1 Bandung 3x3',
            'contact_person' => 'Guru',
            'contact_phone' => '081234567890',
            'members' => [
                [
                    'user_id' => $student->id,
                    'jersey_number' => 4,
                    'is_captain' => 1,
                    'position' => 'Guard',
                    'team_role' => 'player',
                ],
            ],
        ]);

    $partPayment = EventParticipant::where('team_name', 'SMPN 1 Bandung 3x3')->first();
    expect($partPayment)->not->toBeNull()
        ->and($partPayment->status)->toBe('pending_payment');

    $respPayment->assertRedirect(route('panel.community-events.checkout', $partPayment->id));
});
