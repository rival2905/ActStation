<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityLinkPage;
use App\Modules\Community\Models\CommunityOfficial;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function communityLogoGlb(array $overrides = []): string
{
    $json = array_replace(['asset' => ['version' => '2.0'], 'scene' => 0, 'scenes' => [['nodes' => [0]]], 'nodes' => [['mesh' => 0]], 'meshes' => [['primitives' => [['attributes' => ['POSITION' => 0]]]]], 'buffers' => [['byteLength' => 36]], 'bufferViews' => [['buffer' => 0, 'byteLength' => 36]], 'accessors' => [['bufferView' => 0, 'componentType' => 5126, 'count' => 3, 'type' => 'VEC3', 'min' => [-1, -1, 0], 'max' => [1, 1, 0]]]], $overrides);
    $bytes = json_encode($json, JSON_THROW_ON_ERROR);
    $bytes .= str_repeat(' ', (4 - strlen($bytes) % 4) % 4);
    $bin = pack('g*', -1, -1, 0, 1, -1, 0, 0, 1, 0);

    return 'glTF'.pack('V2', 2, 28 + strlen($bytes) + strlen($bin)).pack('V2', strlen($bytes), 0x4E4F534A).$bytes.pack('V2', strlen($bin), 0x004E4942).$bin;
}

