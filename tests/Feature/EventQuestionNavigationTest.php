<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\EventQuestionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

test('organizer and participant question route definitions exist', function (): void {
    expect(Route::has('panel.organizer.questions.index'))->toBeTrue()
        ->and(Route::has('panel.organizer.questions.store'))->toBeTrue()
        ->and(Route::has('panel.organizer.questions.update'))->toBeTrue()
        ->and(Route::has('panel.organizer.questions.report'))->toBeTrue()
        ->and(Route::has('panel.event-questions.edit'))->toBeTrue()
        ->and(Route::has('panel.event-questions.update'))->toBeTrue()
        ->and(Route::has('panel.event-questions.download'))->toBeTrue();
});

test('question pages render actual organizer form participant branch and report', function (): void {
    $role = Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole($role);
    $event = Event::factory()->create(['user_id' => $owner->id, 'status' => 'published']);
    $team = EventParticipant::factory()->create(['event_id' => $event->id, 'user_id' => $owner->id, 'community_id' => null, 'status' => 'confirmed']);
    $service = app(EventQuestionService::class);
    $parent = $service->save($event, $owner, ['target' => 'team', 'type' => 'select', 'label' => 'Berminat opening?', 'options' => ['Ya', 'Tidak'], 'is_required' => true, 'order' => 1]);
    $child = $service->save($event, $owner, ['target' => 'team', 'type' => 'text', 'label' => 'Nama penampilan', 'depends_on_question_id' => $parent->id, 'conditional_value' => 'Ya', 'is_required' => true, 'order' => 2]);
    $this->actingAs($owner)->get(route('panel.organizer.questions.index', $event))->assertOk()->assertSee('Berminat opening?');
    $fields = view('events.questions._fields', ['question' => null, 'key' => 'test', 'questions' => []])->render();
    expect($fields)->toContain('name="school_levels[]"', 'type="checkbox"', 'value="sma"', 'name="_form_key"')->not->toContain('>MA</option>');
    $this->get(route('panel.event-questions.edit', [$event, 'team', $team->id]))->assertOk()->assertSee('Nama penampilan');
    $this->put(route('panel.event-questions.update', [$event, 'team', $team->id]), ['answers' => [$parent->id => 'Ya', $child->id => 'DEMO Tari']])->assertRedirect()->assertSessionHasNoErrors();
    $this->get(route('panel.organizer.questions.report', $event))->assertOk()->assertSee('DEMO Tari');
});

test('question editors retain all options and isolate failed form input', function (): void {
    $question = ['id' => 1, 'label' => 'Stored label', 'target' => 'institution', 'type' => 'select', 'order' => 1, 'options' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], 'institution_types' => ['school']];
    request()->setLaravelSession(session()->driver());
    session()->flashInput(['_form_key' => 'new', 'label' => 'Failed new label', 'school_levels' => ['sma']]);
    $stored = view('events.questions._fields', ['question' => $question, 'key' => 'q-1', 'questions' => [$question]])->render();
    expect($stored)->toContain('value="Stored label"', 'value="H"')->not->toContain('value="Failed new label"');
    $new = view('events.questions._fields', ['question' => null, 'key' => 'new', 'questions' => [$question]])->render();
    expect($new)->toContain('value="Failed new label"');
});
