<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\CommunityLookup;
use App\Contracts\DTOs\CommunitySummary;
use App\Contracts\EntityRequirements;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\Document;
use App\Modules\Community\Services\CommunityLifecycle;
use Database\Seeders\RolePermissionSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('admin');

    Storage::fake('local');
});

test('canonical EntityRequirements constants dan legalDocumentCodes sesuai spesifikasi', function (): void {
    expect(EntityRequirements::SUBTYPES)->toHaveKeys(['club', 'school', 'university', 'institution', 'free_association'])
        ->and(EntityRequirements::INSTITUTION_TYPES)->toHaveKeys(['federation', 'government', 'corporate', 'foundation', 'association'])
        ->and(EntityRequirements::DOCUMENT_LABELS)->toHaveKeys([
            'club_registration',
            'legal_entity_proof',
            'institution_decree',
            'founding_statement',
            'appointment_decree',
            'business_registration',
        ]);

    expect(EntityRequirements::legalDocumentCodes('club'))->toBe(['club_registration', 'legal_entity_proof'])
        ->and(EntityRequirements::legalDocumentCodes('school'))->toBe(['institution_decree'])
        ->and(EntityRequirements::legalDocumentCodes('university'))->toBe(['institution_decree'])
        ->and(EntityRequirements::legalDocumentCodes('free_association'))->toBe(['founding_statement'])
        ->and(EntityRequirements::legalDocumentCodes('institution', 'federation'))->toBe(['appointment_decree'])
        ->and(EntityRequirements::legalDocumentCodes('institution', 'government'))->toBe(['institution_decree'])
        ->and(EntityRequirements::legalDocumentCodes('institution', 'corporate'))->toBe(['legal_entity_proof', 'business_registration'])
        ->and(EntityRequirements::legalDocumentCodes('institution', 'foundation'))->toBe(['legal_entity_proof'])
        ->and(EntityRequirements::legalDocumentCodes('institution', 'association'))->toBe(['founding_statement'])
        ->and(EntityRequirements::legalDocumentCodes('institution', null))->toBe([]);
});

test('community summary resolves school level from registered NPSN without guessing names', function (): void {
    DB::table('schools')->insert([
        'npsn' => '99887766', 'name' => 'Sekolah Fixture', 'school_level' => 'smk', 'school_status' => 'negeri', 'province_id' => '32', 'city_id' => '32.73', 'dataset_year' => 2026, 'source' => 'test',
    ]);
    $community = Community::factory()->create(['subtype' => 'school', 'npsn' => '99887766']);
    $lookup = app(CommunityLookup::class);
    expect($lookup->find($community->id)->school_level)->toBe('smk');
    $community->update(['npsn' => '99887765']);
    expect($lookup->find($community->id)->school_level)->toBeNull();
    $community->update(['subtype' => 'club', 'npsn' => '99887766']);
    expect($lookup->find($community->id)->school_level)->toBeNull();
});

test('CommunitySummary DTO mendukung trailing institution_type', function (): void {
    $summary = new CommunitySummary(
        id: 1,
        name: 'Test Club',
        slug: 'test-club',
        status: 'verified',
        subtype: 'institution',
        sport_category_id: 1,
        city_id: '3273',
        institution_type: 'federation',
    );

    expect($summary->institution_type)->toBe('federation');

    // Constructor without trailing param defaults to null
    $legacySummary = new CommunitySummary(
        id: 2,
        name: 'Legacy Club',
        slug: 'legacy-club',
        status: 'verified',
        subtype: 'club',
        sport_category_id: null,
        city_id: null,
    );

    expect($legacySummary->institution_type)->toBeNull();
});

test('CommunityLookup legalDocumentsForUser mengembalikan dokumen legal terverifikasi untuk pengurus aktif', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $community = Community::factory()->create([
        'subtype' => 'club',
        'status' => 'verified',
        'is_active' => true,
    ]);

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $user->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]);

    // Store private local files
    $pathReg = UploadedFile::fake()->create('reg.pdf', 100)->store('community_documents', 'local');
    $pathEntity = UploadedFile::fake()->create('entity.pdf', 100)->store('community_documents', 'local');

    $docReg = Document::create([
        'documentable_type' => Community::class,
        'documentable_id' => $community->id,
        'category' => 'club_registration',
        'name' => 'Tanda Registrasi Klub',
        'file_path' => $pathReg,
        'status' => 'verified',
        'is_public' => false,
    ]);

    $docEntity = Document::create([
        'documentable_type' => Community::class,
        'documentable_id' => $community->id,
        'category' => 'legal_entity_proof',
        'name' => 'Bukti Badan Hukum',
        'file_path' => $pathEntity,
        'status' => 'verified',
        'is_public' => false,
    ]);

    /** @var CommunityLookup $lookup */
    $lookup = app(CommunityLookup::class);
    $docs = $lookup->legalDocumentsForUser($user->id, $community->id);

    expect($docs)->toHaveKey('club_registration')
        ->and($docs)->toHaveKey('legal_entity_proof')
        ->and($docs['club_registration'])->toMatchArray([
            'id' => $docReg->id,
            'category' => 'club_registration',
            'name' => 'Tanda Registrasi Klub',
        ]);
});

