<?php

declare(strict_types=1);

use App\Contracts\CommunityLookup;
use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventAnswer;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\Event\Services\EventQuestionService;
use App\Modules\Event\Services\RegistrationAdditionalInformationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->spy(EmailDelivery::class);
});

test('additional answers share institution state without sharing team answers or consuming registrations', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $event = Event::factory()->create(['user_id' => $user->id, 'status' => 'published']);
    $school = Community::factory()->create(['subtype' => 'school', 'status' => 'verified', 'is_active' => true]);
    CommunityOfficial::factory()->create(['community_id' => $school->id, 'user_id' => $user->id, 'status' => 'active', 'ended_at' => null]);
    $community = app(CommunityLookup::class)->find($school->id);
    $drafts = collect([1, 2])->map(fn () => RegistrationDraft::create(['user_id' => $user->id, 'community_id' => $school->id, 'event_id' => $event->id, 'payload' => [], 'files' => [], 'revision' => 1, 'expires_at' => now()->addDay()]));
    $service = app(RegistrationAdditionalInformationService::class);
    expect($service->read($event, $community, $drafts[0])['additional']['enabled'])->toBeFalse();
    $institution = app(EventQuestionService::class)->save($event, $user, ['target' => 'institution', 'type' => 'text', 'label' => 'Kesenian', 'is_required' => true]);
    $team = app(EventQuestionService::class)->save($event, $user, ['target' => 'team', 'type' => 'text', 'label' => 'Catatan tim', 'is_required' => true]);
    $initial = $service->read($event, $community, $drafts[0]);
    expect($initial['additional']['enabled'])->toBeTrue();
    expect(fn () => $service->assertComplete($event, $community, $drafts[0]))->toThrow(ValidationException::class);
    $saved = $service->save($event, $user, $community, $drafts[0], ['revision' => 1, 'institution_version' => $initial['additional']['institution_version'], 'institution_answers' => [$institution->id => 'Tari'], 'team_answers' => [$team->id => 'Tim A'], 'selected_activity_ids' => [], 'complete' => true]);
    expect($saved['draft']['revision'])->toBe(2);
    $other = $service->read($event, $community, $drafts[1]);
    expect($other['additional']['institution']['answers'])->toBe([$institution->id => 'Tari'])->and($other['additional']['team']['answers'])->toBe([]);
    $service->assertComplete($event, $community, $drafts[0]->fresh());
    expect(fn () => $service->save($event, $user, $community, $drafts[1], ['revision' => 1, 'institution_version' => $initial['additional']['institution_version']]))->toThrow(HttpException::class);
    expect(EventAnswer::where('event_id', $event->id)->where('target', 'institution')->count())->toBe(1);
    expect($event->participants()->count())->toBe(0);
});

test('additional HTTP endpoints protect ownership files visibility and revision', function (): void {
    Storage::fake('local');
    $role = Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);
    $event = Event::factory()->create(['user_id' => $user->id, 'status' => 'published']);
    $school = Community::factory()->create(['subtype' => 'school', 'status' => 'verified', 'is_active' => true]);
    CommunityOfficial::factory()->create(['community_id' => $school->id, 'user_id' => $user->id, 'status' => 'active', 'ended_at' => null]);
    $draft = RegistrationDraft::create(['user_id' => $user->id, 'community_id' => $school->id, 'event_id' => $event->id, 'payload' => [], 'files' => [], 'revision' => 1, 'expires_at' => now()->addDay()]);
    $question = app(EventQuestionService::class)->save($event, $user, ['target' => 'team', 'type' => 'file', 'label' => 'Berkas tambahan', 'is_required' => true]);
    $private = $event->documents()->create(['category' => 'other', 'name' => 'PRIVATE', 'file_path' => 'private.pdf', 'is_public' => false, 'status' => 'pending']);
    $public = $event->documents()->create(['category' => 'other', 'name' => 'Panduan', 'file_path' => 'guide.pdf', 'is_public' => true, 'status' => 'verified']);
    Storage::disk('local')->put('guide.pdf', '%PDF-1.4 DEMO');
    $url = route('panel.registration-drafts.additional.show', [$event->slug, $draft->id]);
    $save = route('panel.registration-drafts.additional.save', [$event->slug, $draft->id]);
    $initial = $this->actingAs($user)->getJson($url)->assertOk()->assertJsonCount(1, 'additional.documents')->assertJsonPath('additional.documents.0.name', 'Panduan');
    expect($initial->getContent())->not->toContain('PRIVATE', 'private.pdf', 'guide.pdf');
    $this->postJson($save, ['revision' => 1, 'complete' => true])->assertUnprocessable();
    $file = UploadedFile::fake()->createWithContent('guide.pdf', '%PDF-1.4 DEMO');
    $saved = $this->post($save, ['revision' => 1, 'complete' => true, 'selected_activity_ids' => [''], 'team_answers' => [$question->id => $file]], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('draft.revision', 2);
    expect($saved->getContent())->not->toContain('event_answers/');
    $stored = $draft->fresh()->payload['additional_team_answers'][$question->id]['path'];
    Storage::disk('local')->assertExists($stored);
    $this->getJson(route('panel.registration-drafts.show', [$event->slug, $draft->id]))->assertOk()->assertJsonMissingPath('draft.payload.additional_team_answers');
    $this->postJson($save, ['revision' => 1])->assertConflict();
    $this->postJson($save, ['revision' => 2, 'team_answers' => [$question->id => ['path' => $stored]]])->assertUnprocessable();
    expect($draft->fresh()->revision)->toBe(2);
    $this->postJson($save, ['revision' => 2, 'complete' => true])->assertOk();
    $private->update(['is_public' => false]);
    $public->update(['is_public' => false]);
    $this->get(route('events.documents.download', [$event->slug, $public->id]))->assertNotFound();
    $other = User::factory()->create(['is_active' => true]);
    $other->assignRole($role);
    CommunityOfficial::factory()->create(['community_id' => $school->id, 'user_id' => $other->id, 'status' => 'active', 'ended_at' => null]);
    $this->actingAs($other)->getJson($url)->assertForbidden();
    $this->postJson($save, ['revision' => 3])->assertForbidden();
});
