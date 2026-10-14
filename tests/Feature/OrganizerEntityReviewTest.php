<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Event\Models\Event;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\MasterData\Models\Venue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    config(['app.organizer_private_documents_required' => true]);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web', 'panel_access' => 'internal']);
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'panel_access' => 'internal']);
    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web', 'panel_access' => 'external']);

    $permView = Permission::firstOrCreate(['name' => 'user.view', 'guard_name' => 'web']);
    $permUpdate = Permission::firstOrCreate(['name' => 'user.update', 'guard_name' => 'web']);
    $adminRole->givePermissionTo([$permView, $permUpdate]);

    Storage::fake('local');
    Storage::fake('public');

    DB::table('wilayah')->insertOrIgnore([
        ['kode' => '32', 'nama' => 'JAWA BARAT'],
        ['kode' => '32.73', 'nama' => 'KOTA BANDUNG'],
        ['kode' => '32.73.01', 'nama' => 'SUKASARI'],
        ['kode' => '32.73.01.1001', 'nama' => 'SARIJADI'],
    ]);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->venue = Venue::create([
        'name' => 'GOR Pajajaran',
        'address' => 'Jl. Pajajaran No. 37, Bandung',
        'is_active' => true,
    ]);

    $this->admin = User::factory()->create([
        'name' => 'Admin Moderator',
        'email' => 'admin.mod.'.uniqid().'@test.com',
        'is_active' => true,
    ]);
    $this->admin->assignRole('admin');

    $this->applicant = User::factory()->create([
        'name' => 'Budi Pemohon',
        'email' => 'budi.'.uniqid().'@test.com',
        'phone_number' => '081234567890',
        'is_active' => true,
    ]);
    $this->applicant->assignRole('member');

    $this->validBasePayload = [
        'identity_number' => '3273012345678901',
        'identity_name' => 'Budi Pemohon',
        'birth_place' => 'Bandung',
        'birth_date' => '1992-04-12',
        'identity_occupation' => 'Wiraswasta',
        'identity_address' => 'Jl. Sarijadi No. 5',
        'npwp_number' => '01.234.567.8-901.000',
        'npwp_name' => 'Budi Pemohon',
        'bank_name' => 'BCA',
        'bank_account_number' => '8887776665',
        'bank_account_holder' => 'Budi Pemohon',
        'bank_branch' => 'KCP Bandung',
        'province_id' => '32',
        'city_id' => '32.73',
        'subdistrict_id' => '32.73.01',
        'village_id' => '32.73.01.1001',
        'postal_code' => '40151',
        'address' => 'Jl. Sarijadi No. 5',
        'rt' => '01',
        'rw' => '02',
        'emergency_contact' => '081299887766',
    ];
});

test('organizer entity review shows institutional detail and downloads compatible legal category using canonical code', function (): void {
    $community = Community::factory()->create(['subtype' => 'institution', 'institution_type' => 'federation', 'status' => 'verified', 'is_active' => true]);
    CommunityOfficial::create(['community_id' => $community->id, 'user_id' => $this->applicant->id, 'position' => 'manager', 'status' => 'active', 'started_at' => now()]);
    Storage::disk('local')->put('community_documents/appointment.pdf', 'test document');
    $community->documents()->create(['category' => 'deed', 'name' => 'Akta Organisasi', 'file_path' => 'community_documents/appointment.pdf', 'status' => 'verified', 'is_public' => false]);
    $this->applicant->update(['organizer_community_id' => $community->id, 'kyc_status' => 'pending']);

    $this->actingAs($this->applicant)->get(route('panel.organizer.apply'))
        ->assertOk()->assertSee('Pengurus Federasi / Cabor');
    $this->actingAs($this->admin)->get(route('admin.organizer-kyc.show', $this->applicant))
        ->assertOk()->assertSee('Pengurus Federasi / Cabor')
        ->assertSee(route('admin.organizer-kyc.entity-documents.download', ['user' => $this->applicant, 'code' => 'legal_entity_proof']), false);
    $this->get(route('admin.organizer-kyc.entity-documents.download', ['user' => $this->applicant, 'code' => 'legal_entity_proof']))->assertOk();

    $community->update(['status' => 'rejected']);
    $this->get(route('admin.organizer-kyc.show', $this->applicant))->assertOk()->assertSee('Entitas belum terverifikasi');
});