test('community summary includes inactive status and legal reuse includes additional verified categories', function (): void {
    $user = User::factory()->create();
    $community = Community::factory()->create(['subtype' => 'free_association', 'status' => 'verified', 'is_active' => true]);
    CommunityOfficial::create(['community_id' => $community->id, 'user_id' => $user->id, 'position' => 'manager', 'status' => 'active', 'started_at' => now(), 'ended_at' => null]);
    $path = UploadedFile::fake()->create('additional.pdf', 100)->store('community_documents', 'local');
    $community->documents()->create(['category' => 'legal_entity_proof', 'name' => 'Legalitas tambahan', 'file_path' => $path, 'status' => 'verified', 'is_public' => false]);
    $lookup = app(CommunityLookup::class);
    expect($lookup->legalDocumentsForUser($user->id, $community->id))->toHaveKey('legal_entity_proof');
    expect($lookup->legalDocumentDownloadForUser($user->id, $community->id, 'legal_entity_proof'))->toBeInstanceOf(StreamedResponse::class);
    $community->update(['is_active' => false]);
    expect($lookup->find($community->id)->is_active)->toBeFalse()
        ->and($lookup->findForUser($user->id))->toBeNull()
        ->and($lookup->legalDocumentsForUser($user->id, $community->id))->toBe([]);
});

test('CommunityLookup legalDocumentsForUser mendukung compatible aliases', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $community = Community::factory()->create([
        'subtype' => 'school',
        'npsn' => '20199990',
        'status' => 'verified',
        'is_active' => true,
    ]);

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $user->id,
        'position' => 'headmaster',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]);

    $pathDecree = UploadedFile::fake()->create('decree.pdf', 100)->store('community_documents', 'local');

    // Stored with legacy alias category 'decree'
    $doc = Document::create([
        'documentable_type' => Community::class,
        'documentable_id' => $community->id,
        'category' => 'decree',
        'name' => 'SK Sekolah',
        'file_path' => $pathDecree,
        'status' => 'verified',
        'is_public' => false,
    ]);

    /** @var CommunityLookup $lookup */
    $lookup = app(CommunityLookup::class);
    $docs = $lookup->legalDocumentsForUser($user->id, $community->id);

    expect($docs)->toHaveKey('institution_decree')
        ->and($docs['institution_decree']['id'])->toBe($doc->id);
});

test('CommunityLookup legalDocumentsForUser menolak akses jika bukan pengurus aktif atau komunitas belum terverifikasi', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $stranger = User::factory()->create(['is_active' => true]);

    $communityPending = Community::factory()->create([
        'subtype' => 'club',
        'status' => 'pending',
        'is_active' => true,
    ]);

    CommunityOfficial::create([
        'community_id' => $communityPending->id,
        'user_id' => $user->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]);

    /** @var CommunityLookup $lookup */
    $lookup = app(CommunityLookup::class);

    // Unverified community returns empty array
    expect($lookup->legalDocumentsForUser($user->id, $communityPending->id))->toBe([]);

    $communityVerified = Community::factory()->create([
        'subtype' => 'club',
        'status' => 'verified',
        'is_active' => true,
    ]);

    CommunityOfficial::create([
        'community_id' => $communityVerified->id,
        'user_id' => $user->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->subYear()->toDateString(),
        'ended_at' => now()->subDay()->toDateString(), // Ended official!
    ]);

    // Ended official returns empty
    expect($lookup->legalDocumentsForUser($user->id, $communityVerified->id))->toBe([]);
    // Stranger returns empty
    expect($lookup->legalDocumentsForUser($stranger->id, $communityVerified->id))->toBe([]);
});

