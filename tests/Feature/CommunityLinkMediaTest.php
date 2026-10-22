<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityLinkPage;
use App\Modules\Community\Models\CommunityOfficial;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

test('avatar stays private until published and upload preserves old published media', function (): void {
    Storage::fake('local');
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('community_manager');
    $community = Community::factory()->create(['is_active' => true]);
    CommunityOfficial::create(['community_id' => $community->id, 'user_id' => $owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $page = CommunityLinkPage::create(['community_id' => $community->id, 'handle' => 'avatar-'.uniqid(), 'draft_content' => ['title' => 'Avatar', 'theme_id' => 'clean-light', 'socials' => [], 'categories' => [], 'links' => [['id' => (string) Str::uuid(), 'label' => 'Info', 'url' => 'https://example.com/', 'visible' => true]]]]);
    $this->actingAs($owner)->withSession(['current_community_id' => $community->id]);
    $url = '/panel/community-links/'.$page->id.'/avatar';
    $this->post($url, ['revision' => 1, 'avatar' => UploadedFile::fake()->image('logo.png', 100, 100)])->assertRedirect()->assertSessionHasNoErrors();
    $asset = $page->fresh()->draft_content['avatar'];
    Storage::disk('local')->assertExists('community-links/'.$page->id.'/'.$asset.'.webp');
    $this->get('/c/'.$page->handle.'/avatar/'.$asset)->assertNotFound();
    $this->get($url.'/'.$asset)->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->post('/panel/community-links/'.$page->id.'/publish', ['revision' => 2])->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/c/'.$page->handle.'/avatar/'.$asset)->assertOk();
    $this->post($url, ['revision' => 3, 'avatar' => UploadedFile::fake()->image('new.jpg', 80, 80)])->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/c/'.$page->handle.'/avatar/'.$asset)->assertOk();
    $this->postJson($url, ['revision' => 4, 'avatar' => UploadedFile::fake()->createWithContent('evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')])->assertUnprocessable();
    $this->postJson($url, ['revision' => 1, 'avatar' => UploadedFile::fake()->image('stale.png')])->assertConflict();
    $this->postJson($url, ['revision' => 4, 'panel_community_id' => $community->id + 1, 'avatar' => UploadedFile::fake()->image('wrong-context.png')])->assertUnprocessable();
    $this->postJson($url, ['revision' => 4, 'avatar' => UploadedFile::fake()->image('huge.png', 2050, 40)])->assertUnprocessable();
    $this->post('/panel/community-links/'.$page->id.'/unpublish', ['revision' => 4])->assertRedirect();
    $this->get('/c/'.$page->handle.'/avatar/'.$asset)->assertNotFound();
    $this->post($url, ['revision' => 5, 'asset_kind' => 'background', 'avatar' => UploadedFile::fake()->image('background.jpg', 100, 100)])->assertRedirect()->assertSessionHasNoErrors();
    expect($page->fresh()->draft_content)->toHaveKey('background');
    for ($i = 0; $i < 10; $i++) {
        Storage::disk('local')->put('community-links/'.$page->id.'/old-'.$i.'.webp', 'fixture');
    }
    $this->postJson($url, ['revision' => 6, 'avatar' => UploadedFile::fake()->image('quota.png')])->assertUnprocessable();
    foreach (Storage::disk('local')->files('community-links/'.$page->id) as $file) {
        touch(Storage::disk('local')->path($file), now()->subDays(2)->timestamp);
    }
    $this->artisan('community-links:cleanup-media')->assertSuccessful();
    Storage::disk('local')->assertExists('community-links/'.$page->id.'/'.$asset.'.webp');
    Storage::disk('local')->assertMissing('community-links/'.$page->id.'/old-0.webp');
    $draftAsset = $page->fresh()->draft_content['avatar'];
    $this->get('/panel/community-links/'.$page->id.'/edit')->assertOk()->assertSee('Hapus Logo')->assertSee('Perubahan belum diterbitkan');
    $foreign = CommunityLinkPage::create(['community_id' => Community::factory()->create()->id, 'handle' => 'foreign-'.uniqid(), 'draft_content' => ['avatar' => $draftAsset]]);
    $this->postJson('/panel/community-links/'.$foreign->id.'/remove-avatar', ['revision' => 1])->assertNotFound();
    $this->postJson('/panel/community-links/'.$page->id.'/remove-avatar', ['revision' => 6, 'panel_community_id' => $community->id + 1])->assertUnprocessable();
    $this->postJson('/panel/community-links/'.$page->id.'/remove-avatar', ['revision' => 1])->assertConflict();
    $this->post('/panel/community-links/'.$page->id.'/remove-avatar', ['revision' => 6])->assertRedirect()->assertSessionHasNoErrors();
    expect($page->fresh()->draft_content)->not->toHaveKey('avatar');
    expect($page->fresh()->published_content['avatar'])->toBe($asset);
    Storage::disk('local')->assertExists('community-links/'.$page->id.'/'.$draftAsset.'.webp');
    $this->get('/panel/community-links/'.$page->id.'/preview')->assertOk()->assertDontSee('class="avatar"', false);
    $this->get('/panel/community-links/'.$page->id.'/edit')->assertOk()->assertDontSee('Hapus Logo');
    $this->post('/panel/community-links/'.$page->id.'/publish', ['revision' => 7])->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/panel/community-links/'.$page->id.'/edit')->assertOk()->assertSee('Draft sudah sama dengan versi yang diterbitkan.');
    $this->get('/c/'.$page->handle)->assertOk()->assertDontSee('class="avatar"', false);
    $this->get('/c/'.$page->handle.'/avatar/'.$asset)->assertNotFound();
    $page->update(['suspended_at' => now()]);
    $this->get('/c/'.$page->handle.'/avatar/'.$asset)->assertNotFound();
});
