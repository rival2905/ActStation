<?php

declare(strict_types=1);
use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Event\Http\Requests\ActivityConsentRequest;
use App\Modules\Event\Http\Requests\ActivityDecisionRequest;
use App\Modules\Event\Http\Requests\RegisterEventActivityRequest;
use App\Modules\Event\Http\Requests\SaveEventActivityRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventActivity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

test('public activity catalog endpoint exists and participant writes require login', function (): void {
    expect(Route::has('events.activities.index'))->toBeTrue();
    $this->post('/panel/events/999/activities/999/registrations')->assertRedirect();
});

test('activity consent and decisions reject unaccepted and unsupported form values', function (): void {
    $consent = new ActivityConsentRequest;
    $decision = new ActivityDecisionRequest;
    expect(validator(['consent' => false], $consent->rules())->fails())->toBeTrue()
        ->and(validator(['decision' => ['approve']], $decision->rules())->fails())->toBeTrue()
        ->and(validator(['decision' => 'paid'], $decision->rules())->fails())->toBeTrue()
        ->and(validator(['consent' => '1'], $consent->rules())->passes())->toBeTrue();
});

test('activity catalog renders only public events and links from parent event', function (): void {
    $this->app->make('db')->beginTransaction();
    try {
        $event = Event::factory()->create(['status' => 'published']);
        $this->get(route('events.activities.index', $event->slug))->assertOk()->assertSee('Kegiatan Pendamping');
        $this->get(route('events.show', $event->slug))->assertOk()
            ->assertSee(route('events.activities.index', $event->slug), false);
        $event->update(['status' => 'draft']);
        $this->get(route('events.activities.index', $event->slug))->assertNotFound();
    } finally {
        $this->app->make('db')->rollBack();
    }
});

test('activity form removes empty optional members before validated payload', function (): void {
    $request = new RegisterEventActivityRequest;
    $request->replace(['members' => ['3', null, ''], 'request_key' => (string) Str::uuid(), 'consent' => '1']);
    $method = new ReflectionMethod($request, 'prepareForValidation');
    $method->invoke($request);
    expect($request->input('members'))->toBe(['3']);
});

test('activity schedule form interprets labeled WIB as UTC', function (): void {
    $request = new SaveEventActivityRequest;
    $request->replace(['starts_at' => '2026-12-01T09:00']);
    (new ReflectionMethod($request, 'prepareForValidation'))->invoke($request);
    expect($request->input('starts_at'))->toBe('2026-12-01 02:00:00');
});

test('organizer creates scheduled activity and participant submits optional member form', function (): void {
    $this->app->make('db')->beginTransaction();
    try {
        Http::preventStrayRequests();
        $this->spy(EmailDelivery::class);
        $role = Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web'], ['panel_access' => 'external']);
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole($role);
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => 'published', 'registration_open' => true]);
        $this->actingAs($owner)->post(route('panel.organizer.activities.store', $event), ['name' => 'DEMO Foto', 'capacity' => 10, 'min_members' => 1, 'max_members' => 2, 'fee_mode' => 'separate', 'fee' => 0, 'starts_at' => '2026-12-01T09:00'])->assertRedirect()->assertSessionHasNoErrors();
        $activity = EventActivity::where('event_id', $event->id)->firstOrFail();
        expect($activity->starts_at->format('H:i'))->toBe('02:00');
        $this->get(route('panel.organizer.activities.index', $event))->assertOk()->assertSee('DEMO Foto');
        $fields = view('events.activities._fields', ['activity' => $activity, 'formKey' => 'test'])->render();
        expect($fields)->toContain('name="included_slots"', 'name="eligibility_scope"', '<textarea', 'name="_form_key"');
        $this->get(route('events.activities.index', $event->slug))->assertOk()->assertSee('members[]', false);
        $this->post(route('panel.activities.register', [$event, $activity]), ['request_key' => (string) Str::uuid(), 'members' => [$owner->id, ''], 'team_name' => 'DEMO', 'consent' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $registration = $activity->registrations()->firstOrFail();
        $decisionUrl = route('panel.organizer.activities.decide', [$event, $activity, $registration]);
        $this->get(route('panel.organizer.activities.index', $event))->assertOk()->assertSee($decisionUrl, false);
        $this->post(route('panel.organizer.activities.decide', [$event, $activity, $registration]), ['decision' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        expect($registration->fresh()->status)->toBe('confirmed');
        $this->get(route('panel.organizer.activities.index', $event))->assertOk()->assertDontSee($decisionUrl, false);
    } finally {
        $this->app->make('db')->rollBack();
    }
});
