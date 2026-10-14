<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\MasterData\Models\Venue;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->venue = Venue::create([
        'name' => 'GOR Tri Lomba Juang Bandung',
        'address' => 'Jl. Pajajaran No. 37, Bandung',
        'is_active' => true,
    ]);

    $this->event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'venue_id' => $this->venue->id,
        'title' => 'Kejurda Basket Ramah SEO 2026',
        'slug' => 'kejurda-basket-ramah-seo-2026',
        'description' => 'Turnamen resmi basket regional Jawa Barat dengan sistem proteksi rekening bersama Escrow.',
        'status' => 'published',
        'registration_open' => true,
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(15),
    ]);

    EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Putra',
        'registration_fee' => 750000,
    ]);
});

test('landing page publik memiliki meta tag SEO lengkap, OpenGraph, dan schema WebSite', function (): void {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee('<meta name="description"', false)
        ->assertSee('<meta name="keywords"', false)
        ->assertSee('<link rel="canonical"', false)
        ->assertSee('<meta property="og:site_name"', false)
        ->assertSee('<meta property="og:title"', false)
        ->assertSee('<meta name="twitter:card"', false)
        ->assertSee('"@type":"WebSite"', false)
        ->assertSee('"@type":"SportsOrganization"', false)
        ->assertSee('<h1', false);
});

test('katalog turnamen publik memiliki heading h1, meta description, dan schema CollectionPage', function (): void {
    $response = $this->get(route('events.index'));

    $response->assertOk()
        ->assertSee('<h1 class="h2 fw-bolder mb-1">Turnamen Olahraga Terbuka</h1>', false)
        ->assertSee('<meta name="description"', false)
        ->assertSee('<link rel="canonical"', false)
        ->assertSee('"@type":"CollectionPage"', false)
        ->assertSee('Kejurda Basket Ramah SEO 2026');
});

test('detail turnamen publik memiliki heading h1, meta description spesifik, dan schema SportsEvent lengkap', function (): void {
    $response = $this->get(route('events.show', $this->event->slug));

    $response->assertOk()
        ->assertSee('<h1 class="h2 fw-bolder mb-2">Kejurda Basket Ramah SEO 2026</h1>', false)
        ->assertSee('<meta name="description"', false)
        ->assertSee('Turnamen resmi basket regional Jawa Barat')
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('"@type":"SportsEvent"', false)
        ->assertSee('"name":"Kejurda Basket Ramah SEO 2026"', false)
        ->assertSee('"@type":"Place"', false)
        ->assertSee('GOR Tri Lomba Juang Bandung');
});

test('endpoint sitemap xml publik mengembalikan daftar url valid dan berstruktur xml', function (): void {
    $response = $this->get(route('sitemap'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
        ->assertSee(route('landing'), false)
        ->assertSee(route('events.index'), false)
        ->assertSee(route('panduan.event'), false)
        ->assertSee(route('events.show', $this->event->slug), false)
        ->assertSee(route('events.tournament', $this->event->slug), false)
        ->assertSee(route('events.schedule', $this->event->slug), false)
        ->assertSee(route('events.results', $this->event->slug), false)
        ->assertSee(route('events.tickets', $this->event->slug), false);
});

test('halaman sub-tab turnamen publik memiliki judul h1 dan meta unik', function (): void {
    $this->get(route('events.tournament', $this->event->slug))
        ->assertOk()
        ->assertSee('<h1 class="h2 fw-bolder mb-1">Kejurda Basket Ramah SEO 2026</h1>', false)
        ->assertSee('<meta name="description"', false);

    $this->get(route('events.schedule', $this->event->slug))
        ->assertOk()
        ->assertSee('<h1 class="h2 fw-bolder mb-1">Kejurda Basket Ramah SEO 2026</h1>', false);

    $this->get(route('events.results', $this->event->slug))
        ->assertOk()
        ->assertSee('<h1 class="h2 fw-bolder mb-1">Kejurda Basket Ramah SEO 2026</h1>', false);

    $this->get(route('events.tickets', $this->event->slug))
        ->assertOk()
        ->assertSee('<h1 class="h2 fw-bolder mb-1">Kejurda Basket Ramah SEO 2026</h1>', false);
});
