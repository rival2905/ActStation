<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\RegistrationDraft;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

test('organizer can view draft teams tab in event teams page', function (): void {
    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web', 'panel_access' => 'external']);
    $organizer = User::factory()->create(['is_active' => true]);
    $organizer->assignRole('organizer');

    $event = Event::factory()->create([
        'user_id' => $organizer->id,
        'status' => 'published',
    ]);

    $category = EventCategory::factory()->create([
        'event_id' => $event->id,
        'name' => 'Kategori Uji',
    ]);

    $community = Community::factory()->create([
        'name' => 'Klub Uji Basket',
        'is_active' => true,
    ]);

    $draft = RegistrationDraft::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'community_id' => $community->id,
        'user_id' => $organizer->id,
        'payload' => [
            'team_name' => 'Tim Harimau Draft',
            'contact_person' => 'Budi PIC',
            'contact_phone' => '08123456789',
            'members' => [
                ['user_id' => $organizer->id],
            ],
        ],
        'revision' => 1,
        'expires_at' => now()->addWeek(),
    ]);

    $response = $this->actingAs($organizer)
        ->get(route('panel.organizer.teams.index', ['event' => $event->id, 'tab' => 'drafts']));

    $response->assertOk()
        ->assertSee('Draft Pendaftaran')
        ->assertSee('Daftar Tim Status Draft')
        ->assertSee('Tim Harimau Draft')
        ->assertSee('/panel/organizer/events/'.$event->id.'/team-inspections/draft/'.$draft->id, false)
        ->assertSee('Budi PIC')
        ->assertSee('Sedang Mengisi');
});