test('CommunityLookup legalDocumentDownloadForUser mengembalikan StreamedResponse dan memvalidasi otorisasi', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $stranger = User::factory()->create(['is_active' => true]);

    $community = Community::factory()->create([
        'subtype' => 'club',
        'status' => 'verified',
        'is_active' => true,
    ]);

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $user->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]);

    $pathReg = UploadedFile::fake()->create('reg.pdf', 100)->store('community_documents', 'local');

    Document::create([
        'documentable_type' => Community::class,
        'documentable_id' => $community->id,
        'category' => 'club_registration',
        'name' => 'Tanda Registrasi Klub',
        'file_path' => $pathReg,
        'status' => 'verified',
        'is_public' => false,
    ]);

    /** @var CommunityLookup $lookup */
    $lookup = app(CommunityLookup::class);

    $response = $lookup->legalDocumentDownloadForUser($user->id, $community->id, 'club_registration');
    expect($response)->toBeInstanceOf(StreamedResponse::class);

    // Stranger -> 403
    expect(fn () => $lookup->legalDocumentDownloadForUser($stranger->id, $community->id, 'club_registration'))
        ->toThrow(HttpException::class);

    // Invalid code -> 404
    expect(fn () => $lookup->legalDocumentDownloadForUser($user->id, $community->id, 'non_existent_code'))
        ->toThrow(HttpException::class);
});

test('CommunityLifecycle submit mewajibkan dokumen legal canonical dan file fisik', function (): void {
    $community = Community::factory()->create([
        'subtype' => 'club',
        'status' => 'draft',
    ]);

    $lifecycle = new CommunityLifecycle;

    // Submitting without documents throws InvalidArgumentException
    expect(fn () => $lifecycle->submit($community))
        ->toThrow(InvalidArgumentException::class);

    // Upload first doc
    $path1 = UploadedFile::fake()->create('reg.pdf', 100)->store('community_documents', 'local');
    Document::create([
        'documentable_type' => Community::class,
        'documentable_id' => $community->id,
        'category' => 'club_registration',
        'name' => 'Reg Doc',
        'file_path' => $path1,
        'status' => 'pending',
    ]);

    // Still missing legal_entity_proof
    expect(fn () => $lifecycle->submit($community))
        ->toThrow(InvalidArgumentException::class);

    // Upload second doc
    $path2 = UploadedFile::fake()->create('proof.pdf', 100)->store('community_documents', 'local');
    Document::create([
        'documentable_type' => Community::class,
        'documentable_id' => $community->id,
        'category' => 'legal_entity_proof',
        'name' => 'Proof Doc',
        'file_path' => $path2,
        'status' => 'pending',
    ]);

    // Now submit succeeds
    $lifecycle->submit($community);
    expect($community->status)->toBe('pending');
});

test('CommunityLifecycle verify mewajibkan dokumen legal berstatus verified', function (): void {
    $community = Community::factory()->create([
        'subtype' => 'school',
        'npsn' => '20199995',
        'status' => 'pending',
    ]);

    $path = UploadedFile::fake()->create('decree.pdf', 100)->store('community_documents', 'local');
    $doc = Document::create([
        'documentable_type' => Community::class,
        'documentable_id' => $community->id,
        'category' => 'institution_decree',
        'name' => 'SK Sekolah',
        'file_path' => $path,
        'status' => 'pending', // Not yet verified
    ]);

    $lifecycle = new CommunityLifecycle;

    // Verify should fail when doc is pending
    expect(fn () => $lifecycle->verify($community, $this->admin->id))
        ->toThrow(DomainException::class);

    $doc->update(['status' => 'verified']);

    $lifecycle->verify($community, $this->admin->id);
    expect($community->status)->toBe('verified');
});

test('komunitas verified tidak dapat mengubah klasifikasi legalitas via panel profile', function (): void {
    DB::table('wilayah')->insertOrIgnore([
        ['kode' => '32', 'nama' => 'Jawa Barat'],
        ['kode' => '32.73', 'nama' => 'Kota Bandung'],
    ]);

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('community_manager');

    $community = Community::factory()->create([
        'subtype' => 'club',
        'status' => 'verified',
        'province_id' => '32',
        'city_id' => '32.73',
    ]);

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $user->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]);

    $response = $this->actingAs($user)
        ->from(route('panel.profile.show'))
        ->put(route('panel.profile.update'), [
            'name' => $community->name,
            'subtype' => 'school', // Trying to change subtype on verified community!
            'province_id' => '32',
            'city_id' => '32.73',
        ]);

    $response->assertSessionHasErrors('subtype');
});

