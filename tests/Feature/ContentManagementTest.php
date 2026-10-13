<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Content\Models\Announcement;
use App\Modules\Content\Models\Article;
use App\Modules\Content\Models\ArticleCategory;
use App\Modules\Content\Models\Banner;
use App\Modules\Content\Models\Faq;
use App\Modules\Content\Models\Gallery;
use App\Modules\Content\Models\GalleryMedia;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['is_active' => true]);
    $this->staff->assignRole('staff');

    $this->category = ArticleCategory::create([
        'name' => 'Umum',
        'slug' => 'umum',
    ]);
});

test('html sanitizer strips script, onerror, and javascript pseudo protocol from article body', function (): void {
    $dirtyHtml = '<p class="lead">Safe Text</p><script>alert("xss")</script><img src="https://example.com/img.png" onerror="alert(1)" alt="Image" /><a href="javascript:alert(2)">Danger</a><a href="https://perbasi.org">Safe Link</a>';

    $response = $this->actingAs($this->admin)
        ->post(route('admin.articles.store'), [
            'title' => 'Artikel Keamanan',
            'category_id' => $this->category->id,
            'body' => $dirtyHtml,
            'status' => 'draft',
        ]);

    $response->assertRedirect(route('admin.articles.index'));

    $article = Article::where('title', 'Artikel Keamanan')->firstOrFail();

    expect($article->body)
        ->not->toContain('<script')
        ->not->toContain('alert(')
        ->not->toContain('onerror')
        ->not->toContain('javascript:')
        ->toContain('Safe Text')
        ->toContain('https://perbasi.org');
});

test('article slug must be unique and duplicate slug is rejected', function (): void {
    Article::create([
        'user_id' => $this->admin->id,
        'category_id' => $this->category->id,
        'title' => 'Artikel Pertama',
        'slug' => 'berita-utama',
        'body' => '<p>Konten artikel</p>',
        'status' => 'draft',
    ]);

    $response = $this->actingAs($this->admin)
        ->from(route('admin.articles.create'))
        ->post(route('admin.articles.store'), [
            'title' => 'Artikel Kedua Duplikat',
            'slug' => 'berita-utama',
            'category_id' => $this->category->id,
            'body' => '<p>Konten artikel lain</p>',
            'status' => 'draft',
        ]);

    $response->assertRedirect(route('admin.articles.create'));
    $response->assertSessionHasErrors('slug');
});

test('published_at is filled on first publish and not overwritten on subsequent edits', function (): void {
    $now = Carbon::parse('2026-10-09 10:00:00');
    Carbon::setTestNow($now);

    $this->actingAs($this->admin)
        ->post(route('admin.articles.store'), [
            'title' => 'Artikel Publish Otomatis',
            'category_id' => $this->category->id,
            'body' => '<p>Konten rilis</p>',
            'status' => 'published',
            'published_at' => null,
        ]);

    $article = Article::where('title', 'Artikel Publish Otomatis')->firstOrFail();
    expect($article->published_at?->format('Y-m-d H:i:s'))->toBe('2026-10-09 10:00:00');

    // Edit 5 hari kemudian tanpa mengisi published_at baru
    Carbon::setTestNow(Carbon::parse('2026-10-14 15:30:00'));

    $this->actingAs($this->admin)
        ->put(route('admin.articles.update', $article), [
            'title' => 'Artikel Publish Otomatis (Revisi)',
            'slug' => $article->slug,
            'category_id' => $this->category->id,
            'body' => '<p>Konten revisi</p>',
            'status' => 'published',
            'published_at' => null,
        ]);

    $article->refresh();
    expect($article->title)->toBe('Artikel Publish Otomatis (Revisi)')
        ->and($article->published_at?->format('Y-m-d H:i:s'))->toBe('2026-10-09 10:00:00');

    Carbon::setTestNow();
});

