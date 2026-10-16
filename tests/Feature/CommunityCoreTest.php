<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityCoach;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\Document;
use App\Modules\Community\Services\CommunityLifecycle;
use App\Modules\Community\Services\InvitationService;
use Database\Seeders\RolePermissionSeeder;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->superadmin = User::where('email', 'superadmin@actstation.test')->first();

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['is_active' => true]);
    $this->staff->assignRole('staff');
});

test('transisi ilegal (draft ke verified langsung) ditolak', function (): void {
    $community = Community::factory()->create(['status' => 'draft']);

    // Admin HTTP call attempting to verify draft directly
    $response = $this->actingAs($this->admin)
        ->from(route('admin.communities.index'))
        ->post(route('admin.communities.verify', $community));

    $response->assertSessionHasErrors('status');
    $this->assertDatabaseHas('communities', [
        'id' => $community->id,
        'status' => 'draft',
    ]);

    // Service call directly throws DomainException
    expect(fn () => (new CommunityLifecycle)->verify($community))
        ->toThrow(DomainException::class);
});

test('reject wajib alasan', function (): void {
    $community = Community::factory()->create(['status' => 'pending']);

    // HTTP call with empty reject_reason
    $response = $this->actingAs($this->admin)
        ->from(route('admin.communities.index'))
        ->post(route('admin.communities.verify', $community), [
            'decision' => 'rejected',
            'reject_reason' => '',
        ]);

    $response->assertSessionHasErrors('reject_reason');
    $this->assertDatabaseHas('communities', [
        'id' => $community->id,
        'status' => 'pending',
    ]);

    // Service call directly with empty reason throws InvalidArgumentException
    expect(fn () => (new CommunityLifecycle)->reject($community, '   '))
        ->toThrow(InvalidArgumentException::class);
});

test('staff tanpa permission mendapat 403', function (): void {
    $community = Community::factory()->create(['status' => 'pending']);

    // Staff cannot store community
    $this->actingAs($this->staff)
        ->post(route('admin.communities.store'), [
            'name' => 'Klub Baru',
            'subtype' => 'club',
        ])
        ->assertForbidden();

    // Staff cannot delete community
    $this->actingAs($this->staff)
        ->delete(route('admin.communities.destroy', $community))
        ->assertForbidden();

    // Staff cannot verify community
    $this->actingAs($this->staff)
        ->post(route('admin.communities.verify', $community))
        ->assertForbidden();

    // Staff cannot revoke community
    $this->actingAs($this->staff)
        ->post(route('admin.communities.revoke', $community), [
            'reject_reason' => 'Alasan revoke',
        ])
        ->assertForbidden();

    // Staff cannot manage officials
    $user = User::factory()->create();
    $this->actingAs($this->staff)
        ->post(route('admin.communities.officials.store', $community), [
            'user_id' => $user->id,
            'position' => 'Sekretaris',
            'started_at' => now()->toDateString(),
        ])
        ->assertForbidden();
});

test('unique generated column menolak dua pengurus aktif dengan posisi sama', function (): void {
    $community = Community::factory()->create();
    $user = User::factory()->create();

    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $user->id,
        'position' => 'Ketua',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]);

    // Same community, same user, same active position -> unique conflict
    expect(fn () => CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $user->id,
        'position' => 'Ketua',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]))->toThrow(QueryException::class);

    // If previous position ended, can become Ketua again
    $first = CommunityOfficial::first();
    $first->update(['ended_at' => now()->toDateString()]);

    $second = CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $user->id,
        'position' => 'Ketua',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]);

    expect($second->exists)->toBeTrue();
});

