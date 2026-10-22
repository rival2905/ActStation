<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $this->owner = User::factory()->create(['is_active' => true]);
    $this->owner->assignRole('community_manager');
    $this->community = Community::factory()->create(['is_active' => true, 'subtype' => 'free_association']);
    CommunityOfficial::create(['community_id' => $this->community->id, 'user_id' => $this->owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $this->actingAs($this->owner)->withSession(['current_community_id' => $this->community->id]);
    $this->createPage = fn ($handle) => $this->post('/panel/community-links', ['panel_community_id' => $this->community->id, 'title' => 'Portal Komunitas', 'handle' => $handle, 'theme_id' => 'clean-light']);
});

test('community activates multiple independent pages with one primary and explicit publication', function (): void {
    $this->get('/panel/community-links')->assertOk()->assertSee('Pilih Tema');
    $a = 'portal-'.Str::lower(Str::random(10));
    $b = 'edukasi-'.Str::lower(Str::random(10));
    ($this->createPage)($a)->assertRedirect()->assertSessionHasNoErrors();
    ($this->createPage)($b)->assertRedirect()->assertSessionHasNoErrors();
    $pages = DB::table('community_link_pages')->where('community_id', $this->community->id)->orderBy('id')->get();
    expect($pages)->toHaveCount(2)->and($pages->where('is_primary', true))->toHaveCount(1);
    $this->get('/c/'.$a)->assertNotFound();
    $this->get('/panel/community-links/'.$pages[0]->id.'/edit')->assertOk()->assertSee('Terbitkan')->assertSee('id="link-editor-data"', false)->assertSee('community-links/editor.js?v=')->assertSee('Profil & Tema')->assertSee('Opsi Lainnya');
    $category = (string) Str::uuid();
    $data = ['revision' => 1, 'panel_community_id' => $this->community->id, 'title' => 'Portal Published', 'bio' => 'Informasi umum', 'theme_id' => 'midnight', 'categories' => [['id' => $category, 'name' => 'Layanan']], 'links' => [['id' => (string) Str::uuid(), 'label' => 'Panduan Aman', 'url' => 'https://example.com/guide', 'category_id' => $category, 'visible' => true], ['id' => (string) Str::uuid(), 'label' => 'Jangan Tampil', 'url' => 'https://example.com/private', 'visible' => false]]];
    $this->put('/panel/community-links/'.$pages[0]->id, $data)->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/panel/community-links/'.$pages[0]->id.'/edit')->assertOk()->assertSee('Draft tersimpan. Publik belum berubah.');
    $this->post('/panel/community-links/'.$pages[0]->id.'/publish', ['revision' => 2])->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/c/'.$a)->assertOk()->assertSee('Panduan Aman')->assertSee('Layanan')->assertDontSee('Jangan Tampil')->assertDontSee('community_id');
    $data['revision'] = 3;
    $data['title'] = 'Draft Baru';
    $this->put('/panel/community-links/'.$pages[0]->id, $data)->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/c/'.$a)->assertOk()->assertSee('Portal Published')->assertDontSee('Draft Baru');
    $this->get('/panel/community-links/'.$pages[0]->id.'/preview')->assertOk()->assertSee('Draft Baru')->assertHeader('Cache-Control', 'no-store, private');
    $this->putJson('/panel/community-links/'.$pages[0]->id, $data)->assertConflict();
    $this->post('/panel/community-links/'.$pages[1]->id.'/primary', ['revision' => 1])->assertRedirect();
    expect(DB::table('community_link_pages')->where('community_id', $this->community->id)->where('is_primary', true)->value('id'))->toBe($pages[1]->id);
    $this->post('/panel/community-links/'.$pages[0]->id.'/unpublish', ['revision' => 4])->assertRedirect();
    $this->get('/c/'.$a)->assertNotFound();
});

