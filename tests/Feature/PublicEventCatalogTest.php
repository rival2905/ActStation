<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\MasterData\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->sportA = SportCategory::create([
        'name' => 'Basketball',
        'slug' => 'basketball',
        'is_active' => true,
    ]);

    $this->sportB = SportCategory::create([
        'name' => 'Futsal',
        'slug' => 'futsal',
        'is_active' => true,
    ]);

    $this->user = User::factory()->create();

    // Wilayah
    DB::table('wilayah')->insert([
        ['kode' => '32', 'nama' => 'JAWA BARAT'],
        ['kode' => '31', 'nama' => 'DKI JAKARTA'],
        ['kode' => '32.73', 'nama' => 'KOTA BANDUNG'],
        ['kode' => '31.71', 'nama' => 'KOTA JAKARTA SELATAN'],
    ]);

    $this->venueBandung = Venue::create([
        'name' => 'GOR C-Tra Arena Bandung',
        'province_id' => '32',
        'city_id' => '32.73',
        'is_active' => true,
    ]);

    $this->venueJakarta = Venue::create([
        'name' => 'GOR Bulungan Jakarta',
        'province_id' => '31',
        'city_id' => '31.71',
        'is_active' => true,
    ]);

    $this->eventSoon = Event::factory()->create([
        'sport_category_id' => $this->sportA->id,
        'venue_id' => $this->venueBandung->id,
        'user_id' => $this->user->id,
        'title' => 'Bandung Basketball Cup 2026',
        'slug' => 'bandung-basketball-cup-2026',
        'status' => 'published',
        'starts_at' => Carbon::now()->addDays(5),
        'ends_at' => Carbon::now()->addDays(8),
    ]);

    $this->eventLater = Event::factory()->create([
        'sport_category_id' => $this->sportB->id,
        'venue_id' => $this->venueJakarta->id,
        'user_id' => $this->user->id,
        'title' => 'Jakarta Futsal Championship 2026',
        'slug' => 'jakarta-futsal-championship-2026',
        'status' => 'published',
        'starts_at' => Carbon::now()->addDays(30),
        'ends_at' => Carbon::now()->addDays(35),
    ]);
});

test('public events catalog sorts by execution date ascending by default and descending when requested', function (): void {
    // Default date_asc: eventSoon (starts +5d) appears before eventLater (starts +30d)
    $responseAsc = $this->get(route('events.index', ['sort' => 'date_asc']));
    $responseAsc->assertOk();
    $eventsAsc = $responseAsc->viewData('events');
    expect($eventsAsc->first()->id)->toBe($this->eventSoon->id)
        ->and($eventsAsc->last()->id)->toBe($this->eventLater->id);

    // date_desc: eventLater appears first
    $responseDesc = $this->get(route('events.index', ['sort' => 'date_desc']));
    $responseDesc->assertOk();
    $eventsDesc = $responseDesc->viewData('events');
    expect($eventsDesc->first()->id)->toBe($this->eventLater->id)
        ->and($eventsDesc->last()->id)->toBe($this->eventSoon->id);
});

test('public events catalog filters by province and city', function (): void {
    // Filter province 32 (Jawa Barat)
    $responseJabar = $this->get(route('events.index', ['province_id' => '32']));
    $responseJabar->assertOk();
    $eventsJabar = $responseJabar->viewData('events');
    expect($eventsJabar)->toHaveCount(1)
        ->and($eventsJabar->first()->id)->toBe($this->eventSoon->id);

    // Filter city 31.71 (Jakarta Selatan)
    $responseJakarta = $this->get(route('events.index', ['city_id' => '31.71']));
    $responseJakarta->assertOk();
    $eventsJakarta = $responseJakarta->viewData('events');
    expect($eventsJakarta)->toHaveCount(1)
        ->and($eventsJakarta->first()->id)->toBe($this->eventLater->id);
});

test('public events catalog renders falcon sort and wilayah controls in the view', function (): void {
    $response = $this->get(route('events.index'));
    $response->assertOk();
    $response->assertSee('Tanggal Terdekat');
    $response->assertSee('Tanggal Terjauh');
    $response->assertSee('Semua Wilayah');
    $response->assertSee('JAWA BARAT');
    $response->assertSee('DKI JAKARTA');
});
