<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityLinkPage;
use App\Modules\Community\Services\CommunityLinkPageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $role = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web'], ['panel_access' => 'internal']);
    foreach (['community.view', 'community-links.moderate'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole($role);
    $this->admin->givePermissionTo(['community.view', 'community-links.moderate']);
    $this->actingAs($this->admin);
});

test('admin can grant multiple communities and revoke one without changing other access', function (): void {
    $a = Community::factory()->create(['name' => 'Perinatologi Pilihan A', 'is_active' => true]);
    $b = Community::factory()->create(['name' => 'Perinatologi Pilihan B', 'is_active' => true]);
    $service = app(CommunityLinkPageService::class);
    $this->get('/admin/community-links')->assertOk()->assertSee('/admin/community-links/theme-access', false);
    $this->get('/admin/community-links/theme-access?q=Perinatologi+Pilihan')->assertOk()->assertSee($a->name)->assertSee($b->name);
    foreach ([$a, $b] as $community) {
        $this->put('/admin/community-links/theme-access/'.$community->id, ['enabled' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        expect($service->themes($community))->toHaveKey('perinatologi-care');
    }
    $this->get('/admin/community-links/theme-access?q=Perinatologi+Pilihan')->assertOk()->assertSee('Aktif');
    $this->put('/admin/community-links/theme-access/'.$a->id, ['enabled' => '0'])->assertRedirect()->assertSessionHasNoErrors();
    expect($service->themes($a))->not->toHaveKey('perinatologi-care');
    expect($service->themes($b))->toHaveKey('perinatologi-care');
    expect(DB::table('settings')->where('group', 'community_links')->where('key', 'community_links.premium.'.$a->id)->value('value'))->toBe('0');
    expect(ActivityLog::where('auditable_type', Community::class)->where('auditable_id', $a->id)->whereJsonContains('new_values->action', 'premium_theme_access')->count())->toBe(2);
});

test('database revocation overrides legacy config and later grants take effect fresh', function (): void {
    $community = Community::factory()->create(['is_active' => true]);
    config(['community_links.exclusive_communities' => [$community->id]]);
    $service = app(CommunityLinkPageService::class);
    expect($service->themes($community))->toHaveKey('perinatologi-care');
    $page = CommunityLinkPage::create(['community_id' => $community->id, 'handle' => 'grant-'.uniqid(), 'status' => 'published', 'draft_content' => [], 'published_content' => ['title' => 'Portal tetap utuh', 'theme_id' => 'perinatologi-care', 'links' => []]]);
    $this->get('/c/'.$page->handle)->assertOk()->assertSee('perinatologi-care.js');
    $this->put('/admin/community-links/theme-access/'.$community->id, ['enabled' => 0])->assertRedirect()->assertSessionHasNoErrors();
    expect($service->themes($community))->not->toHaveKey('perinatologi-care');
    $this->get('/c/'.$page->handle)->assertOk()->assertSee('Portal tetap utuh')->assertSee('class="clean-light"', false)->assertDontSee('perinatologi-care.js');
    expect($page->fresh()->published_content['theme_id'])->toBe('perinatologi-care');
    $this->put('/admin/community-links/theme-access/'.$community->id, ['enabled' => 1])->assertRedirect()->assertSessionHasNoErrors();
    expect($service->themes($community))->toHaveKey('perinatologi-care');
    $this->put('/admin/community-links/theme-access/'.$community->id, ['enabled' => 1])->assertRedirect()->assertSessionHasNoErrors();
    expect(ActivityLog::where('auditable_type', Community::class)->where('auditable_id', $community->id)->whereJsonContains('new_values->action', 'premium_theme_access')->count())->toBe(2);
    $community->update(['is_active' => false]);
    $this->put('/admin/community-links/theme-access/'.$community->id, ['enabled' => 0])->assertRedirect()->assertSessionHasNoErrors();
    expect($service->themes($community))->not->toHaveKey('perinatologi-care');
});

test('premium access is validated and requires active internal moderator', function (): void {
    $community = Community::factory()->create(['is_active' => true]);
    $this->getJson('/admin/community-links/theme-access?q[]=bad')->assertUnprocessable();
    $this->putJson('/admin/community-links/theme-access/'.$community->id, ['enabled' => ['bad']])->assertUnprocessable();
    $this->putJson('/admin/community-links/theme-access/999999999', ['enabled' => 1])->assertNotFound();
    $community->update(['is_active' => false]);
    $this->putJson('/admin/community-links/theme-access/'.$community->id, ['enabled' => 1])->assertUnprocessable();
    $this->admin->revokePermissionTo('community-links.moderate');
    $this->get('/admin/community-links/theme-access')->assertForbidden();
    $this->putJson('/admin/community-links/theme-access/'.$community->id, ['enabled' => 1])->assertForbidden();
    $this->get('/admin/community-links')->assertOk()->assertDontSee('/admin/community-links/theme-access');
    $this->admin->givePermissionTo('community-links.moderate');
    $this->admin->update(['is_active' => false]);
    $this->putJson('/admin/community-links/theme-access/'.$community->id, ['enabled' => 1])->assertForbidden();
    expect(DB::table('settings')->where('key', 'community_links.premium.'.$community->id)->exists())->toBeFalse();
});

test('external managers cannot grant premium even with a forged permission', function (): void {
    $role = Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);
    $user->givePermissionTo('community-links.moderate');
    $community = Community::factory()->create(['is_active' => true]);
    $this->actingAs($user)->get('/admin/community-links/theme-access')->assertForbidden();
    $this->putJson('/admin/community-links/theme-access/'.$community->id, ['enabled' => 1])->assertForbidden();
});