test('link page management rejects foreign scope invalid URLs and unauthorized exclusive themes', function (): void {
    $handle = 'safe-'.Str::lower(Str::random(10));
    ($this->createPage)($handle)->assertRedirect()->assertSessionHasNoErrors();
    $page = DB::table('community_link_pages')->where('handle', $handle)->firstOrFail();
    $this->postJson('/panel/community-links', ['title' => 'Exclusive', 'handle' => 'exclusive-'.Str::lower(Str::random(10)), 'theme_id' => 'perinatologi-care'])->assertUnprocessable();
    $data = ['revision' => 1, 'title' => 'Safe', 'theme_id' => 'clean-light', 'links' => [['id' => (string) Str::uuid(), 'label' => 'Unsafe', 'url' => 'javascript:alert(1)', 'visible' => true]]];
    foreach (['javascript:alert(1)', 'data:text/html,unsafe', '//example.com/', 'https://user:pass@example.com/'] as $url) {
        $data['links'][0]['url'] = $url;
        $this->putJson('/panel/community-links/'.$page->id, $data)->assertUnprocessable();
    }
    $data['links'][0]['url'] = ['bad'];
    $this->putJson('/panel/community-links/'.$page->id, $data)->assertUnprocessable();
    $data['links'][0]['url'] = 'https://example.com/safe';
    $data['links'][0]['category_id'] = (string) Str::uuid();
    $this->putJson('/panel/community-links/'.$page->id, $data)->assertUnprocessable();
    $this->postJson('/panel/community-links/'.$page->id.'/publish', ['revision' => 1])->assertUnprocessable();
    $this->postJson('/panel/community-links/'.$page->id.'/primary', ['revision' => 1, 'panel_community_id' => $this->community->id + 1])->assertUnprocessable();
    $foreign = Community::factory()->create(['is_active' => true]);
    CommunityOfficial::create(['community_id' => $foreign->id, 'user_id' => $this->owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $this->withSession(['current_community_id' => $foreign->id])->get('/panel/community-links/'.$page->id.'/edit')->assertNotFound();
    $this->postJson('/panel/community-links/'.$page->id.'/publish', ['revision' => 1])->assertNotFound();
    $this->withSession(['current_community_id' => $this->community->id]);
    CommunityOfficial::where('community_id', $this->community->id)->where('user_id', $this->owner->id)->update(['ended_at' => today()]);
    $this->get('/panel/community-links/'.$page->id.'/preview')->assertNotFound();
    expect(DB::table('community_link_pages')->where('id', $page->id)->value('revision'))->toBe(1);
});

test('AJAX draft autosave returns next revision without publishing and rejects stale writes', function (): void {
    $handle = 'auto-'.Str::lower(Str::random(10));
    ($this->createPage)($handle)->assertRedirect()->assertSessionHasNoErrors();
    $page = DB::table('community_link_pages')->where('handle', $handle)->firstOrFail();
    $data = ['revision' => 1, 'title' => 'Draft otomatis', 'theme_id' => 'clean-light', 'links' => [], 'categories' => [], 'socials' => []];
    $this->putJson('/panel/community-links/'.$page->id, $data)->assertOk()->assertJsonPath('data.revision', 2)->assertJsonPath('data.message', 'Draft tersimpan. Publik belum berubah.');
    $saved = DB::table('community_link_pages')->find($page->id);
    expect(json_decode($saved->draft_content, true)['title'])->toBe('Draft otomatis')->and($saved->published_content)->toBeNull();
    $this->putJson('/panel/community-links/'.$page->id, $data)->assertConflict();
    $data['revision'] = 2;
    $data['title'] = '';
    $this->putJson('/panel/community-links/'.$page->id, $data)->assertUnprocessable()->assertJsonValidationErrors('title');
    expect(DB::table('community_link_pages')->where('id', $page->id)->value('revision'))->toBe(2);
    $link = ['id' => (string) Str::uuid(), 'label' => 'Panduan', 'url' => 'https://example.com/', 'visible' => true];
    $data['title'] = 'Judul terbit';
    $data['links'] = [$link];
    $this->putJson('/panel/community-links/'.$page->id, $data)->assertOk()->assertJsonPath('data.revision', 3);
    $this->post('/panel/community-links/'.$page->id.'/publish', ['revision' => 3])->assertRedirect()->assertSessionHasNoErrors();
    $data['revision'] = 4;
    $data['title'] = 'Draft setelah terbit';
    $this->putJson('/panel/community-links/'.$page->id, $data)->assertOk()->assertJsonPath('data.revision', 5);
    $this->get('/c/'.$handle)->assertOk()->assertSee('Judul terbit')->assertDontSee('Draft setelah terbit');
});

test('link pages accept HTTP local private and token URLs through save publish and redirect', function (): void {
    $handle = 'urls-'.Str::lower(Str::random(10));
    ($this->createPage)($handle)->assertRedirect()->assertSessionHasNoErrors();
    $page = DB::table('community_link_pages')->where('handle', $handle)->firstOrFail();
    $urls = ['http://example.com/guide', 'http://localhost:7000/panel/profile', 'https://example.com/?token=demo', 'https://actstation.net/panel/profile', 'http://127.0.0.1:7000/'];
    $links = array_map(fn ($url) => ['id' => (string) Str::uuid(), 'label' => 'Tautan', 'url' => $url, 'visible' => true], $urls);
    $this->put('/panel/community-links/'.$page->id, ['revision' => 1, 'title' => 'Portal', 'theme_id' => 'clean-light', 'links' => $links, 'socials' => [['label' => 'Kontak', 'url' => $urls[2]]]])->assertRedirect()->assertSessionHasNoErrors();
    $this->post('/panel/community-links/'.$page->id.'/publish', ['revision' => 2])->assertRedirect()->assertSessionHasNoErrors();
    foreach ($links as $link) {
        $this->get('/c/'.$handle.'/r/'.$link['id'])->assertRedirect($link['url']);
    }
});

test('published link routes record aggregate clicks only for snapshot destinations', function (): void {
    $handle = 'click-'.Str::lower(Str::random(10));
    ($this->createPage)($handle)->assertRedirect()->assertSessionHasNoErrors();
    $page = DB::table('community_link_pages')->where('handle', $handle)->firstOrFail();
    $linkId = (string) Str::uuid();
    $this->put('/panel/community-links/'.$page->id, ['revision' => 1, 'title' => 'Click Page', 'theme_id' => 'clean-light', 'links' => [['id' => $linkId, 'label' => 'Click Me', 'url' => 'https://example.com/guide', 'visible' => true]]])->assertRedirect()->assertSessionHasNoErrors();
    $this->post('/panel/community-links/'.$page->id.'/publish', ['revision' => 2])->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/panel/community-links/'.$page->id.'/preview')->assertOk();
    expect((int) DB::table('community_link_metrics')->where('community_link_page_id', $page->id)->sum('total'))->toBe(0);
    $this->get('/c/'.$handle)->assertOk()->assertSee('/c/'.$handle.'/r/'.$linkId, false);
    $this->get('/c/'.$handle.'/r/'.$linkId.'?url=https://evil.example')->assertRedirect('https://example.com/guide');
    $this->get('/c/'.$handle.'/r/'.Str::uuid())->assertNotFound();
    expect((int) DB::table('community_link_metrics')->where('community_link_page_id', $page->id)->where('item_key', 'views')->sum('total'))->toBe(1)
        ->and((int) DB::table('community_link_metrics')->where('community_link_page_id', $page->id)->where('item_key', $linkId)->sum('total'))->toBe(1);
    $this->get('/panel/community-links/'.$page->id.'/edit')->assertOk()->assertSee('Analitik')->assertSee('30 hari');
    $this->community->update(['is_active' => false]);
    $this->get('/c/'.$handle)->assertNotFound();
    $this->get('/c/'.$handle.'/r/'.$linkId)->assertNotFound();
});

test('exclusive theme requires a community grant and page creation leaves the other pages intact', function (): void {
    $data = ['title' => 'Perinatologi', 'theme_id' => 'perinatologi-care', 'handle' => 'perina-'.Str::lower(Str::random(10))];
    $this->get('/panel/community-links')->assertOk()->assertDontSee('theme_perinatologi-care');
    $this->postJson('/panel/community-links', $data)->assertUnprocessable();
    config(['community_links.exclusive_communities' => [$this->community->id]]);
    $this->get('/panel/community-links')->assertOk()->assertSee('Premium · Motion 3D');
    $this->post('/panel/community-links', $data)->assertRedirect()->assertSessionHasNoErrors();
    $premiumPage = DB::table('community_link_pages')->where('handle', $data['handle'])->firstOrFail();
    $this->get('/panel/community-links/'.$premiumPage->id.'/preview')->assertOk()->assertSee('id="hero-3d-canvas"', false)->assertSee('id="motion-toggle"', false)->assertSee('perinatologi-care.js')->assertDontSee('Simulasi Buka')
        ->assertDontSee('Portal komunitas')->assertDontSee('Kapsul Kasih')->assertDontSee('Legalitas belum terverifikasi')
        ->assertDontSee('Motion 3D:')->assertSee('Preview draft · hanya untuk Anda.');
    $this->postJson('/panel/community-links', $data)->assertUnprocessable();
    $this->postJson('/panel/community-links', ['title' => [], 'theme_id' => [], 'handle' => 'admin'])->assertUnprocessable();
    expect(DB::table('community_link_pages')->where('community_id', $this->community->id)->count())->toBe(1);
});

test('hidden categories never enter the public snapshot and theme revocation keeps links usable', function (): void {
    config(['community_links.exclusive_communities' => [$this->community->id]]);
    $handle = 'categories-'.Str::lower(Str::random(10));
    $this->post('/panel/community-links', ['title' => 'Perinatologi', 'theme_id' => 'perinatologi-care', 'handle' => $handle])->assertRedirect()->assertSessionHasNoErrors();
    $page = DB::table('community_link_pages')->where('handle', $handle)->firstOrFail();
    $categoryId = (string) Str::uuid();
    $data = ['revision' => 1, 'title' => 'Portal', 'theme_id' => 'perinatologi-care', 'categories' => [['id' => $categoryId, 'name' => 'Hidden Category', 'visible' => false]], 'links' => [
        ['id' => (string) Str::uuid(), 'label' => 'Secret Link', 'url' => 'https://example.com/hidden', 'category_id' => $categoryId, 'visible' => true],
        ['id' => (string) Str::uuid(), 'label' => 'Public Guide', 'url' => 'https://example.com/public', 'visible' => true],
    ]];
    $this->put('/panel/community-links/'.$page->id, $data)->assertRedirect()->assertSessionHasNoErrors();
    $this->post('/panel/community-links/'.$page->id.'/publish', ['revision' => 2])->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/c/'.$handle)->assertOk()->assertSee('Public Guide')->assertDontSee('Secret Link')->assertDontSee('Hidden Category');
    config(['community_links.exclusive_communities' => []]);
    $this->get('/c/'.$handle)->assertOk()->assertSee('Public Guide')->assertSee('class="clean-light"', false)->assertDontSee('perinatologi-care.js')->assertDontSee('id="hero-3d-canvas"', false);
    $this->postJson('/panel/community-links/'.$page->id.'/publish', ['revision' => 3])->assertUnprocessable();
});

test('invalid sparse editor input returns editable lists without losing entries', function (): void {
    $handle = 'sparse-'.Str::lower(Str::random(10));
    ($this->createPage)($handle)->assertRedirect();
    $page = DB::table('community_link_pages')->where('handle', $handle)->firstOrFail();
    $linkId = (string) Str::uuid();
    $this->from('/panel/community-links/'.$page->id.'/edit')->put('/panel/community-links/'.$page->id, [
        'revision' => 1, 'title' => 'Tetap utuh', 'theme_id' => 'clean-light',
        'categories' => [3 => ['id' => (string) Str::uuid(), 'name' => 'Panduan']],
        'links' => [7 => ['id' => $linkId, 'label' => 'Jangan hilang', 'url' => 'not-a-url', 'visible' => true]],
        'socials' => [4 => ['label' => 'Website', 'url' => 'https://example.com/']],
    ])->assertRedirect()->assertSessionHasErrors('links.7.url');
    $response = $this->get('/panel/community-links/'.$page->id.'/edit')->assertOk();
    preg_match('/<script type="application\/json" id="link-editor-data">(.*?)<\/script>/s', $response->getContent(), $matches);
    $data = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    foreach (['categories', 'links', 'socials'] as $key) {
        expect(array_is_list($data[$key]))->toBeTrue();
    }
    expect($data['links'][0]['id'])->toBe($linkId)->and($data['links'][0]['label'])->toBe('Jangan hilang');
});

test('stale HTML editor save retains user input and requires explicit conflict review', function (): void {
    $handle = 'conflict-'.Str::lower(Str::random(10));
    ($this->createPage)($handle)->assertRedirect();
    $page = DB::table('community_link_pages')->where('handle', $handle)->firstOrFail();
    $data = ['revision' => 1, 'title' => 'Perubahan tab pertama', 'bio' => '', 'theme_id' => 'clean-light', 'links' => []];
    $this->put('/panel/community-links/'.$page->id, $data)->assertRedirect();
    $data['title'] = 'Isian tab lama jangan hilang';
    $this->put('/panel/community-links/'.$page->id, $data)->assertRedirect('/panel/community-links/'.$page->id.'/edit')->assertSessionHasErrors('revision')->assertSessionHasInput('title', $data['title']);
    $this->get('/panel/community-links/'.$page->id.'/edit')->assertOk()->assertSee('Isian tab lama jangan hilang')->assertSee('Perubahan tab pertama')->assertSee('Tinjau konflik revisi')->assertSee('name="conflict_reviewed"', false);
    expect(json_decode(DB::table('community_link_pages')->where('id', $page->id)->value('draft_content'), true)['title'])->toBe('Perubahan tab pertama');
    $this->putJson('/panel/community-links/'.$page->id, $data)->assertConflict();
    $data['revision'] = 2;
    $data['conflict_reviewed'] = 1;
    $this->put('/panel/community-links/'.$page->id, $data)->assertRedirect()->assertSessionHasNoErrors();
    expect(json_decode(DB::table('community_link_pages')->where('id', $page->id)->value('draft_content'), true)['title'])->toBe('Isian tab lama jangan hilang');
});