test('bank mismatch individual rejected while entity mode valid', function (): void {
    // Individual mode: bank holder != PIC name -> fails validation
    $individualPayload = array_merge($this->validBasePayload, [
        'organizer_mode' => 'individual',
        'bank_account_holder' => 'PT Olahraga Juara', // Different name
        'identity_image' => UploadedFile::fake()->image('ktp.jpg'),
        'identity_selfie' => UploadedFile::fake()->image('selfie.jpg'),
        'responsibility_statement' => UploadedFile::fake()->create('sptjm.pdf', 500, 'application/pdf'),
        'bank_proof' => UploadedFile::fake()->create('buku_tabungan.pdf', 500, 'application/pdf'),
    ]);

    $response = $this->actingAs($this->applicant)
        ->post(route('panel.organizer.apply.store'), $individualPayload);

    $response->assertSessionHasErrors(['bank_account_holder']);

    // Entity mode: setup verified community for applicant
    $community = Community::create([
        'name' => 'Klub Basket Juara',
        'slug' => 'klub-basket-juara-'.uniqid(),
        'subtype' => 'club',
        'sport_category_id' => $this->sport->id,
        'province_id' => '32',
        'city_id' => '32.73',
        'status' => 'verified',
        'is_active' => true,
    ]);

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $this->applicant->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
    ]);

    // Create required verified legal documents on community
    Storage::disk('local')->put('community_documents/club_reg.pdf', 'fake pdf');
    Storage::disk('local')->put('community_documents/akta.pdf', 'fake pdf');

    DB::table('documents')->insert([
        [
            'documentable_type' => 'App\Modules\Community\Models\Community',
            'documentable_id' => $community->id,
            'category' => 'club_registration',
            'name' => 'Surat Pendaftaran Klub',
            'file_path' => 'community_documents/club_reg.pdf',
            'status' => 'verified',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'documentable_type' => 'App\Modules\Community\Models\Community',
            'documentable_id' => $community->id,
            'category' => 'legal_entity_proof',
            'name' => 'Akta Pendirian',
            'file_path' => 'community_documents/akta.pdf',
            'status' => 'verified',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $entityPayload = array_merge($this->validBasePayload, [
        'organizer_mode' => 'entity',
        'community_id' => $community->id,
        'bank_account_holder' => 'Klub Basket Juara', // Differs from PIC name Budi Pemohon
        'identity_image' => UploadedFile::fake()->image('ktp.jpg'),
        'identity_selfie' => UploadedFile::fake()->image('selfie.jpg'),
        'responsibility_statement' => UploadedFile::fake()->create('sptjm.pdf', 500, 'application/pdf'),
        'bank_proof' => UploadedFile::fake()->create('rekening_koran.pdf', 500, 'application/pdf'),
        'authorization_letter' => UploadedFile::fake()->create('surat_tugas.pdf', 500, 'application/pdf'),
    ]);

    $response2 = $this->actingAs($this->applicant)
        ->post(route('panel.organizer.apply.store'), $entityPayload);

    $response2->assertSessionDoesntHaveErrors();
    $response2->assertRedirect(route('panel.organizer.apply'));

    $fresh = $this->applicant->fresh();
    expect($fresh->kyc_status)->toBe('pending')
        ->and($fresh->organizer_community_id)->toBe($community->id)
        ->and($fresh->hasRole('organizer'))->toBeFalse(); // Not granted yet!
});

test('required documents per mode: entity mode requires authorization_letter', function (): void {
    $community = Community::create([
        'name' => 'Klub Garuda',
        'slug' => 'klub-garuda-'.uniqid(),
        'subtype' => 'club',
        'province_id' => '32',
        'city_id' => '32.73',
        'status' => 'verified',
        'is_active' => true,
    ]);

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $this->applicant->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
    ]);

    Storage::disk('local')->put('community_documents/club_reg.pdf', 'fake pdf');
    Storage::disk('local')->put('community_documents/akta.pdf', 'fake pdf');

    DB::table('documents')->insert([
        [
            'documentable_type' => 'App\Modules\Community\Models\Community',
            'documentable_id' => $community->id,
            'category' => 'club_registration',
            'name' => 'Surat Pendaftaran Klub',
            'file_path' => 'community_documents/club_reg.pdf',
            'status' => 'verified',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'documentable_type' => 'App\Modules\Community\Models\Community',
            'documentable_id' => $community->id,
            'category' => 'legal_entity_proof',
            'name' => 'Akta Pendirian',
            'file_path' => 'community_documents/akta.pdf',
            'status' => 'verified',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Entity payload without authorization_letter
    $payload = array_merge($this->validBasePayload, [
        'organizer_mode' => 'entity',
        'community_id' => $community->id,
        'identity_image' => UploadedFile::fake()->image('ktp.jpg'),
        'identity_selfie' => UploadedFile::fake()->image('selfie.jpg'),
        'responsibility_statement' => UploadedFile::fake()->create('sptjm.pdf', 500, 'application/pdf'),
        'bank_proof' => UploadedFile::fake()->create('bank.pdf', 500, 'application/pdf'),
    ]);

    $response = $this->actingAs($this->applicant)
        ->post(route('panel.organizer.apply.store'), $payload);

    $response->assertSessionHasErrors(['authorization_letter']);
});

test('forged or unverified community fails validation', function (): void {
    // Other user's community
    $otherUser = User::factory()->create();
    $otherCommunity = Community::create([
        'name' => 'Komunitas Lain',
        'slug' => 'komunitas-lain-'.uniqid(),
        'subtype' => 'club',
        'province_id' => '32',
        'city_id' => '32.73',
        'status' => 'verified',
        'is_active' => true,
    ]);
    CommunityOfficial::create([
        'community_id' => $otherCommunity->id,
        'user_id' => $otherUser->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
    ]);

    $payload = array_merge($this->validBasePayload, [
        'organizer_mode' => 'entity',
        'community_id' => $otherCommunity->id, // Forged ID
        'identity_image' => UploadedFile::fake()->image('ktp.jpg'),
        'identity_selfie' => UploadedFile::fake()->image('selfie.jpg'),
        'responsibility_statement' => UploadedFile::fake()->create('sptjm.pdf', 500, 'application/pdf'),
        'bank_proof' => UploadedFile::fake()->create('bank.pdf', 500, 'application/pdf'),
        'authorization_letter' => UploadedFile::fake()->create('surat.pdf', 500, 'application/pdf'),
    ]);

    $response = $this->actingAs($this->applicant)
        ->post(route('panel.organizer.apply.store'), $payload);

    $response->assertSessionHasErrors(['community_id']);
});

test('same applicant immutable pending submit blocked with 422', function (): void {
    $this->applicant->update([
        'kyc_status' => 'pending',
        'kyc_submitted_at' => now(),
    ]);

    $payload = array_merge($this->validBasePayload, [
        'organizer_mode' => 'individual',
        'identity_image' => UploadedFile::fake()->image('ktp.jpg'),
        'identity_selfie' => UploadedFile::fake()->image('selfie.jpg'),
        'responsibility_statement' => UploadedFile::fake()->create('sptjm.pdf', 500, 'application/pdf'),
        'bank_proof' => UploadedFile::fake()->create('bank.pdf', 500, 'application/pdf'),
    ]);

    $response = $this->actingAs($this->applicant)
        ->post(route('panel.organizer.apply.store'), $payload);

    $response->assertStatus(422);
});

test('admin approval grants organizer role and preserves athlete role without self-approval', function (): void {
    $this->applicant->assignRole('athlete');
    $this->applicant->update([
        'kyc_status' => 'pending',
        'kyc_submitted_at' => now(),
    ]);

    // Seed valid required organizer documents
    foreach (['identity_image', 'identity_selfie', 'responsibility_statement', 'bank_proof'] as $code) {
        $path = "organizer_kyc/{$this->applicant->id}/{$code}.jpg";
        Storage::disk('local')->put($path, 'fake document');
        DB::table('documents')->insert([
            'documentable_type' => 'App\Models\User',
            'documentable_id' => $this->applicant->id,
            'category' => "organizer_{$code}",
            'name' => $code,
            'file_path' => $path,
            'status' => 'pending',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Self approval attempt by admin
    $this->admin->update(['kyc_status' => 'pending']);
    $selfResponse = $this->actingAs($this->admin)
        ->post(route('admin.organizer-kyc.approve', $this->admin));
    $selfResponse->assertForbidden();

    // Normal approval
    $response = $this->actingAs($this->admin)
        ->post(route('admin.organizer-kyc.approve', $this->applicant));

    $response->assertRedirect(route('admin.organizer-kyc.show', $this->applicant));

    $fresh = $this->applicant->fresh();
    expect($fresh->kyc_status)->toBe('verified')
        ->and($fresh->kyc_reviewed_by)->toBe($this->admin->id)
        ->and($fresh->hasRole('organizer'))->toBeTrue()
        ->and($fresh->hasRole('athlete'))->toBeTrue() // Athlete role preserved!
        ->and($fresh->hasRole('member'))->toBeTrue();
});

test('admin rejection sets rejected status with notes and user can resubmit', function (): void {
    $this->applicant->update([
        'kyc_status' => 'pending',
        'kyc_submitted_at' => now(),
    ]);

    $rejectResponse = $this->actingAs($this->admin)
        ->post(route('admin.organizer-kyc.reject', $this->applicant), [
            'notes' => 'Foto KTP buram, mohon unggah ulang foto KTP yang jelas.',
        ]);

    $rejectResponse->assertRedirect(route('admin.organizer-kyc.show', $this->applicant));

    $fresh = $this->applicant->fresh();
    expect($fresh->kyc_status)->toBe('rejected')
        ->and($fresh->kyc_rejected_reason)->toBe('Foto KTP buram, mohon unggah ulang foto KTP yang jelas.')
        ->and($fresh->hasRole('organizer'))->toBeFalse();

    // User resubmits
    $resubmitPayload = array_merge($this->validBasePayload, [
        'organizer_mode' => 'individual',
        'identity_image' => UploadedFile::fake()->image('ktp_baru.jpg'),
        'identity_selfie' => UploadedFile::fake()->image('selfie.jpg'),
        'responsibility_statement' => UploadedFile::fake()->create('sptjm.pdf', 500, 'application/pdf'),
        'bank_proof' => UploadedFile::fake()->create('bank.pdf', 500, 'application/pdf'),
    ]);

    $resubmitResponse = $this->actingAs($this->applicant->fresh())
        ->post(route('panel.organizer.apply.store'), $resubmitPayload);

    $resubmitResponse->assertRedirect(route('panel.organizer.apply'));
    expect($this->applicant->fresh()->kyc_status)->toBe('pending');
});

test('guessed document returns 404 and proper prefix check', function (): void {
    // Save a document
    Storage::disk('local')->put('organizer_kyc/'.$this->applicant->id.'/ktp.jpg', 'fake image content');

    $docId = DB::table('documents')->insertGetId([
        'documentable_type' => 'App\Models\User',
        'documentable_id' => $this->applicant->id,
        'category' => 'organizer_identity_image',
        'name' => 'Foto KTP',
        'file_path' => 'organizer_kyc/'.$this->applicant->id.'/ktp.jpg',
        'status' => 'pending',
        'is_public' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Non-existent doc
    $r1 = $this->actingAs($this->admin)->get(route('admin.organizer-kyc.documents.download', [
        'user' => $this->applicant->id,
        'document' => 99999,
    ]));
    $r1->assertNotFound();

    // Doc belongs to another user
    $anotherUser = User::factory()->create();
    $r2 = $this->actingAs($this->admin)->get(route('admin.organizer-kyc.documents.download', [
        'user' => $anotherUser->id,
        'document' => $docId,
    ]));
    $r2->assertNotFound();

    // Non-organizer doc (e.g. athlete private doc)
    $athleteDocId = DB::table('documents')->insertGetId([
        'documentable_type' => 'App\Models\User',
        'documentable_id' => $this->applicant->id,
        'category' => 'athlete_birth_cert',
        'name' => 'Akta Lahir',
        'file_path' => 'athlete/'.$this->applicant->id.'/akta.pdf',
        'status' => 'verified',
        'is_public' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $r3 = $this->actingAs($this->admin)->get(route('admin.organizer-kyc.documents.download', [
        'user' => $this->applicant->id,
        'document' => $athleteDocId,
    ]));
    $r3->assertNotFound();
});

test('gates: pending with bank cannot GET or POST event create or request payout', function (): void {
    $this->applicant->assignRole('organizer');
    $this->applicant->update([
        'kyc_status' => 'pending',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'Budi Pemohon',
    ]);

    // GET create redirects to apply
    $getRes = $this->actingAs($this->applicant)
        ->get(route('panel.organizer.events.create'));
    $getRes->assertRedirect(route('panel.organizer.apply'));

    // POST create forbidden
    $postRes = $this->actingAs($this->applicant)
        ->post(route('panel.organizer.events.store'), [
            'title' => 'Turnamen Terlarang',
            'venue_id' => $this->venue->id,
            'starts_at' => now()->addDays(5)->toDateTimeString(),
            'ends_at' => now()->addDays(7)->toDateTimeString(),
        ]);
    $postRes->assertForbidden();

    // Payout request fails if not verified
    $event = Event::create([
        'sport_category_id' => $this->sport->id,
        'venue_id' => $this->venue->id,
        'user_id' => $this->applicant->id,
        'title' => 'Event Uji',
        'slug' => 'event-uji-'.uniqid(),
        'status' => 'ongoing',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $payoutRes = $this->actingAs($this->applicant)
        ->post(route('panel.organizer.payouts.request', $event), [
            'term' => 1,
        ]);
    $payoutRes->assertSessionHas('error');
});

test('legal reuse non-rejected files and block rejected files reuse', function (): void {
    // Applicant uploads initial docs
    $initialPayload = array_merge($this->validBasePayload, [
        'organizer_mode' => 'individual',
        'identity_image' => UploadedFile::fake()->image('ktp.jpg'),
        'identity_selfie' => UploadedFile::fake()->image('selfie.jpg'),
        'responsibility_statement' => UploadedFile::fake()->create('sptjm.pdf', 500, 'application/pdf'),
        'bank_proof' => UploadedFile::fake()->create('bank.pdf', 500, 'application/pdf'),
    ]);

    $this->actingAs($this->applicant)
        ->post(route('panel.organizer.apply.store'), $initialPayload);

    // Admin rejects with notes
    $this->actingAs($this->admin)
        ->post(route('admin.organizer-kyc.reject', $this->applicant), [
            'notes' => 'Perbaiki foto KTP.',
        ]);

    // Resubmission WITHOUT new files should fail because rejected documents cannot be reused
    $emptyFilesPayload = $this->validBasePayload;
    $emptyFilesPayload['organizer_mode'] = 'individual';

    $failRes = $this->actingAs($this->applicant->fresh())
        ->post(route('panel.organizer.apply.store'), $emptyFilesPayload);

    $failRes->assertSessionHasErrors(['identity_image', 'identity_selfie', 'responsibility_statement', 'bank_proof']);
});

test('activity log redacts sensitive PII and paths', function (): void {
    $attributes = [
        'identity_number' => '3273012345678901',
        'bank_account_number' => '1234567890',
        'kyc_rejected_reason' => 'Data sensitif alasan penolakan',
        'file_path' => 'organizer_kyc/1/ktp.jpg',
        'notes' => 'Catatan rahasia',
        'organizer_community_id' => 99,
        'name' => 'Budi Publik',
    ];

    $redacted = ActivityLog::redact($attributes);

    expect($redacted['identity_number'])->toBe(ActivityLog::REDACTED)
        ->and($redacted['bank_account_number'])->toBe(ActivityLog::REDACTED)
        ->and($redacted['kyc_rejected_reason'])->toBe(ActivityLog::REDACTED)
        ->and($redacted['file_path'])->toBe(ActivityLog::REDACTED)
        ->and($redacted['notes'])->toBe(ActivityLog::REDACTED)
        ->and($redacted['organizer_community_id'])->toBe(ActivityLog::REDACTED)
        ->and($redacted['name'])->toBe('Budi Publik');
});

test('admin kyc moderation index and show views with permission guard', function (): void {
    $this->applicant->update([
        'kyc_status' => 'pending',
        'kyc_submitted_at' => now(),
    ]);

    // User without user.view permission cannot access
    $unauthAdmin = User::factory()->create();
    $unauthAdmin->assignRole('member');

    $r403 = $this->actingAs($unauthAdmin)->get(route('admin.organizer-kyc.index'));
    $r403->assertForbidden();

    // Authorized admin can view index
    $rIndex = $this->actingAs($this->admin)->get(route('admin.organizer-kyc.index'));
    $rIndex->assertOk()
        ->assertSee('Pengajuan Penyelenggara')
        ->assertSee($this->applicant->name);

    // Authorized admin can view show
    $rShow = $this->actingAs($this->admin)->get(route('admin.organizer-kyc.show', $this->applicant));
    $rShow->assertOk()
        ->assertSee('Tinjau Pengajuan Penyelenggara')
        ->assertSee('Identitas PIC')
        ->assertSee('Setujui Pengajuan')
        ->assertSee('Tolak Pengajuan');
});

test('pending applicant missing required documents cannot be approved', function (): void {
    $this->applicant->update([
        'kyc_status' => 'pending',
        'kyc_submitted_at' => now(),
    ]);

    // Seed only 2 documents, omitting responsibility_statement and bank_proof
    foreach (['identity_image', 'identity_selfie'] as $code) {
        $path = "organizer_kyc/{$this->applicant->id}/{$code}.jpg";
        Storage::disk('local')->put($path, 'fake content');
        DB::table('documents')->insert([
            'documentable_type' => 'App\Models\User',
            'documentable_id' => $this->applicant->id,
            'category' => "organizer_{$code}",
            'name' => $code,
            'file_path' => $path,
            'status' => 'pending',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $response = $this->actingAs($this->admin)
        ->from(route('admin.organizer-kyc.show', $this->applicant))
        ->post(route('admin.organizer-kyc.approve', $this->applicant));

    $response->assertRedirect(route('admin.organizer-kyc.show', $this->applicant));
    $response->assertSessionHas('error');

    $fresh = $this->applicant->fresh();
    expect($fresh->kyc_status)->toBe('pending')
        ->and($fresh->hasRole('organizer'))->toBeFalse();
});

test('pending applicant with rejected document cannot be approved', function (): void {
    $this->applicant->update([
        'kyc_status' => 'pending',
        'kyc_submitted_at' => now(),
    ]);

    // Seed all 4 documents, but mark bank_proof as rejected
    foreach (['identity_image', 'identity_selfie', 'responsibility_statement', 'bank_proof'] as $code) {
        $path = "organizer_kyc/{$this->applicant->id}/{$code}.jpg";
        Storage::disk('local')->put($path, 'fake content');
        DB::table('documents')->insert([
            'documentable_type' => 'App\Models\User',
            'documentable_id' => $this->applicant->id,
            'category' => "organizer_{$code}",
            'name' => $code,
            'file_path' => $path,
            'status' => $code === 'bank_proof' ? 'rejected' : 'pending',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $response = $this->actingAs($this->admin)
        ->from(route('admin.organizer-kyc.show', $this->applicant))
        ->post(route('admin.organizer-kyc.approve', $this->applicant));

    $response->assertRedirect(route('admin.organizer-kyc.show', $this->applicant));
    $response->assertSessionHas('error');

    $fresh = $this->applicant->fresh();
    expect($fresh->kyc_status)->toBe('pending')
        ->and($fresh->hasRole('organizer'))->toBeFalse();
});

test('pending applicant with missing file on disk cannot be approved', function (): void {
    $this->applicant->update([
        'kyc_status' => 'pending',
        'kyc_submitted_at' => now(),
    ]);

    // Seed all 4 documents in DB, but do not write bank_proof to disk
    foreach (['identity_image', 'identity_selfie', 'responsibility_statement', 'bank_proof'] as $code) {
        $path = "organizer_kyc/{$this->applicant->id}/{$code}.jpg";
        if ($code !== 'bank_proof') {
            Storage::disk('local')->put($path, 'fake content');
        }
        DB::table('documents')->insert([
            'documentable_type' => 'App\Models\User',
            'documentable_id' => $this->applicant->id,
            'category' => "organizer_{$code}",
            'name' => $code,
            'file_path' => $path,
            'status' => 'pending',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $response = $this->actingAs($this->admin)
        ->from(route('admin.organizer-kyc.show', $this->applicant))
        ->post(route('admin.organizer-kyc.approve', $this->applicant));

    $response->assertRedirect(route('admin.organizer-kyc.show', $this->applicant));
    $response->assertSessionHas('error');

    $fresh = $this->applicant->fresh();
    expect($fresh->kyc_status)->toBe('pending')
        ->and($fresh->hasRole('organizer'))->toBeFalse();
});

test('entity mode applicant cannot be approved with withdrawn official or unverified legal doc or inactive community', function (): void {
    $community = Community::create([
        'name' => 'Klub Uji Entity',
        'slug' => 'klub-uji-entity-'.uniqid(),
        'subtype' => 'club',
        'province_id' => '32',
        'city_id' => '32.73',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $official = CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $this->applicant->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]);

    $this->applicant->update([
        'kyc_status' => 'pending',
        'organizer_community_id' => $community->id,
        'kyc_submitted_at' => now(),
    ]);

    // Seed all 5 organizer documents for applicant
    foreach (['identity_image', 'identity_selfie', 'responsibility_statement', 'bank_proof', 'authorization_letter'] as $code) {
        $path = "organizer_kyc/{$this->applicant->id}/{$code}.jpg";
        Storage::disk('local')->put($path, 'fake content');
        DB::table('documents')->insert([
            'documentable_type' => 'App\Models\User',
            'documentable_id' => $this->applicant->id,
            'category' => "organizer_{$code}",
            'name' => $code,
            'file_path' => $path,
            'status' => 'pending',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Case A: Community has no verified legal documents -> cannot approve
    $r1 = $this->actingAs($this->admin)
        ->from(route('admin.organizer-kyc.show', $this->applicant))
        ->post(route('admin.organizer-kyc.approve', $this->applicant));
    $r1->assertRedirect(route('admin.organizer-kyc.show', $this->applicant));
    $r1->assertSessionHas('error');
    expect($this->applicant->fresh()->hasRole('organizer'))->toBeFalse();

    // Now seed valid verified legal docs on community
    Storage::disk('local')->put('community_documents/club_reg.pdf', 'fake pdf');
    Storage::disk('local')->put('community_documents/akta.pdf', 'fake pdf');
    DB::table('documents')->insert([
        [
            'documentable_type' => 'App\Modules\Community\Models\Community',
            'documentable_id' => $community->id,
            'category' => 'club_registration',
            'name' => 'Surat Pendaftaran Klub',
            'file_path' => 'community_documents/club_reg.pdf',
            'status' => 'verified',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'documentable_type' => 'App\Modules\Community\Models\Community',
            'documentable_id' => $community->id,
            'category' => 'legal_entity_proof',
            'name' => 'Akta Pendirian',
            'file_path' => 'community_documents/akta.pdf',
            'status' => 'verified',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Case B: Official is withdrawn (ended_at set or inactive) -> cannot approve
    $official->update(['ended_at' => now()->subDay(), 'status' => 'inactive']);
    $r2 = $this->actingAs($this->admin)
        ->from(route('admin.organizer-kyc.show', $this->applicant))
        ->post(route('admin.organizer-kyc.approve', $this->applicant));
    $r2->assertRedirect(route('admin.organizer-kyc.show', $this->applicant));
    $r2->assertSessionHas('error');
    expect($this->applicant->fresh()->hasRole('organizer'))->toBeFalse();

    // Reset official to active, but Case C: Community is inactive -> cannot approve
    $official->update(['ended_at' => null, 'status' => 'active']);
    $community->update(['is_active' => false]);
    $r3 = $this->actingAs($this->admin)
        ->from(route('admin.organizer-kyc.show', $this->applicant))
        ->post(route('admin.organizer-kyc.approve', $this->applicant));
    $r3->assertRedirect(route('admin.organizer-kyc.show', $this->applicant));
    $r3->assertSessionHas('error');
    expect($this->applicant->fresh()->hasRole('organizer'))->toBeFalse();

    // When community is active again -> approval succeeds!
    $community->update(['is_active' => true]);
    $r4 = $this->actingAs($this->admin)
        ->from(route('admin.organizer-kyc.show', $this->applicant))
        ->post(route('admin.organizer-kyc.approve', $this->applicant));
    $r4->assertRedirect(route('admin.organizer-kyc.show', $this->applicant));
    $r4->assertSessionHas('success');
    expect($this->applicant->fresh()->hasRole('organizer'))->toBeTrue();
});

test('nested document upload with PHP or wrong MIME is rejected with 422', function (): void {
    $payload = $this->validBasePayload;
    $payload['organizer_mode'] = 'individual';
    $payload['documents'] = [
        'identity_selfie' => UploadedFile::fake()->create('shell.php', 100, 'text/x-php'),
    ];

    $response = $this->actingAs($this->applicant)
        ->post(route('panel.organizer.apply.store'), $payload);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['documents.identity_selfie']);
});

test('array organizer_mode returns 422 without 500', function (): void {
    $payload = $this->validBasePayload;
    $payload['organizer_mode'] = ['invalid'];

    $response = $this->actingAs($this->applicant)
        ->postJson(route('panel.organizer.apply.store'), $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['organizer_mode']);
});

test('stale decisions and self actions are rejected', function (): void {
    // 1. Admin self-rejection is forbidden (403)
    $this->admin->update(['kyc_status' => 'pending']);
    $rSelfReject = $this->actingAs($this->admin)
        ->post(route('admin.organizer-kyc.reject', $this->admin), ['notes' => 'Self rejection']);
    $rSelfReject->assertForbidden();

    // 2. Already verified applicant cannot be approved again
    $this->applicant->update(['kyc_status' => 'verified']);
    $rApproveVerified = $this->actingAs($this->admin)
        ->from(route('admin.organizer-kyc.show', $this->applicant))
        ->post(route('admin.organizer-kyc.approve', $this->applicant));
    $rApproveVerified->assertRedirect(route('admin.organizer-kyc.show', $this->applicant));
    $rApproveVerified->assertSessionHas('error');

    // 3. Already verified applicant cannot be rejected
    $rRejectVerified = $this->actingAs($this->admin)
        ->from(route('admin.organizer-kyc.show', $this->applicant))
        ->post(route('admin.organizer-kyc.reject', $this->applicant), ['notes' => 'Late reject']);
    $rRejectVerified->assertRedirect(route('admin.organizer-kyc.show', $this->applicant));
    $rRejectVerified->assertSessionHas('error');

    // 4. Already rejected applicant cannot be approved
    $this->applicant->update(['kyc_status' => 'rejected']);
    $rApproveRejected = $this->actingAs($this->admin)
        ->from(route('admin.organizer-kyc.show', $this->applicant))
        ->post(route('admin.organizer-kyc.approve', $this->applicant));
    $rApproveRejected->assertRedirect(route('admin.organizer-kyc.show', $this->applicant));
    $rApproveRejected->assertSessionHas('error');
});
