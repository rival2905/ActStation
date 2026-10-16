<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->organizer = User::factory()->create([
        'kyc_status' => 'verified',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'Organizer ActStation',
    ]);
    $this->organizer->assignRole('organizer');

    $this->event = Event::factory()->create([
        'user_id' => $this->organizer->id,
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(12),
        'registration_deadline' => now()->addDays(8),
    ]);
});

test('workspace overview explains next actions without false readiness or escrow guarantees', function (): void {
    $this->event->update(['status' => 'draft']);
    $this->organizer->update(['kyc_status' => 'pending']);
    $this->actingAs($this->organizer)->get(route('panel.organizer.events.manage', $this->event))
        ->assertOk()->assertSee('Langkah berikutnya')->assertSee('Persiapan event')
        ->assertSee('Belum ada kategori')->assertSee('Menunggu pemeriksaan identitas')
        ->assertSee('Transfer langsung ke penyelenggara tidak termasuk escrow platform.')
        ->assertDontSee('Garansi Escrow 100%')->assertDontSee('Anti-Fraud Guard')
        ->assertDontSee('publicSwitch.checked = true', false)
        ->assertSee('aria-label="Navigasi workspace event"', false);
});

test('workspace publishes only through explicit confirmation and hides unpublished public link', function (): void {
    $this->event->update(['status' => 'draft']);
    EventCategory::factory()->create(['event_id' => $this->event->id]);
    $response = $this->actingAs($this->organizer)->get(route('panel.organizer.events.manage', $this->event))->assertOk();
    $response->assertSee('id="submitEventReviewModal"', false)
        ->assertSee('id="confirmEventReview" type="checkbox" required', false)
        ->assertSee(route('panel.organizer.events.submitReview', $this->event))
        ->assertDontSee('href="'.route('events.show', $this->event->slug).'"', false);
    expect($this->event->fresh()->status)->toBe('draft');
    $this->event->update(['status' => 'pending_approval']);
    $this->get(route('panel.organizer.events.manage', $this->event))->assertOk()
        ->assertSee('Pengajuan sedang diperiksa admin')->assertDontSee('id="submitEventReviewModal"', false);
});

test('ticket summary labels price-based estimate rather than escrow balance', function (): void {
    $this->actingAs($this->organizer)->get(route('panel.organizer.ticketing.index', $this->event))
        ->assertOk()->assertSee('Estimasi harga saat ini × tiket terjual; bukan saldo escrow.')
        ->assertDontSee('Aman di Rekening Escrow Platform');
});

test('document tab keeps publication explicit and does not equate upload with approval', function (): void {
    $this->actingAs($this->organizer)->get(route('panel.organizer.documents.index', $this->event))
        ->assertOk()->assertDontSee('publicSwitch.checked = true', false)
        ->assertSee('Berkas tersedia belum berarti disetujui')
        ->assertSee('Dokumen tetap internal kecuali Anda memilih akses publik.');
});

test('organizer melihat 10 tab navigasi workspace di seluruh sub-menu event', function (string $routeName, string $activeTab): void {
    $expectedTabs = [
        'Ringkasan event',
        'Kategori',
        'Dokumen Event',
        'Tim Peserta',
        'Bagan Pertandingan',
        'Jadwal Pertandingan',
        'Hasil & Skor',
        'Tiket Penonton',
        'Scan Tiket',
        'Dana & Pencairan',
    ];

    $response = $this->actingAs($this->organizer)
        ->get(route($routeName, $this->event));

    $response->assertOk();
    $response->assertSee(route($routeName, $this->event));
    $dom = new \DOMDocument;
    @$dom->loadHTML($response->getContent());
    $active = (new \DOMXPath($dom))->query('//nav[@aria-label="Navigasi workspace event"]//a[@aria-current="page"]');
    expect($active->length)->toBe(1)
        ->and($active->item(0)->getAttribute('href'))->toBe(route($routeName, $this->event));

    foreach ($expectedTabs as $tab) {
        $response->assertSeeText($tab);
        $response->assertSee($tab);
    }
})->with([
    'Ringkasan event' => ['panel.organizer.events.manage', 'overview'],
    'Personel' => ['panel.organizer.events.personnel.edit', 'personnel'],
    'Panitia' => ['panel.organizer.committee.index', 'committee'],
    'Kategori' => ['panel.organizer.categories.index', 'categories'],
    'Dokumen Event' => ['panel.organizer.documents.index', 'documents'],
    'Tim Peserta' => ['panel.organizer.teams.index', 'teams'],
    'Bagan Pertandingan' => ['panel.organizer.tournament.index', 'tournament'],
    'Jadwal Pertandingan' => ['panel.organizer.scheduling.index', 'scheduling'],
    'Hasil & Skor' => ['panel.organizer.results.index', 'results'],
    'Tiket Penonton' => ['panel.organizer.ticketing.index', 'ticketing'],
    'Scan Tiket' => ['panel.organizer.gate-scanner.index', 'gate_scanner'],
    'Dana & Pencairan' => ['panel.organizer.payouts.index', 'payouts'],
]);
