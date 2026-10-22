<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityLinkPage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

test('moderator suspends immediately and restoring requires owner republishing', function (): void {
    $role = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web'], ['panel_access' => 'internal']);
    foreach (['community.view', 'community-links.moderate'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $actor = User::factory()->create(['is_active' => true]);
    $actor->assignRole($role);
    $actor->givePermissionTo(['community.view', 'community-links.moderate']);
    $community = Community::factory()->create(['is_active' => true]);
    $page = CommunityLinkPage::create(['community_id' => $community->id, 'handle' => 'moderation-'.uniqid(), 'status' => 'published', 'draft_content' => [], 'published_content' => ['title' => 'Public', 'theme_id' => 'clean-light', 'links' => []]]);
    $this->actingAs($actor)->get('/admin/community-links')->assertOk()->assertSee($page->handle);
    $this->post('/admin/community-links/'.$page->id.'/suspend', ['revision' => 1, 'reason' => 'Konten sedang ditinjau'])->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/c/'.$page->handle)->assertNotFound();
    expect($page->fresh()->suspended_at)->not->toBeNull();
    $this->postJson('/admin/community-links/'.$page->id.'/restore', ['revision' => 1, 'reason' => 'Selesai ditinjau'])->assertConflict();
    $this->post('/admin/community-links/'.$page->id.'/restore', ['revision' => 2, 'reason' => 'Selesai ditinjau'])->assertRedirect();
    expect($page->fresh()->status)->toBe('unpublished')->and($page->fresh()->suspended_at)->toBeNull();
    $this->get('/c/'.$page->handle)->assertNotFound();
    $actor->revokePermissionTo('community-links.moderate');
    $this->postJson('/admin/community-links/'.$page->id.'/suspend', ['revision' => 3, 'reason' => 'No permission'])->assertForbidden();
});
