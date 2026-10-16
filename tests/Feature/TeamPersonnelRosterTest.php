<?php

declare(strict_types=1);

use App\Contracts\TeamPersonnelProvider;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\RegistrationDraftService;
use App\Modules\Event\Services\TeamPersonnelRoster;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->event = Event::factory()->create(['personnel_requirements' => [['role_id' => 1, 'min' => 1, 'max' => 1], ['role_id' => 2, 'min' => 0, 'max' => 2]]]);
    $this->community = Community::factory()->create();
    $this->creator = User::factory()->create(['is_active' => true]);
    $provider = Mockery::mock(TeamPersonnelProvider::class);
    $provider->shouldReceive('roles')->andReturn([['id' => 1, 'name' => 'Manager', 'code' => 'manager', 'is_active' => true], ['id' => 2, 'name' => 'Dokumentasi', 'code' => 'documentation', 'is_active' => true], ['id' => 3, 'name' => 'Nonaktif', 'code' => 'inactive', 'is_active' => false]]);
    $provider->shouldReceive('eligiblePeople')->with($this->community->id)->andReturn([['user_id' => $this->creator->id, 'name' => $this->creator->name, 'is_official' => true]]);
    $this->app->instance(TeamPersonnelProvider::class, $provider);
    $this->service = app(TeamPersonnelRoster::class);
});

test('one person fills several allowed roles without athlete requirements', function (): void {
    $selection = [['user_id' => $this->creator->id, 'role_id' => 1], ['user_id' => $this->creator->id, 'role_id' => 2]];
    $participant = EventParticipant::factory()->create(['event_id' => $this->event->id, 'community_id' => $this->community->id, 'personnel_requirements' => $this->event->personnel_requirements]);
    $this->service->sync($participant, $selection);
    expect($participant->personnel()->count())->toBe(2)->and($participant->members()->count())->toBe(0);
    expect($participant->personnel()->pluck('role_name')->all())->toBe(['Manager', 'Dokumentasi']);
    $this->event->update(['personnel_requirements' => []]);
    $participant->unsetRelation('event');
    $this->service->sync($participant, $selection);
    expect($participant->personnel()->count())->toBe(2);
});

test('missing mandatory role allowed for draft but blocked for submission', function (): void {
    expect($this->service->validate($this->event, $this->community->id, [], false))->toBe([]);
    $this->service->validate($this->event, $this->community->id, []);
})->throws(ValidationException::class);

test('forged unsupported inactive and duplicate personnel are rejected', function (array $rows): void {
    $selection = array_map(fn ($r) => ['user_id' => $r[0] === 0 ? $this->creator->id : $r[0], 'role_id' => $r[1]], $rows);
    $this->service->validate($this->event, $this->community->id, $selection, false);
})->with([[[[9999999, 1]]], [[[0, 999]]], [[[0, 3]]], [[[0, 1], [0, 1]]]])->throws(ValidationException::class);

test('creator manager assigned only at creation and not resurrected by updates', function (): void {
    $drafts = app(RegistrationDraftService::class);
    $draft = $drafts->createDraft($this->creator, $this->community->id, $this->event, ['team_name' => 'Tim Uji'], Request::create('/', 'POST'));
    expect($draft->payload['personnel'])->toBe([['user_id' => $this->creator->id, 'role_id' => 1]]);
    $draft = $drafts->updateDraft($draft, ['personnel' => []], $draft->revision, Request::create('/', 'POST'));
    expect($draft->payload['personnel'])->toBe([]);
    $draft = $drafts->updateDraft($draft, ['notes' => 'Tetap tanpa manager'], $draft->revision, Request::create('/', 'POST'));
    expect($draft->payload['personnel'])->toBe([]);
});

test('unconfigured event accepts no personnel and does not create manager', function (): void {
    $this->event->update(['personnel_requirements' => null]);
    expect($this->service->initial($this->event, $this->community->id, $this->creator->id))->toBe([])
        ->and($this->service->validate($this->event, $this->community->id, []))->toBe([]);
});