test('FK restrictOnDelete dari communities ke sport_categories mencegah hapus cabor yang dipakai', function (): void {
    $sportCategoryId = DB::table('sport_categories')->insertGetId([
        'name' => 'Bola Basket Core',
        'slug' => 'bola-basket-core',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Community::factory()->create([
        'sport_category_id' => $sportCategoryId,
    ]);

    expect(fn () => DB::table('sport_categories')->where('id', $sportCategoryId)->delete())
        ->toThrow(QueryException::class);
});

test('lifecycle verify, reject, revoke dan submit bekerja dengan benar', function (): void {
    Storage::fake('local');
    $community = Community::factory()->create(['status' => 'pending']);

    $pathReg = UploadedFile::fake()->create('reg.pdf', 100)->store('community_documents', 'local');
    $pathEntity = UploadedFile::fake()->create('entity.pdf', 100)->store('community_documents', 'local');

    Document::create([
        'documentable_type' => Community::class,
        'documentable_id' => $community->id,
        'category' => 'club_registration',
        'name' => 'Tanda Registrasi Klub',
        'file_path' => $pathReg,
        'status' => 'verified',
        'is_public' => false,
    ]);

    Document::create([
        'documentable_type' => Community::class,
        'documentable_id' => $community->id,
        'category' => 'legal_entity_proof',
        'name' => 'Bukti Legalitas Klub',
        'file_path' => $pathEntity,
        'status' => 'verified',
        'is_public' => false,
    ]);

    // Admin verifies pending community
    $this->actingAs($this->admin)
        ->from(route('admin.communities.index'))
        ->post(route('admin.communities.verify', $community))
        ->assertRedirect(route('admin.communities.index'))
        ->assertSessionHas('success');

    $community->refresh();
    expect($community->status)->toBe('verified')
        ->and($community->verified_at)->not->toBeNull()
        ->and($community->verified_by)->toBe($this->admin->id);

    // Admin revokes verification
    $this->actingAs($this->admin)
        ->from(route('admin.communities.index'))
        ->post(route('admin.communities.revoke', $community), [
            'reject_reason' => 'Pelanggaran ketentuan keabsahan klub',
        ])
        ->assertRedirect(route('admin.communities.index'))
        ->assertSessionHas('success');

    $community->refresh();
    expect($community->status)->toBe('rejected')
        ->and($community->verified_at)->toBeNull()
        ->and($community->verified_by)->toBeNull()
        ->and($community->reject_reason)->toBe('Pelanggaran ketentuan keabsahan klub');
});

test('dokumen admin tidak tersimpan di disk public dan bisa diunduh lewat route privat', function (): void {
    Storage::fake('local');
    Storage::fake('public');

    $community = Community::factory()->create();
    $file = UploadedFile::fake()->create('akta_notaris.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->admin)->post(route('admin.communities.documents.store', $community), [
        'category' => 'deed',
        'name' => 'Akta Notaris',
        'document_number' => '123/NOT/2026',
        'file' => $file,
    ]);

    $response->assertRedirect(route('admin.communities.documents.index', $community));

    $document = $community->documents()->first();
    expect($document)->not->toBeNull();

    Storage::disk('public')->assertMissing($document->file_path);
    Storage::disk('local')->assertExists($document->file_path);

    $downloadResponse = $this->actingAs($this->admin)->get(route('admin.communities.documents.download', [$community, $document]));
    $downloadResponse->assertOk();
});

test('IDOR nested admin menghasilkan 404', function (): void {
    $communityA = Community::factory()->create();
    $communityB = Community::factory()->create();

    $officialB = CommunityOfficial::factory()->create(['community_id' => $communityB->id]);
    $coachB = CommunityCoach::factory()->create(['community_id' => $communityB->id]);
    $athleteB = CommunityAthlete::factory()->create(['community_id' => $communityB->id]);
    $documentB = Document::create([
        'documentable_type' => Community::class,
        'documentable_id' => $communityB->id,
        'category' => 'deed',
        'name' => 'Doc B',
        'file_path' => 'community_documents/docb.pdf',
        'status' => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('admin.communities.officials.destroy', [$communityA, $officialB]))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->delete(route('admin.communities.coaches.destroy', [$communityA, $coachB]))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->post(route('admin.communities.athletes.release', [$communityA, $athleteB]))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->delete(route('admin.communities.documents.destroy', [$communityA, $documentB]))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->get(route('admin.communities.documents.download', [$communityA, $documentB]))
        ->assertNotFound();
});

test('undangan privat dengan email lain ditolak', function (): void {
    $community = Community::factory()->create();
    $invitationService = app(InvitationService::class);

    $result = $invitationService->createInvitation(
        $community,
        ['is_global' => false, 'email' => 'invited@actstation.test'],
        $this->admin
    );

    $response = $this->post(route('invitations.public.submit', $result['plain_token']), [
        'consent' => '1',
        'name' => 'Imposter User',
        'email' => 'other@actstation.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertDatabaseMissing('users', ['email' => 'other@actstation.test']);
});

test('email existing tidak ter-attach', function (): void {
    $community = Community::factory()->create();
    $existingUser = User::factory()->create([
        'email' => 'existing@actstation.test',
        'is_active' => true,
    ]);
    expect($existingUser->hasRole('athlete'))->toBeFalse();

    $invitationService = app(InvitationService::class);
    $result = $invitationService->createInvitation(
        $community,
        ['is_global' => false, 'email' => 'existing@actstation.test'],
        $this->admin
    );

    $response = $this->post(route('invitations.public.submit', $result['plain_token']), [
        'consent' => '1',
        'name' => 'Existing User',
        'email' => 'existing@actstation.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors(['email' => 'Email sudah terdaftar, silakan login di panel lalu terima undangan']);

    $this->assertDatabaseMissing('community_athletes', [
        'community_id' => $community->id,
        'user_id' => $existingUser->id,
    ]);
    expect($existingUser->fresh()->hasRole('athlete'))->toBeFalse();
});

test('overseer login diarahkan ke halaman region', function (): void {
    $overseer = User::factory()->create([
        'email' => 'overseer@actstation.test',
        'password' => 'password123',
        'is_active' => true,
    ]);
    $overseer->assignRole('region_overseer');

    $response = $this->post(route('admin.login.attempt'), [
        'email' => 'overseer@actstation.test',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('admin.region-oversight.athletes.index'));
    $this->assertAuthenticatedAs($overseer);
});

test('plain token hanya diberikan sekali lewat flash invite_url dan hash token tidak dikirim ke view', function (): void {
    $community = Community::factory()->create();
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('community_manager');
    CommunityOfficial::factory()->create(['community_id' => $community->id, 'user_id' => $manager->id]);

    $response = $this->actingAs($manager)
        ->withSession(['current_community_id' => $community->id])
        ->post(route('panel.invitations.store'), [
            'invite_type' => 'athlete',
            'is_global' => false,
            'email' => 'newathlete@actstation.test',
        ]);

    $response->assertRedirect(route('panel.athletes.index'));
    $response->assertSessionHas('invite_url');
    $inviteUrl = session('invite_url');
    expect($inviteUrl)->not->toBeNull();

    $invitation = $community->invitations()->first();
    expect($inviteUrl)->not->toContain($invitation->token);

    $indexResponse = $this->actingAs($manager)
        ->withSession(['current_community_id' => $community->id])
        ->get(route('panel.athletes.index'));

    $indexResponse->assertOk();
    $viewInvitations = $indexResponse->viewData('invitations');
    expect($viewInvitations->first()->getAttributes())->not->toHaveKey('token');
});
