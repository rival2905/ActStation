<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SchoolExtracurricular;
use App\Modules\Community\Models\SchoolExtracurricularAppointment;
use App\Modules\Community\Models\SportsPersonnelRole;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->assignRole('member');
    $this->school = Community::factory()->create(['subtype' => 'school', 'is_active' => true]);
    CommunityOfficial::create(['community_id' => $this->school->id, 'user_id' => $this->manager->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $sport = SportCategory::firstOrCreate(['slug' => 'basketball'], ['name' => 'Basketball', 'is_active' => true]);
    $this->unit = SchoolExtracurricular::create(['community_id' => $this->school->id, 'sport_category_id' => $sport->id]);
});

test('get authenticated scoped unit workspace route returns ok only for school manager', function (): void {
    $this->actingAs($this->manager)->get(route('panel.schools.extracurriculars.personnel.index', [$this->school->id, $this->unit->id]))->assertOk();
});

test('outsider cannot open scoped personnel workspace', function (): void {
    $stranger = User::factory()->create(['is_active' => true]);
    $stranger->assignRole('member');
    $this->actingAs($stranger)->get(route('panel.schools.extracurriculars.personnel.index', [$this->school->id, $this->unit->id]))->assertForbidden();
});

test('replacement activates after acceptance without school-wide rights', function (): void {
    $role = SportsPersonnelRole::firstOrCreate(['code' => 'coach'], ['name' => 'Pelatih', 'is_active' => true, 'sort_order' => 1]);
    $oldUser = User::factory()->create(['is_active' => true]);
    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole('member');
    $old = SchoolExtracurricularAppointment::create(['school_extracurricular_id' => $this->unit->id, 'user_id' => $oldUser->id, 'sports_personnel_role_id' => $role->id, 'status' => 'active', 'can_manage_personnel' => true, 'invited_by' => $this->manager->id]);
    $response = $this->actingAs($this->manager)->post(route('panel.schools.extracurriculars.personnel.invite', [$this->school->id, $this->unit->id]), ['target_user_id' => $target->id, 'sports_personnel_role_id' => $role->id, 'can_manage_personnel' => true, 'replaces_id' => $old->id]);
    $response->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('invite_url');
    $url = session('invite_url');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $appointment = SchoolExtracurricularAppointment::where('user_id', $target->id)->firstOrFail();
    expect($old->fresh()->status)->toBe('active');
    $this->actingAs($target)->get($url)->assertOk();
    expect($appointment->fresh()->status)->toBe('pending');
    $accept = route('panel.schools.extracurriculars.personnel.accept', [$this->school->id, $this->unit->id, $appointment->id]);
    $this->post($accept, ['token' => $query['token'], 'consent' => '1'])->assertRedirect();
    expect($appointment->fresh()->status)->toBe('active')->and($old->fresh()->status)->toBe('ended');
    $this->assertDatabaseMissing('community_officials', ['community_id' => $this->school->id, 'user_id' => $target->id]);
    $this->get(route('panel.schools.extracurriculars.personnel.index', [$this->school->id, $this->unit->id]))->assertOk();
    $other = SchoolExtracurricular::create(['community_id' => $this->school->id, 'sport_category_id' => SportCategory::create(['name' => 'Futsal Test', 'slug' => 'futsal-'.uniqid(), 'is_active' => true])->id]);
    $this->get(route('panel.schools.extracurriculars.personnel.index', [$this->school->id, $other->id]))->assertForbidden();
    $this->post($accept, ['token' => $query['token'], 'consent' => '1'])->assertForbidden();
    expect($appointment->fresh()->status)->toBe('active');
});
