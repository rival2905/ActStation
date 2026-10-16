<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityCoach;
use App\Modules\Community\Models\CommunityOfficial;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

test('active athlete browses own community directory and history without management or private data', function (): void {
    Http::preventStrayRequests();
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(['athlete', 'community_manager']);
    $managed = Community::factory()->create(['is_active' => true]);
    CommunityOfficial::factory()->create(['community_id' => $managed->id, 'user_id' => $user->id, 'status' => 'active', 'ended_at' => null]);
    $club = Community::factory()->create(['is_active' => true]);
    $membership = CommunityAthlete::factory()->create(['community_id' => $club->id, 'user_id' => $user->id, 'status' => 'active', 'region_status' => 'pending', 'left_at' => null]);
    $peer = User::factory()->create(['name' => 'Atlet Rekan Direktori', 'email' => 'private-directory@example.test', 'identity_number' => '3273010101050001', 'phone_number' => '081112223339']);
    CommunityAthlete::factory()->create(['community_id' => $club->id, 'user_id' => $peer->id, 'status' => 'active', 'left_at' => null, 'nisn' => '0012345678', 'region_notes' => 'CATATAN_PRIVAT_WILAYAH']);
    CommunityAthlete::factory()->create(['community_id' => $club->id, 'user_id' => User::factory()->create(['name' => 'Mantan Atlet Direktori'])->id, 'status' => 'left', 'joined_at' => '2024-01-01', 'left_at' => '2025-01-01']);
    foreach ([['Pengurus Kini', 'active', null], ['Pengurus Lama', 'inactive', '2025-01-01']] as [$name, $status, $end]) {
        CommunityOfficial::factory()->create(['community_id' => $club->id, 'user_id' => User::factory()->create(['name' => $name])->id, 'position' => 'Sekretaris', 'status' => $status, 'started_at' => '2024-01-01', 'ended_at' => $end]);
    }
    foreach ([['Pelatih Kini', 'active', null], ['Pelatih Lama', 'inactive', '2025-01-01']] as [$name, $status, $end]) {
        CommunityCoach::factory()->create(['community_id' => $club->id, 'user_id' => User::factory()->create(['name' => $name])->id, 'status' => $status, 'started_at' => '2024-01-01', 'ended_at' => $end]);
    }
    CommunityAthlete::factory()->create(['community_id' => $club->id, 'user_id' => User::factory()->create(['name' => 'Pendaftar Privat Belum Disetujui'])->id, 'status' => 'pending']);
    CommunityAthlete::factory()->create(['community_id' => $club->id, 'user_id' => User::factory()->create(['name' => 'Pengajuan Ditolak Privat'])->id, 'status' => 'rejected']);
    $url = '/panel/my-communities/'.$club->id;
    $this->actingAs($user)->withSession(['current_community_id' => $managed->id])->get(route('panel.my-communities.index'))->assertOk()->assertSee($url);
    $response = $this->get($url)->assertOk()->assertSee($club->name)->assertSee('Hanya Baca')
        ->assertSee('Atlet Rekan Direktori')->assertSee('Mantan Atlet Direktori')->assertSee('Pengurus Kini')->assertSee('Pengurus Lama')->assertSee('Pelatih Kini')->assertSee('Pelatih Lama')->assertSee('01-01-2025')
        ->assertDontSee($peer->email)->assertDontSee($peer->identity_number)->assertDontSee($peer->phone_number)->assertDontSee('0012345678')->assertDontSee('CATATAN_PRIVAT_WILAYAH')->assertDontSee('Pendaftar Privat Belum Disetujui')->assertDontSee('Pengajuan Ditolak Privat')
        ->assertSessionHas('current_community_id', $managed->id);
    expect($response->viewData('directory')['groups']['athletes']->first())->not->toHaveKeys(['email', 'identity_number', 'nisn', 'nim', 'phone_number', 'documents']);
    $this->post(route('panel.community.switch'), ['community_id' => $club->id])->assertForbidden();
    $this->delete(route('panel.officials.destroy', CommunityOfficial::where('community_id', $club->id)->first()))->assertForbidden();
    expect(CommunityOfficial::where('community_id', $club->id)->count())->toBe(2);
    $user->removeRole('community_manager');
    CommunityOfficial::where('community_id', $managed->id)->where('user_id', $user->id)->update(['ended_at' => today()]);
    $this->get($url)->assertOk();
    $this->post(route('panel.community.switch'), ['community_id' => $club->id])->assertForbidden();
    $this->post(route('panel.coaches.store'), ['community_id' => $club->id, 'user_id' => $peer->id, 'gender_scope' => 'all'])->assertForbidden();
    $membership->update(['region_status' => 'rejected']);
    $this->get($url)->assertOk();
    foreach ([['status' => 'pending'], ['status' => 'left', 'left_at' => today()]] as $attributes) {
        $membership->update($attributes);
        $this->get($url)->assertForbidden();
    }
    $membership->update(['status' => 'active', 'left_at' => null]);
    $club->update(['is_active' => false]);
    $this->get($url)->assertForbidden();
    $club->update(['is_active' => true]);
    $user->update(['is_active' => false]);
    $this->get($url)->assertForbidden();
    $outsider = User::factory()->create(['is_active' => true]);
    $outsider->assignRole('athlete');
    $this->actingAs($outsider)->get($url)->assertForbidden();
    $official = CommunityOfficial::factory()->create(['community_id' => $club->id, 'user_id' => $outsider->id, 'status' => 'active', 'ended_at' => null]);
    $this->get($url)->assertOk();
    $official->update(['ended_at' => today()]);
    $this->get($url)->assertForbidden();
    $this->actingAs($outsider)->get('/panel/my-communities/999999999')->assertNotFound();
});

test('mixed account separates managed communities from athlete membership', function (): void {
    Http::preventStrayRequests();
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(['community_manager', 'athlete']);
    $first = Community::factory()->create(['name' => 'Sekolah Pengurus Pertama', 'is_active' => true]);
    $second = Community::factory()->create(['name' => 'Komunitas Sekretaris Kedua', 'is_active' => true]);
    $club = Community::factory()->create(['name' => 'Klub Atlet Tanpa Hak Kelola', 'subtype' => 'club', 'is_active' => true]);
    foreach ([$first->id => 'manager', $second->id => 'secretary'] as $id => $position) {
        CommunityOfficial::create(['community_id' => $id, 'user_id' => $user->id, 'position' => $position, 'status' => 'active', 'started_at' => today()]);
    }
    CommunityAthlete::create(['community_id' => $club->id, 'user_id' => $user->id, 'status' => 'active', 'region_status' => 'pending', 'joined_at' => today()]);
    $this->actingAs($user)->withSession(['current_community_id' => $first->id])
        ->get(route('panel.my-communities.index'))->assertOk()
        ->assertSee('Sebagai Pengurus')->assertSee('Sebagai Atlet')
        ->assertSee($first->name)->assertSee($second->name)->assertSee($club->name)
        ->assertSee('Sekretaris')->assertSee('Belum terverifikasi');
    $this->post(route('panel.community.switch'), ['community_id' => $second->id])
        ->assertRedirect(route('panel.profile.show'))->assertSessionHas('current_community_id', $second->id);
    $this->post(route('panel.community.switch'), ['community_id' => $club->id])->assertForbidden();
    $this->assertDatabaseMissing('community_officials', ['community_id' => $club->id, 'user_id' => $user->id]);
});
