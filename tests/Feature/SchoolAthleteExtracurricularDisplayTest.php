<?php

declare(strict_types=1);

use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SchoolExtracurricular;
use App\Modules\Community\Models\SchoolExtracurricularMember;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

test('athlete directory shows scoped active extracurricular labels only for schools', function (string $subtype): void {
    Http::preventStrayRequests();
    $this->spy(EmailDelivery::class);
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('community_manager');
    $school = Community::factory()->create(['subtype' => $subtype, 'status' => 'verified', 'is_active' => true]);
    CommunityOfficial::create(['community_id' => $school->id, 'user_id' => $owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $basket = SportCategory::firstOrCreate(['slug' => 'basketball'], ['name' => 'Basketball', 'is_active' => true]);
    $futsal = SportCategory::firstOrCreate(['slug' => 'futsal'], ['name' => 'Futsal', 'is_active' => true]);
    $units = collect([$basket, $futsal])->map(fn ($sport) => SchoolExtracurricular::create(['community_id' => $school->id, 'sport_category_id' => $sport->id]));
    $athletes = collect(['Basket Only', 'Futsal Only', 'Both Sports', 'No Unit', 'Former Member'])->map(function ($name) use ($school) {
        $user = User::factory()->create(['name' => $name, 'is_active' => true]);

        return CommunityAthlete::create(['community_id' => $school->id, 'user_id' => $user->id, 'status' => 'active', 'region_status' => 'pending', 'joined_at' => today()]);
    });
    foreach ([[0, 0], [1, 1], [2, 0], [2, 1]] as [$athleteIndex, $unitIndex]) {
        SchoolExtracurricularMember::create(['school_extracurricular_id' => $units[$unitIndex]->id, 'community_athlete_id' => $athletes[$athleteIndex]->id, 'joined_at' => now()]);
    }
    SchoolExtracurricularMember::create(['school_extracurricular_id' => $units[1]->id, 'community_athlete_id' => $athletes[0]->id, 'joined_at' => now()->subDay(), 'left_at' => now()]);
    $foreign = SchoolExtracurricular::create(['community_id' => Community::factory()->create(['subtype' => 'school'])->id, 'sport_category_id' => $futsal->id]);
    SchoolExtracurricularMember::create(['school_extracurricular_id' => $foreign->id, 'community_athlete_id' => $athletes[3]->id, 'joined_at' => now()]);
    $athletes[4]->update(['status' => 'left', 'left_at' => today()]);
    SchoolExtracurricularMember::create(['school_extracurricular_id' => $units[0]->id, 'community_athlete_id' => $athletes[4]->id, 'joined_at' => now()]);
    $response = $this->actingAs($owner)->withSession(['current_community_id' => $school->id])->get('/panel/athletes')->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    $headers = $xpath->query('//*[@id="roster-pane"]//th[normalize-space(.)="Ekskul"]');
    expect($headers->length)->toBe($subtype === 'school' ? 1 : 0);
    foreach (['Basket Only' => [$basket->name], 'Futsal Only' => [$futsal->name], 'Both Sports' => [$basket->name, $futsal->name], 'No Unit' => [], 'Former Member' => []] as $name => $expected) {
        expect($xpath->query('//*[@id="roster-pane"]//tr[.//h6[normalize-space(.)="'.$name.'"]]')->length)->toBe(1);
        $labels = $xpath->query('//*[@id="roster-pane"]//tr[.//h6[normalize-space(.)="'.$name.'"]]//*[@data-athlete-extracurricular]');
        $actual = array_map(fn ($node) => trim($node->textContent), iterator_to_array($labels));
        sort($actual);
        $expected = $subtype === 'school' ? $expected : [];
        sort($expected);
        expect($actual)->toBe($expected);
    }
    if ($subtype === 'school') {
        $response->assertSee('Belum ada ekskul');
        foreach ([$units[0]->id => ['Basket Only', 'Both Sports'], $units[1]->id => ['Futsal Only', 'Both Sports']] as $unitId => $names) {
            $filtered = $this->get('/panel/athletes?extracurricular_id='.$unitId)->assertOk();
            $filteredDom = new DOMDocument;
            @$filteredDom->loadHTML($filtered->getContent());
            $filteredXpath = new DOMXPath($filteredDom);
            $actualNames = array_map(fn ($node) => trim($node->textContent), iterator_to_array($filteredXpath->query('//*[@id="roster-pane"]//tbody//h6')));
            sort($actualNames);
            sort($names);
            expect($actualNames)->toBe($names);
            $filtered->assertSee('Filter Ekskul')->assertSee('extracurricular_id='.$unitId, false);
        }
        $this->get('/panel/athletes?extracurricular_id='.$foreign->id)->assertNotFound();
        $this->getJson('/panel/athletes?extracurricular_id[]=17')->assertUnprocessable();
        $this->getJson('/panel/athletes?extracurricular_id=abc')->assertUnprocessable();
    } else {
        $response->assertDontSee('Filter Ekskul');
        $this->get('/panel/athletes?extracurricular_id='.$units[0]->id)->assertNotFound();
    }
})->with(['school', 'club', 'university', 'institution']);