test('komunitas pending tidak dapat mengubah klasifikasi legalitas dan UI mengunci kontrol', function (): void {
    DB::table('wilayah')->insertOrIgnore([
        ['kode' => '32', 'nama' => 'Jawa Barat'],
        ['kode' => '32.73', 'nama' => 'Kota Bandung'],
    ]);

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('community_manager');

    $community = Community::factory()->create([
        'subtype' => 'club',
        'status' => 'pending',
        'province_id' => '32',
        'city_id' => '32.73',
    ]);

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $user->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]);

    // 1. Forged subtype submission rejected
    $response = $this->actingAs($user)
        ->from(route('panel.profile.show'))
        ->put(route('panel.profile.update'), [
            'name' => $community->name,
            'subtype' => 'school',
            'province_id' => '32',
            'city_id' => '32.73',
        ]);

    $response->assertSessionHasErrors('subtype');
    expect($community->fresh()->subtype)->toBe('club');

    // 2. UI renders locked controls with hidden input
    $getResp = $this->actingAs($user)->get(route('panel.profile.show'));
    $getResp->assertOk();
    $getResp->assertSee('<input type="hidden" name="subtype" value="club">', false);
    $getResp->assertSee('Tipe terverifikasi / dalam peninjauan terkunci.');
});

test('legacy verified institution dengan subtype institution dan institution_type null tidak dapat mengubah detail instansi tanpa reject', function (): void {
    DB::table('wilayah')->insertOrIgnore([
        ['kode' => '32', 'nama' => 'Jawa Barat'],
        ['kode' => '32.73', 'nama' => 'Kota Bandung'],
    ]);

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('community_manager');

    $community = Community::factory()->create([
        'subtype' => 'institution',
        'institution_type' => null,
        'status' => 'verified',
        'province_id' => '32',
        'city_id' => '32.73',
    ]);

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $user->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]);

    // Attempting to set institution_type fails
    $response = $this->actingAs($user)
        ->from(route('panel.profile.show'))
        ->put(route('panel.profile.update'), [
            'name' => $community->name,
            'subtype' => 'institution',
            'institution_type' => 'corporate',
            'province_id' => '32',
            'city_id' => '32.73',
        ]);

    $response->assertSessionHasErrors('institution_type');
    expect($community->fresh()->institution_type)->toBeNull();

    // Normal profile update preserving null institution_type succeeds
    $validResponse = $this->actingAs($user)
        ->from(route('panel.profile.show'))
        ->put(route('panel.profile.update'), [
            'name' => 'Instansi Baru',
            'subtype' => 'institution',
            'institution_type' => null,
            'province_id' => '32',
            'city_id' => '32.73',
            'contact_person' => 'Budi Santoso',
        ]);

    $validResponse->assertSessionHasNoErrors();
    expect($community->fresh()->contact_person)->toBe('Budi Santoso');
});

test('dokumen legalitas wajib atau terverifikasi tidak dapat dihapus saat pending atau verified', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('community_manager');

    $community = Community::factory()->create([
        'subtype' => 'club',
        'status' => 'verified',
    ]);

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $user->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]);

    $path = UploadedFile::fake()->create('akta.pdf', 100)->store('community_documents', 'local');
    $doc = Document::create([
        'documentable_type' => Community::class,
        'documentable_id' => $community->id,
        'category' => 'club_registration',
        'name' => 'Tanda Daftar Klub',
        'file_path' => $path,
        'status' => 'verified',
    ]);

    // Panel manager cannot delete verified statutory document
    $response = $this->actingAs($user)->delete(route('panel.documents.destroy', $doc));
    $response->assertForbidden();

    expect(Storage::disk('local')->exists($path))->toBeTrue();
    $this->assertModelExists($doc);

    // Admin cannot delete verified statutory document while verified
    $adminResponse = $this->actingAs($this->admin)->delete(route('admin.communities.documents.destroy', [$community, $doc]));
    $adminResponse->assertForbidden();

    expect(Storage::disk('local')->exists($path))->toBeTrue();
    $this->assertModelExists($doc);
});

