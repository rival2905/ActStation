<?php

declare(strict_types=1);

use App\Contracts\EmailDelivery;
use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SchoolExtracurricular;
use App\Modules\Community\Models\SchoolExtracurricularMember;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->spy(EmailDelivery::class);
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $this->owner = User::factory()->create(['is_active' => true]);
    $this->owner->assignRole('community_manager');
    $this->school = Community::factory()->create(['subtype' => 'school', 'status' => 'verified', 'is_active' => true]);
    CommunityOfficial::create(['community_id' => $this->school->id, 'user_id' => $this->owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $this->athlete = CommunityAthlete::create(['community_id' => $this->school->id, 'user_id' => User::factory()->create(['is_active' => true])->id, 'status' => 'active', 'region_status' => 'pending', 'joined_at' => today(), 'responded_at' => now()]);
    $sport = SportCategory::firstOrCreate(['slug' => 'futsal'], ['name' => 'Futsal', 'is_active' => true]);
    $this->unit = SchoolExtracurricular::create(['community_id' => $this->school->id, 'sport_category_id' => $sport->id]);
    $this->url = '/panel/athletes/'.$this->athlete->id.'/extracurriculars';
    $this->actingAs($this->owner)->withSession(['current_community_id' => $this->school->id]);
});

test('manager adds another extracurricular without an event and repeated requests preserve memberships', function (): void {
    $basket = SportCategory::firstOrCreate(['slug' => 'basketball'], ['name' => 'Basketball', 'is_active' => true]);
    $first = SchoolExtracurricular::create(['community_id' => $this->school->id, 'sport_category_id' => $basket->id]);
    SchoolExtracurricularMember::create(['school_extracurricular_id' => $first->id, 'community_athlete_id' => $this->athlete->id, 'joined_at' => now()]);
    $before = $this->athlete->fresh()->getRawOriginal();
    $eventCounts = collect(['event_registration_drafts', 'event_participants', 'event_participant_members'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $this->get('/panel/athletes')->assertOk()->assertSee('action="'.url($this->url).'"', false)->assertSee('Tambah Ekskul');
    $this->post($this->url, ['extracurricular_id' => $this->unit->id, 'panel_community_id' => $this->school->id])->assertRedirect('/panel/athletes')->assertSessionHasNoErrors();
    $row = SchoolExtracurricularMember::where('school_extracurricular_id', $this->unit->id)->where('community_athlete_id', $this->athlete->id)->firstOrFail();
    $this->post($this->url, ['extracurricular_id' => $this->unit->id])->assertRedirect('/panel/athletes')->assertSessionHasNoErrors();
    expect(SchoolExtracurricularMember::where('community_athlete_id', $this->athlete->id)->count())->toBe(2)
        ->and($this->athlete->fresh()->getRawOriginal())->toBe($before)
        ->and($row->fresh()->left_at)->toBeNull();
    expect(ActivityLog::where('auditable_type', SchoolExtracurricularMember::class)->where('auditable_id', $row->id)->where('user_id', $this->owner->id)->exists())->toBeTrue();
    foreach ($eventCounts as $table => $count) {
        expect(DB::table($table)->count())->toBe($count);
    }
    $this->get('/panel/athletes?extracurricular_id='.$this->unit->id)->assertOk()->assertSee($this->athlete->user->name);
    $this->get('/panel/athletes')->assertOk()->assertDontSee('action="'.url($this->url).'"', false);
});

test('manual enrollment rejects invalid scope and inactive membership without writes', function (): void {
    $foreign = SchoolExtracurricular::create(['community_id' => Community::factory()->create(['subtype' => 'school'])->id, 'sport_category_id' => $this->unit->sport_category_id]);
    $this->postJson($this->url, ['extracurricular_id' => $foreign->id])->assertNotFound();
    $this->postJson($this->url, ['extracurricular_id' => []])->assertUnprocessable();
    $this->postJson($this->url, ['extracurricular_id' => 'bad'])->assertUnprocessable();
    $this->postJson($this->url, ['extracurricular_id' => $this->unit->id, 'panel_community_id' => $foreign->community_id])->assertUnprocessable();
    $this->athlete->update(['status' => 'pending']);
    $this->postJson($this->url, ['extracurricular_id' => $this->unit->id])->assertUnprocessable();
    $this->athlete->update(['status' => 'active', 'left_at' => today()]);
    $this->postJson($this->url, ['extracurricular_id' => $this->unit->id])->assertUnprocessable();
    $this->athlete->update(['left_at' => null]);
    $this->athlete->user->update(['is_active' => false]);
    $this->postJson($this->url, ['extracurricular_id' => $this->unit->id])->assertUnprocessable();
    expect(SchoolExtracurricularMember::where('community_athlete_id', $this->athlete->id)->count())->toBe(0);
});

test('manual enrollment cannot reactivate ended extracurricular membership', function (): void {
    $ended = SchoolExtracurricularMember::create(['school_extracurricular_id' => $this->unit->id, 'community_athlete_id' => $this->athlete->id, 'joined_at' => now()->subDays(3), 'left_at' => now()->subDay()]);
    $before = $ended->fresh()->getRawOriginal();
    $this->postJson($this->url, ['extracurricular_id' => $this->unit->id])->assertUnprocessable();
    expect($ended->fresh()->getRawOriginal())->toBe($before);
});

test('manual enrollment remains school only and denies foreign athletes and outsiders', function (): void {
    $other = CommunityAthlete::create(['community_id' => Community::factory()->create()->id, 'user_id' => User::factory()->create()->id, 'status' => 'active', 'joined_at' => today()]);
    $this->postJson('/panel/athletes/'.$other->id.'/extracurriculars', ['extracurricular_id' => $this->unit->id])->assertNotFound();
    $this->school->update(['subtype' => 'club']);
    $this->get('/panel/athletes')->assertOk()->assertDontSee('Tambah Ekskul');
    $this->postJson($this->url, ['extracurricular_id' => $this->unit->id])->assertForbidden();
    $this->school->update(['subtype' => 'school']);
    $outsider = User::factory()->create(['is_active' => true]);
    $outsider->assignRole('community_manager');
    $this->actingAs($outsider)->postJson($this->url, ['extracurricular_id' => $this->unit->id])->assertForbidden();
    expect(SchoolExtracurricularMember::where('community_athlete_id', $this->athlete->id)->count())->toBe(0);
});