test('banner active scope respects active flag and time boundaries', function (): void {
    $now = Carbon::parse('2026-10-09 12:00:00');
    Carbon::setTestNow($now);

    $bannerAlwaysActive = Banner::create([
        'title' => 'Banner Selalu Aktif',
        'image' => 'banners/1.jpg',
        'is_active' => true,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    $bannerCurrentlyActive = Banner::create([
        'title' => 'Banner Rentang Aktif',
        'image' => 'banners/2.jpg',
        'is_active' => true,
        'starts_at' => $now->copy()->subDays(2),
        'ends_at' => $now->copy()->addDays(2),
    ]);

    $bannerScheduledFuture = Banner::create([
        'title' => 'Banner Belum Mulai',
        'image' => 'banners/3.jpg',
        'is_active' => true,
        'starts_at' => $now->copy()->addHour(),
        'ends_at' => $now->copy()->addDays(3),
    ]);

    $bannerExpired = Banner::create([
        'title' => 'Banner Kadaluarsa',
        'image' => 'banners/4.jpg',
        'is_active' => true,
        'starts_at' => $now->copy()->subDays(5),
        'ends_at' => $now->copy()->subHour(),
    ]);

    $bannerInactive = Banner::create([
        'title' => 'Banner Dinonaktifkan',
        'image' => 'banners/5.jpg',
        'is_active' => false,
        'starts_at' => $now->copy()->subDays(1),
        'ends_at' => $now->copy()->addDays(1),
    ]);

    $activeIds = Banner::active()->pluck('id')->all();

    expect($activeIds)
        ->toContain($bannerAlwaysActive->id)
        ->toContain($bannerCurrentlyActive->id)
        ->not->toContain($bannerScheduledFuture->id)
        ->not->toContain($bannerExpired->id)
        ->not->toContain($bannerInactive->id);

    Carbon::setTestNow();
});

test('staff role is forbidden on create actions across content modules', function (): void {
    // Article create
    $this->actingAs($this->staff)
        ->get(route('admin.articles.create'))
        ->assertForbidden();

    $this->actingAs($this->staff)
        ->post(route('admin.articles.store'), [
            'title' => 'Illegal Post',
            'body' => '<p>Bad</p>',
            'status' => 'draft',
        ])
        ->assertForbidden();

    // Article Category store
    $this->actingAs($this->staff)
        ->post(route('admin.article-categories.store'), [
            'name' => 'Illegal Cat',
            'slug' => 'illegal-cat',
        ])
        ->assertForbidden();

    // Banner create
    $this->actingAs($this->staff)
        ->get(route('admin.banners.create'))
        ->assertForbidden();

    $this->actingAs($this->staff)
        ->post(route('admin.banners.store'), [
            'title' => 'Illegal Banner',
        ])
        ->assertForbidden();

    // Announcement create
    $this->actingAs($this->staff)
        ->get(route('admin.announcements.create'))
        ->assertForbidden();

    $this->actingAs($this->staff)
        ->post(route('admin.announcements.store'), [
            'title' => 'Illegal Announcement',
            'body' => 'Body',
            'type' => 'info',
        ])
        ->assertForbidden();

    // FAQ create
    $this->actingAs($this->staff)
        ->get(route('admin.faqs.create'))
        ->assertForbidden();

    $this->actingAs($this->staff)
        ->post(route('admin.faqs.store'), [
            'question' => 'Q?',
            'answer' => 'A',
        ])
        ->assertForbidden();

    // Gallery create
    $this->actingAs($this->staff)
        ->get(route('admin.galleries.create'))
        ->assertForbidden();

    $this->actingAs($this->staff)
        ->post(route('admin.galleries.store'), [
            'title' => 'Illegal Gallery',
        ])
        ->assertForbidden();
});

test('uploading svg file is rejected on image fields', function (): void {
    Storage::fake('public');

    $svgFile = UploadedFile::fake()->create('exploit.svg', 50, 'image/svg+xml');

    // Article featured_image
    $this->actingAs($this->admin)
        ->from(route('admin.articles.create'))
        ->post(route('admin.articles.store'), [
            'title' => 'Artikel Gambar SVG',
            'category_id' => $this->category->id,
            'body' => '<p>Konten</p>',
            'status' => 'draft',
            'featured_image' => $svgFile,
        ])
        ->assertSessionHasErrors('featured_image');

    // Banner image
    $this->actingAs($this->admin)
        ->from(route('admin.banners.create'))
        ->post(route('admin.banners.store'), [
            'title' => 'Banner SVG',
            'image' => $svgFile,
        ])
        ->assertSessionHasErrors('image');

    // Gallery media photo
    $gallery = Gallery::create(['title' => 'Galeri Foto']);

    $this->actingAs($this->admin)
        ->from(route('admin.galleries.show', $gallery))
        ->post(route('admin.galleries.media.store', $gallery), [
            'type' => 'photo',
            'file' => $svgFile,
        ])
        ->assertSessionHasErrors('file');
});

test('gallery media video url from non-whitelist host is rejected while youtube and vimeo are accepted', function (): void {
    $gallery = Gallery::create(['title' => 'Galeri Video']);

    // Non-whitelisted host
    $this->actingAs($this->admin)
        ->from(route('admin.galleries.show', $gallery))
        ->post(route('admin.galleries.media.store', $gallery), [
            'type' => 'video',
            'video_url' => 'https://malicious-video-site.com/watch?v=123',
        ])
        ->assertSessionHasErrors('video_url');

    // Whitelisted YouTube host
    $this->actingAs($this->admin)
        ->from(route('admin.galleries.show', $gallery))
        ->post(route('admin.galleries.media.store', $gallery), [
            'type' => 'video',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])
        ->assertRedirect(route('admin.galleries.show', $gallery))
        ->assertSessionHasNoErrors();

    // Whitelisted Vimeo host
    $this->actingAs($this->admin)
        ->from(route('admin.galleries.show', $gallery))
        ->post(route('admin.galleries.media.store', $gallery), [
            'type' => 'video',
            'video_url' => 'https://vimeo.com/76979871',
        ])
        ->assertRedirect(route('admin.galleries.show', $gallery))
        ->assertSessionHasNoErrors();

    expect(GalleryMedia::where('gallery_id', $gallery->id)->count())->toBe(2);
});

test('soft-deleted article remains in database with deleted_at set', function (): void {
    $article = Article::create([
        'user_id' => $this->admin->id,
        'category_id' => $this->category->id,
        'title' => 'Artikel Akan Dihapus',
        'slug' => 'artikel-akan-dihapus',
        'body' => '<p>Akan segera dihapus secara lembut.</p>',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->delete(route('admin.articles.destroy', $article))
        ->assertRedirect(route('admin.articles.index'));

    $this->assertSoftDeleted('articles', [
        'id' => $article->id,
        'title' => 'Artikel Akan Dihapus',
    ]);

    expect(Article::find($article->id))->toBeNull()
        ->and(Article::withTrashed()->find($article->id))->not->toBeNull()
        ->and(Article::withTrashed()->find($article->id)?->deleted_at)->not->toBeNull();
});

test('announcement active scope respects active flag and date limits', function (): void {
    $now = Carbon::parse('2026-10-09 08:00:00');
    Carbon::setTestNow($now);

    $announcementAlways = Announcement::create([
        'user_id' => $this->admin->id,
        'title' => 'Pengumuman Selamanya',
        'body' => 'Pengumuman selalu tampil',
        'type' => 'info',
        'is_active' => true,
        'published_at' => null,
        'expires_at' => null,
    ]);

    $announcementValid = Announcement::create([
        'user_id' => $this->admin->id,
        'title' => 'Pengumuman Berlaku',
        'body' => 'Pengumuman periode aktif',
        'type' => 'warning',
        'is_active' => true,
        'published_at' => $now->copy()->subHours(2),
        'expires_at' => $now->copy()->addHours(5),
    ]);

    $announcementFuture = Announcement::create([
        'user_id' => $this->admin->id,
        'title' => 'Pengumuman Mendatang',
        'body' => 'Pengumuman belum berlaku',
        'type' => 'urgent',
        'is_active' => true,
        'published_at' => $now->copy()->addHours(1),
        'expires_at' => $now->copy()->addDays(1),
    ]);

    $announcementExpired = Announcement::create([
        'user_id' => $this->admin->id,
        'title' => 'Pengumuman Habis',
        'body' => 'Pengumuman expired',
        'type' => 'info',
        'is_active' => true,
        'published_at' => $now->copy()->subDays(2),
        'expires_at' => $now->copy()->subHour(),
    ]);

    $announcementInactive = Announcement::create([
        'user_id' => $this->admin->id,
        'title' => 'Pengumuman Nonaktif',
        'body' => 'Pengumuman disabled',
        'type' => 'info',
        'is_active' => false,
        'published_at' => $now->copy()->subHour(),
        'expires_at' => $now->copy()->addHour(),
    ]);

    $activeIds = Announcement::active()->pluck('id')->all();

    expect($activeIds)
        ->toContain($announcementAlways->id)
        ->toContain($announcementValid->id)
        ->not->toContain($announcementFuture->id)
        ->not->toContain($announcementExpired->id)
        ->not->toContain($announcementInactive->id);

    Carbon::setTestNow();
});

test('deleting media of gallery B via gallery A route returns 404 and preserves media', function (): void {
    $galleryA = Gallery::create(['title' => 'Galeri A']);
    $galleryB = Gallery::create(['title' => 'Galeri B']);

    $mediaB = $galleryB->media()->create([
        'type' => 'video',
        'file_path' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'caption' => 'Video Galeri B',
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($this->admin)
        ->delete(route('admin.galleries.media.destroy', [
            'gallery' => $galleryA,
            'media' => $mediaB,
        ]));

    $response->assertNotFound();

    expect(GalleryMedia::find($mediaB->id))->not->toBeNull();
});

test('updating article without new file preserves existing featured_image', function (): void {
    $article = Article::create([
        'user_id' => $this->admin->id,
        'category_id' => $this->category->id,
        'title' => 'Artikel Berkas Tetap',
        'slug' => 'artikel-berkas-tetap',
        'body' => '<p>Konten artikel awal</p>',
        'featured_image' => 'articles/existing-photo.jpg',
        'status' => 'draft',
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.articles.update', $article), [
            'title' => 'Artikel Berkas Tetap (Diperbarui)',
            'slug' => $article->slug,
            'category_id' => $this->category->id,
            'body' => '<p>Konten diperbarui</p>',
            'status' => 'draft',
        ])
        ->assertRedirect(route('admin.articles.index'));

    $article->refresh();

    expect($article->title)->toBe('Artikel Berkas Tetap (Diperbarui)')
        ->and($article->featured_image)->toBe('articles/existing-photo.jpg');
});

test('article category index search filters by name and slug and passes filters', function (): void {
    ArticleCategory::create(['name' => 'Basketball Event', 'slug' => 'basketball-event']);
    ArticleCategory::create(['name' => 'Pelatihan Wasit', 'slug' => 'pelatihan-wasit']);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.article-categories.index', ['search' => 'wasit']));

    $response->assertOk();
    $response->assertSee('Pelatihan Wasit');
    $response->assertDontSee('Basketball Event');
    $response->assertViewHas('filters', ['search' => 'wasit']);
});

test('banner index search includes url in search query', function (): void {
    Banner::create([
        'title' => 'Promo Spesial',
        'image' => 'banners/promo.jpg',
        'url' => 'https://perbasi.org/special-link',
        'is_active' => true,
    ]);
    Banner::create([
        'title' => 'Diskon Lain',
        'image' => 'banners/diskon.jpg',
        'url' => 'https://other-site.com',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.banners.index', ['search' => 'special-link']));

    $response->assertOk();
    $response->assertSee('Promo Spesial');
    $response->assertDontSee('Diskon Lain');
});

test('updating banner without new file preserves existing image', function (): void {
    $banner = Banner::create([
        'title' => 'Banner Lama',
        'image' => 'banners/old.jpg',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.banners.update', $banner), [
            'title' => 'Banner Baru',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.banners.index'));

    $banner->refresh();

    expect($banner->title)->toBe('Banner Baru')
        ->and($banner->image)->toBe('banners/old.jpg');
});

test('updating gallery without new file preserves existing cover_image', function (): void {
    $gallery = Gallery::create([
        'title' => 'Galeri Lama',
        'cover_image' => 'galleries/old-cover.jpg',
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.galleries.update', $gallery), [
            'title' => 'Galeri Baru',
        ])
        ->assertRedirect(route('admin.galleries.index'));

    $gallery->refresh();

    expect($gallery->title)->toBe('Galeri Baru')
        ->and($gallery->cover_image)->toBe('galleries/old-cover.jpg');
});
