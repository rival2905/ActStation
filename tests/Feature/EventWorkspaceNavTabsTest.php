<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Event\Models\Event;
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

test('organizer melihat 10 tab navigasi workspace di seluruh sub-menu event', function (string $routeName, string $activeTab): void {
    $expectedTabs = [
        'Overview',
        'Kategori',
        'Dokumen Event',
        'Tim Peserta',
        'Bagan / Tournament',
        'Jadwal Pertandingan',
        'Hasil Skor & Live',
        'Tiket Penonton',
        'Gate Scanner',
        'Escrow & Payout',
    ];

    $response = $this->actingAs($this->organizer)
        ->get(route($routeName, $this->event));

    $response->assertOk();
    $response->assertSee(route($routeName, $this->event));
    $response->assertSee('nav-link active fw-bold', false);

    foreach ($expectedTabs as $tab) {
        $response->assertSeeText($tab);
        $response->assertSee($tab);
    }
})->with([
    'Overview' => ['panel.organizer.events.manage', 'overview'],
    'Kategori' => ['panel.organizer.categories.index', 'categories'],
    'Dokumen Event' => ['panel.organizer.documents.index', 'documents'],
    'Tim Peserta' => ['panel.organizer.teams.index', 'teams'],
    'Bagan / Tournament' => ['panel.organizer.tournament.index', 'tournament'],
    'Jadwal Pertandingan' => ['panel.organizer.scheduling.index', 'scheduling'],
    'Hasil Skor & Live' => ['panel.organizer.results.index', 'results'],
    'Tiket Penonton' => ['panel.organizer.ticketing.index', 'ticketing'],
    'Gate Scanner' => ['panel.organizer.gate-scanner.index', 'gate_scanner'],
    'Escrow & Payout' => ['panel.organizer.payouts.index', 'payouts'],
]);