test('penghapusan dokumen opsional atau dokumen pada status draft diizinkan', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('community_manager');

    // 1. Draft community statutory doc deletion allowed
    $communityDraft = Community::factory()->create([
        'subtype' => 'club',
        'status' => 'draft',
    ]);

    CommunityOfficial::create([
        'community_id' => $communityDraft->id,
        'user_id' => $user->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]);

    $path1 = UploadedFile::fake()->create('draft_reg.pdf', 100)->store('community_documents', 'local');
    $doc1 = Document::create([
        'documentable_type' => Community::class,
        'documentable_id' => $communityDraft->id,
        'category' => 'club_registration',
        'name' => 'Draft Reg',
        'file_path' => $path1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)->delete(route('panel.documents.destroy', $doc1))
        ->assertRedirect(route('panel.documents.index'));

    expect(Storage::disk('local')->exists($path1))->toBeFalse();
    $this->assertModelMissing($doc1);

    // End first management assignment before testing another entity.
    CommunityOfficial::where('community_id', $communityDraft->id)->where('user_id', $user->id)->update(['ended_at' => now()]);

    // 2. Verified community optional non-verified doc deletion allowed
    $communityVerified = Community::factory()->create([
        'subtype' => 'club',
        'status' => 'verified',
    ]);

    CommunityOfficial::create([
        'community_id' => $communityVerified->id,
        'user_id' => $user->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]);

    $path2 = UploadedFile::fake()->create('optional.pdf', 100)->store('community_documents', 'local');
    $doc2 = Document::create([
        'documentable_type' => Community::class,
        'documentable_id' => $communityVerified->id,
        'category' => 'other',
        'name' => 'Dokumen Opsional',
        'file_path' => $path2,
        'status' => 'pending',
    ]);

    $this->actingAs($user)->delete(route('panel.documents.destroy', $doc2))
        ->assertRedirect(route('panel.documents.index'));

    expect(Storage::disk('local')->exists($path2))->toBeFalse();
    $this->assertModelMissing($doc2);
});

test('unggah dokumen legalitas wajib diblokir saat pending atau verified tetapi dokumen opsional diizinkan', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('community_manager');

    $community = Community::factory()->create([
        'subtype' => 'club',
        'status' => 'pending',
    ]);

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $user->id,
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]);

    $fakeFile = UploadedFile::fake()->create('statutory.pdf', 100, 'application/pdf');

    // Attempt to upload statutory category under pending community fails
    $failResponse = $this->actingAs($user)->post(route('panel.documents.store'), [
        'category' => 'club_registration',
        'name' => 'Tanda Daftar Baru',
        'file' => $fakeFile,
    ]);

    $failResponse->assertSessionHasErrors('category');
    expect(Document::where('documentable_id', $community->id)->where('category', 'club_registration')->count())->toBe(0);

    // Uploading non-statutory optional category under pending succeeds
    $fakeFile2 = UploadedFile::fake()->create('other.pdf', 100, 'application/pdf');
    $successResponse = $this->actingAs($user)->post(route('panel.documents.store'), [
        'category' => 'other',
        'name' => 'Dokumen Tambahan',
        'file' => $fakeFile2,
    ]);

    $successResponse->assertRedirect(route('panel.documents.index'));
    expect(Document::where('documentable_id', $community->id)->where('category', 'other')->count())->toBe(1);
});

test('admin show tidak menampilkan tombol verifikasi untuk komunitas rejected dan tombol verifikasi pending disabled jika dokumen wajib belum lengkap', function (): void {
    // 1. Rejected community does not expose verify button
    $communityRejected = Community::factory()->create([
        'subtype' => 'school',
        'npsn' => '12345678',
        'status' => 'rejected',
        'reject_reason' => 'Berkas tidak valid',
    ]);

    $rejectedResp = $this->actingAs($this->admin)->get(route('admin.communities.show', $communityRejected));
    $rejectedResp->assertOk();
    $rejectedResp->assertDontSee('name="decision" value="verified"', false);

    // 2. Pending community with missing statutory doc has disabled verify button and checklist
    $communityPending = Community::factory()->create([
        'subtype' => 'school',
        'npsn' => '12345679',
        'status' => 'pending',
    ]);

    $pendingResp = $this->actingAs($this->admin)->get(route('admin.communities.show', $communityPending));
    $pendingResp->assertOk();
    $pendingResp->assertSee('Verifikasi Komunitas');
    $pendingResp->assertSee('disabled');
    $pendingResp->assertSee('Belum Lengkap / Dokumen Belum Terverifikasi');
    $pendingResp->assertSee('Missing');

    // 3. Pending community with verified statutory doc enables verify button
    $path = UploadedFile::fake()->create('decree.pdf', 100)->store('community_documents', 'local');
    Document::create([
        'documentable_type' => Community::class,
        'documentable_id' => $communityPending->id,
        'category' => 'institution_decree',
        'name' => 'SK Sekolah',
        'file_path' => $path,
        'status' => 'verified',
    ]);

    $readyResp = $this->actingAs($this->admin)->get(route('admin.communities.show', $communityPending));
    $readyResp->assertOk();
    $readyResp->assertSee('Semua Dokumen Terverifikasi (Siap Diverifikasi)');
    $readyResp->assertDontSee('disabled title="Dokumen legalitas wajib belum lengkap / belum terverifikasi"');
});