test('GLB logo upload is premium private revision scoped and rejects unsafe resources', function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('community_manager');
    $community = Community::factory()->create(['is_active' => true]);
    CommunityOfficial::create(['community_id' => $community->id, 'user_id' => $owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $page = CommunityLinkPage::create(['community_id' => $community->id, 'handle' => 'model-'.uniqid(), 'draft_content' => ['title' => 'Model', 'theme_id' => 'perinatologi-care', 'links' => [['id' => (string) Str::uuid(), 'label' => 'Info', 'url' => 'https://example.com/', 'visible' => true]]]]);
    $this->actingAs($owner)->withSession(['current_community_id' => $community->id]);
    $url = '/panel/community-links/'.$page->id.'/avatar';
    $upload = fn (string $bytes, int $revision = 1) => $this->postJson($url, ['revision' => $revision, 'asset_kind' => 'model', 'avatar' => UploadedFile::fake()->createWithContent('logo.glb', $bytes)]);
    $upload(communityLogoGlb())->assertForbidden();
    config(['community_links.exclusive_communities' => [$community->id]]);
    $upload(communityLogoGlb())->assertRedirect()->assertSessionHasNoErrors();
    $asset = $page->fresh()->draft_content['model'];
    Storage::disk('local')->assertExists('community-links/'.$page->id.'/'.$asset.'.glb');
    $this->get('/panel/community-links/'.$page->id.'/model/'.$asset)->assertOk()->assertHeader('Content-Type', 'model/gltf-binary')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get('/c/'.$page->handle.'/model/'.$asset)->assertNotFound();
    $this->get('/panel/community-links/'.$page->id.'/preview')->assertOk()->assertSee('data-model-url=', false)->assertSee('GLTFLoader-r128.js');
    $this->post('/panel/community-links/'.$page->id.'/publish', ['revision' => 2])->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/c/'.$page->handle.'/model/'.$asset)->assertOk();
    $upload(communityLogoGlb(), 1)->assertConflict();
    foreach (['not-glb', substr(communityLogoGlb(), 0, -1), communityLogoGlb(['buffers' => [['byteLength' => 36, 'uri' => 'https://evil.test/model.bin']]]), communityLogoGlb(['images' => [['uri' => 'data:image/png;base64,x']]]), communityLogoGlb(['extensionsUsed' => ['KHR_draco_mesh_compression']]), communityLogoGlb(['accessors' => [['bufferView' => 0, 'componentType' => 5126, 'count' => 100001, 'type' => 'VEC3']]]), communityLogoGlb(['nodes' => [['mesh' => 0, 'children' => [0]]]])] as $bad) {
        $upload($bad, 3)->assertUnprocessable()->assertJsonValidationErrors('avatar');
    }
    foreach (['buffers' => 'wrong', 'asset' => [], 'scenes' => [['nodes' => [999]]], 'nodes' => [['mesh' => [], 'children' => []]], 'meshes' => [['primitives' => [['attributes' => ['POSITION' => 0], 'targets' => 'wrong']]]], 'accessors' => [['bufferView' => [], 'componentType' => 5126, 'count' => 3, 'type' => 'VEC3']]] as $key => $bad) {
        $upload(communityLogoGlb([$key => $bad]), 3)->assertUnprocessable()->assertJsonValidationErrors('avatar');
    }
    $this->postJson($url, ['revision' => 3, 'asset_kind' => 'model', 'avatar' => UploadedFile::fake()->create('huge.glb', 5121)])->assertUnprocessable();
    $this->postJson($url, ['revision' => 3, 'panel_community_id' => $community->id + 1, 'asset_kind' => 'model', 'avatar' => UploadedFile::fake()->createWithContent('logo.glb', communityLogoGlb())])->assertUnprocessable();
    expect($page->fresh()->revision)->toBe(3);
    $foreign = CommunityLinkPage::create(['community_id' => Community::factory()->create()->id, 'handle' => 'foreign-model-'.uniqid(), 'draft_content' => ['model' => $asset]]);
    $this->get('/panel/community-links/'.$foreign->id.'/model/'.$asset)->assertNotFound();
    $this->postJson('/panel/community-links/'.$foreign->id.'/avatar', ['revision' => 1, 'asset_kind' => 'model', 'avatar' => UploadedFile::fake()->createWithContent('logo.glb', communityLogoGlb())])->assertNotFound();
    $upload(communityLogoGlb(), 3)->assertRedirect();
    $replacement = $page->fresh()->draft_content['model'];
    $this->get('/c/'.$page->handle.'/model/'.$asset)->assertOk();
    $this->get('/c/'.$page->handle.'/model/'.$replacement)->assertNotFound();
    foreach (Storage::disk('local')->files('community-links/'.$page->id) as $file) {
        touch(Storage::disk('local')->path($file), now()->subDays(2)->timestamp);
    }
    $this->artisan('community-links:cleanup-media')->assertSuccessful();
    Storage::disk('local')->assertExists('community-links/'.$page->id.'/'.$asset.'.glb');
    Storage::disk('local')->assertExists('community-links/'.$page->id.'/'.$replacement.'.glb');
    $this->get('/panel/community-links/'.$page->id.'/edit')->assertOk()->assertSee('Upload Model 3D')->assertSee('Hapus Model 3D');
    $this->post('/panel/community-links/'.$page->id.'/remove-model', ['revision' => 4])->assertRedirect()->assertSessionHasNoErrors();
    expect($page->fresh()->draft_content)->not->toHaveKey('model');
    $this->get('/c/'.$page->handle.'/model/'.$asset)->assertOk();
    $this->post('/panel/community-links/'.$page->id.'/publish', ['revision' => 5])->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/c/'.$page->handle.'/model/'.$asset)->assertNotFound();
    $page->update(['published_content' => $page->fresh()->published_content + ['model' => $asset], 'suspended_at' => now()]);
    $this->get('/c/'.$page->handle.'/model/'.$asset)->assertNotFound();
    $page->update(['suspended_at' => null]);
    config(['community_links.exclusive_communities' => []]);
    $this->get('/c/'.$page->handle.'/model/'.$asset)->assertNotFound();
    $this->get('/panel/community-links/'.$page->id.'/model/'.$replacement)->assertNotFound();
});

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
    $image = imagecreatetruecolor(16, 16);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagefilledrectangle($image, 4, 4, 11, 11, imagecolorallocatealpha($image, 255, 0, 0, 0));
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);
    $this->post($url, ['revision' => 1, 'avatar' => UploadedFile::fake()->createWithContent('logo.png', $png)])->assertRedirect()->assertSessionHasNoErrors();
    $asset = $page->fresh()->draft_content['avatar'];
    Storage::disk('local')->assertExists('community-links/'.$page->id.'/'.$asset.'.webp');
    $encoded = imagecreatefromstring(Storage::disk('local')->get('community-links/'.$page->id.'/'.$asset.'.webp'));
    expect((imagecolorat($encoded, 0, 0) >> 24) & 0x7F)->toBe(127);
    imagedestroy($encoded);
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
