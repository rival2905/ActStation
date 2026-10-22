<?php

declare(strict_types=1);

use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityCoach;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SchoolExtracurricular;
use App\Modules\Community\Models\SchoolExtracurricularAppointment;
use App\Modules\Community\Models\SportsPersonnelRole;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

test('coaches directory distinguishes current school appointments by sport and matching role', function (string $subtype): void {
    Http::preventStrayRequests();
    $this->spy(EmailDelivery::class);
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('community_manager');
    $school = Community::factory()->create(['subtype' => $subtype, 'status' => 'verified', 'is_active' => true]);
    CommunityOfficial::create(['community_id' => $school->id, 'user_id' => $owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $role = SportsPersonnelRole::firstOrCreate(['code' => 'coach'], ['name' => 'Pelatih', 'is_active' => true, 'sort_order' => 1]);
    $assistant = SportsPersonnelRole::firstOrCreate(['code' => 'test_assistant'], ['name' => 'Asisten Test', 'is_active' => true, 'sort_order' => 2]);
    $units = collect(['basketball' => 'Basketball', 'futsal' => 'Futsal'])->map(function ($name, $slug) use ($school) {
        $sport = SportCategory::firstOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true]);

        return SchoolExtracurricular::create(['community_id' => $school->id, 'sport_category_id' => $sport->id]);
    })->values();
    $coaches = collect(['Basket Only', 'Futsal Only', 'Both Sports', 'No Unit', 'Former Coach', 'Pending Coach', 'Inactive Account', 'Ended Assignment', 'Pending Assignment'])->map(function ($name) use ($school, $role) {
        return CommunityCoach::factory()->create(['community_id' => $school->id, 'user_id' => User::factory()->create(['name' => $name, 'is_active' => $name !== 'Inactive Account'])->id, 'sports_personnel_role_id' => $role->id]);
    });
    $coaches[0]->update(['sports_personnel_role_id' => null]);
    $coaches[4]->update(['ended_at' => today()]);
    $coaches[5]->update(['status' => 'pending']);
    foreach ([[0, 0], [1, 1], [2, 0], [2, 1], [4, 0], [5, 0], [6, 0], [7, 0], [8, 0]] as [$person, $unit]) {
        SchoolExtracurricularAppointment::create(['school_extracurricular_id' => $units[$unit]->id, 'user_id' => $coaches[$person]->user_id, 'sports_personnel_role_id' => $role->id, 'status' => $person === 8 ? 'pending' : 'active', 'ended_at' => $person === 7 ? now() : null, 'invited_by' => $owner->id]);
    }
    SchoolExtracurricularAppointment::create(['school_extracurricular_id' => $units[1]->id, 'user_id' => $coaches[0]->user_id, 'sports_personnel_role_id' => $assistant->id, 'status' => 'active', 'invited_by' => $owner->id]);
    $foreign = SchoolExtracurricular::create(['community_id' => Community::factory()->create(['subtype' => 'school'])->id, 'sport_category_id' => $units[1]->sport_category_id]);
    SchoolExtracurricularAppointment::create(['school_extracurricular_id' => $foreign->id, 'user_id' => $coaches[3]->user_id, 'sports_personnel_role_id' => $role->id, 'status' => 'active', 'invited_by' => $owner->id]);
    $snapshot = SchoolExtracurricularAppointment::whereIn('user_id', $coaches->pluck('user_id'))->get()->toArray();
    $response = $this->actingAs($owner)->withSession(['current_community_id' => $school->id])->get('/panel/coaches')->assertOk();
    $parse = function ($response) {
        $dom = new DOMDocument;
        @$dom->loadHTML($response->getContent());

        return new DOMXPath($dom);
    };
    $xpath = $parse($response);
    expect($xpath->query('//th[normalize-space(.)="Ekskul"]')->length)->toBe($subtype === 'school' ? 1 : 0);
    foreach ($coaches as $index => $coach) {
        $expected = match ($index) {
            0 => ['Basketball'], 1 => ['Futsal'], 2 => ['Basketball', 'Futsal'], default => []
        };
        $labels = array_map(fn ($node) => trim($node->textContent), iterator_to_array($xpath->query('//tr[.//h6[normalize-space(.)="'.$coach->user->name.'"]]//*[@data-coach-extracurricular]')));
        sort($labels);
        expect($labels)->toBe($subtype === 'school' ? $expected : []);
    }
    if ($subtype === 'school') {
        $response->assertSee('Filter Ekskul')->assertSee('Belum ada penugasan ekskul')->assertSee(route('panel.schools.extracurriculars.personnel.index', [$school->id, $units[0]->id]), false);
        foreach ([$units[0]->id => ['Basket Only', 'Both Sports'], $units[1]->id => ['Futsal Only', 'Both Sports']] as $unitId => $expected) {
            $filtered = $parse($this->get('/panel/coaches?extracurricular_id='.$unitId)->assertOk());
            $names = array_map(fn ($node) => trim($node->textContent), iterator_to_array($filtered->query('//tbody//h6')));
            sort($names);
            sort($expected);
            expect($names)->toBe($expected);
            expect($filtered->query('//*[@aria-label="Filter Ekskul"]//a[@aria-current="page"]')->length)->toBe(1);
            $counts = array_map(fn ($node) => trim($node->textContent), iterator_to_array($filtered->query('//*[@aria-label="Filter Ekskul"]//a//span')));
            expect($counts)->toBe(['9', '2', '2']);
        }
        $this->get('/panel/coaches?extracurricular_id='.$foreign->id)->assertNotFound();
        $this->getJson('/panel/coaches?extracurricular_id[]=1')->assertUnprocessable();
        $this->getJson('/panel/coaches?extracurricular_id=abc')->assertUnprocessable();
    } else {
        $response->assertDontSee('Filter Ekskul');
        $this->get('/panel/coaches?extracurricular_id='.$units[0]->id)->assertNotFound();
    }
    expect(SchoolExtracurricularAppointment::whereIn('user_id', $coaches->pluck('user_id'))->get()->toArray())->toBe($snapshot);
})->with(['school', 'club', 'university', 'institution']);
