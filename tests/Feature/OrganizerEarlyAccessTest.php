<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['member', 'organizer'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'], ['panel_access' => 'external']);
    }
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web'], ['panel_access' => 'internal']);
    foreach (['user.view', 'user.update'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $this->reviewer = User::factory()->create(['is_active' => true]);
    $this->reviewer->assignRole('staff');
    $this->reviewer->givePermissionTo(['user.view', 'user.update']);
    $this->applicant = User::factory()->create(['is_active' => true]);
    $this->applicant->assignRole('member');
    Storage::fake('local');
    DB::table('wilayah')->insertOrIgnore([
        ['kode' => '32', 'nama' => 'Jawa Barat'],
        ['kode' => '32.73', 'nama' => 'Kota Bandung'],
        ['kode' => '32.73.01', 'nama' => 'Kecamatan Test'],
        ['kode' => '32.73.01.1001', 'nama' => 'Kelurahan Test'],
    ]);
    $this->payload = [
        'organizer_mode' => 'individual',
        'identity_number' => '3273012345678901',
        'identity_name' => 'Pemohon Awal',
        'birth_place' => 'Bandung',
        'birth_date' => '1995-05-15',
        'identity_occupation' => 'Wiraswasta',
        'identity_address' => 'Jalan Test 1',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'Pemohon Awal',
        'bank_branch' => 'Bandung',
        'province_id' => '32',
        'city_id' => '32.73',
        'subdistrict_id' => '32.73.01',
        'village_id' => '32.73.01.1001',
        'address' => 'Jalan Test 1',
        'emergency_contact' => '081234567890',
    ];
});

test('early access hides private organizer document section without disabling submission', function (): void {
    $response = $this->actingAs($this->applicant)->get(route('panel.organizer.apply'));
    $response->assertOk()->assertDontSee('Dokumen Verifikasi Penyelenggara (Privat)')
        ->assertDontSee('name="identity_selfie"', false)
        ->assertDontSee('name="responsibility_statement"', false)
        ->assertDontSee('name="bank_proof"', false)
        ->assertDontSee('name="authorization_letter"', false)
        ->assertSee('Kirim Pengajuan Penyelenggara');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $input = (new DOMXPath($dom))->query('//input[@name="identity_image"]')->item(0);
    expect($input)->not->toBeNull()
        ->and($input->hasAttribute('required'))->toBeFalse();
});

test('early access submits without uploads but grants no role until admin approval', function (): void {
    $this->actingAs($this->applicant)->post(route('panel.organizer.apply.store'), $this->payload)
        ->assertRedirect(route('panel.organizer.apply'))->assertSessionHasNoErrors();
    expect($this->applicant->fresh()->kyc_status)->toBe('pending')
        ->and($this->applicant->fresh()->hasRole('organizer'))->toBeFalse()
        ->and($this->applicant->documents()->count())->toBe(0);
    $this->get(route('panel.organizer.events.create'))->assertRedirect(route('panel.organizer.apply'));
    $this->post(route('panel.organizer.events.store'), [])->assertForbidden();
    $this->actingAs($this->reviewer)->get(route('admin.organizer-kyc.show', $this->applicant))
        ->assertOk()->assertDontSee('Persyaratan Verifikasi Belum Terpenuhi')
        ->assertSee('Upload dokumen organizer belum diwajibkan');
    $this->post(route('admin.organizer-kyc.approve', $this->applicant))
        ->assertRedirect(route('admin.organizer-kyc.show', $this->applicant))->assertSessionMissing('error');
    expect($this->applicant->fresh()->kyc_status)->toBe('verified')
        ->and($this->applicant->fresh()->hasRole('organizer'))->toBeTrue();
});

test('early access entity still requires verified owned legal evidence but not organizer mandate upload', function (): void {
    $community = Community::factory()->create(['subtype' => 'free_association', 'status' => 'verified', 'is_active' => true]);
    CommunityOfficial::create(['community_id' => $community->id, 'user_id' => $this->applicant->id, 'position' => 'manager', 'status' => 'active', 'started_at' => now()]);
    $payload = array_merge($this->payload, ['organizer_mode' => 'entity', 'community_id' => $community->id, 'bank_account_holder' => $community->name]);
    $this->actingAs($this->applicant)->post(route('panel.organizer.apply.store'), $payload)
        ->assertSessionHasErrors('community_id');
    Storage::disk('local')->put('legal/founding.pdf', 'fixture');
    $community->documents()->create(['category' => 'founding_statement', 'name' => 'Bukti Pendirian', 'file_path' => 'legal/founding.pdf', 'status' => 'verified', 'is_public' => false]);
    $this->post(route('panel.organizer.apply.store'), $payload)
        ->assertRedirect(route('panel.organizer.apply'))->assertSessionHasNoErrors();
    $this->actingAs($this->reviewer)->post(route('admin.organizer-kyc.approve', $this->applicant))
        ->assertSessionMissing('error');
    expect($this->applicant->fresh()->kyc_status)->toBe('verified');
});

test('early access optional files retain MIME validation and private storage', function (): void {
    $bad = array_merge($this->payload, ['documents' => ['identity_selfie' => UploadedFile::fake()->create('payload.php', 5, 'application/x-httpd-php')]]);
    $this->actingAs($this->applicant)->postJson(route('panel.organizer.apply.store'), $bad)
        ->assertUnprocessable()->assertJsonValidationErrors('documents.identity_selfie');
    $payload = array_merge($this->payload, ['bank_proof' => UploadedFile::fake()->create('bank.pdf', 100, 'application/pdf')]);
    $this->post(route('panel.organizer.apply.store'), $payload)->assertSessionHasNoErrors();
    $doc = $this->applicant->documents()->where('category', 'organizer_bank_proof')->firstOrFail();
    expect($doc->is_public)->toBeFalse()->and($doc->status)->toBe('pending');
    Storage::disk('local')->assertExists($doc->file_path);
});

test('strict document mode can be restored centrally', function (): void {
    config(['app.organizer_private_documents_required' => true]);
    $this->actingAs($this->applicant)->get(route('panel.organizer.apply'))
        ->assertOk()->assertSee('Dokumen Verifikasi Penyelenggara (Privat)');
    $this->post(route('panel.organizer.apply.store'), $this->payload)
        ->assertSessionHasErrors(['identity_image', 'identity_selfie', 'responsibility_statement', 'bank_proof']);
    $this->applicant->update(['kyc_status' => 'pending']);
    $this->actingAs($this->reviewer)->post(route('admin.organizer-kyc.approve', $this->applicant))
        ->assertSessionHas('error');
    expect($this->applicant->fresh()->kyc_status)->toBe('pending');
});
