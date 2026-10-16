<?php

declare(strict_types=1);

use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\MasterData\Models\DocumentType;
use Database\Seeders\DemoSmaPutraDraftSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

test('eight demo school drafts are idempotent and each can submit with staged evidence', function (): void {
    Http::preventStrayRequests();
    Storage::fake('local');
    $this->mock(EmailDelivery::class)->shouldReceive('send')->andReturn(0);
    $owner = User::factory()->create(['is_active' => true]);
    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $owner->assignRole('organizer');
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $event = Event::factory()->create(['user_id' => $owner->id, 'status' => 'published', 'registration_open' => true, 'registration_deadline' => null, 'max_participants' => null, 'starts_at' => now()->addMonth()]);
    foreach (['demo_birth' => 'athlete', 'demo_photo' => 'athlete', 'demo_team' => 'team', 'demo_school' => 'team'] as $code => $scope) {
        DocumentType::create(['code' => $code, 'name' => $code, 'scope' => $scope, 'is_active' => true]);
    }
    $category = EventCategory::factory()->create(['event_id' => $event->id, 'name' => 'Pelajar SMA Putra', 'gender' => 'male', 'min_age' => 16, 'max_age' => 18, 'min_roster' => 5, 'max_roster' => 12, 'registration_flow' => 'screening_first', 'registration_fee' => 475000, 'allow_community' => true, 'allowed_community_subtypes' => ['school_sma'], 'required_entity_documents' => [], 'required_documents' => ['demo_birth', 'demo_photo', 'demo_team', 'demo_school']]);
    $seeder = app(DemoSmaPutraDraftSeeder::class);
    $seeder->run($event->id, $category->id);
    $seeder->run($event->id, $category->id);
    $drafts = RegistrationDraft::where('event_id', $event->id)->where('event_category_id', $category->id)->get();
    expect($drafts)->toHaveCount(8);
    foreach ($drafts as $draft) {
        expect($draft->isExpired())->toBeFalse()->and($draft->isSubmitted())->toBeFalse()->and($draft->payload['members'])->toHaveCount(8);
        $this->actingAs($owner)->withSession(['current_community_id' => $draft->community_id])
            ->get(route('panel.community-events.create', ['slug' => $event->slug, 'draft_id' => $draft->id]))->assertOk()->assertSee('Daftarkan Tim Ini');
        $this->post(route('panel.community-events.store', $event->slug), array_merge($draft->payload, ['draft_id' => $draft->id, 'draft_revision' => $draft->revision, 'panel_community_id' => $draft->community_id]))
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        expect($draft->fresh()->participant_id)->not->toBeNull();
    }
    expect($event->participants()->where('event_category_id', $category->id)->count())->toBe(8);
});
