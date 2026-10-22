<?php

declare(strict_types=1);

use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SchoolRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->spy(EmailDelivery::class);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['panel_access' => 'internal']);
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web'], ['panel_access' => 'external']);
    Permission::firstOrCreate(['name' => 'community.view', 'guard_name' => 'web']);
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo('community.view');
});

function pendingCommunityBadges(string $html): array
{
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    return array_map(fn (string $attribute) => $xpath->query('//*[@'.$attribute.']'), ['data-pending-community-count', 'data-pending-community-nav-count']);
}

test('community badges count only pending undeleted communities independent of filters and disappear at zero', function (): void {
    Community::where('status', 'pending')->update(['status' => 'verified']);
    $pending = Community::factory()->count(2)->create(['status' => 'pending']);
    Community::factory()->create(['status' => 'draft']);
    Community::factory()->create(['status' => 'rejected']);
    Community::factory()->create(['status' => 'verified']);
    Community::factory()->create(['status' => 'pending'])->delete();
    $response = $this->actingAs($this->admin)->get('/admin/communities?status=verified&search=not-a-match')->assertOk();
    foreach (pendingCommunityBadges($response->getContent()) as $nodes) {
        expect($nodes->length)->toBe(1)->and(trim($nodes->item(0)->textContent))->toBe('2');
    }
    foreach ($pending as $community) {
        $community->update(['status' => 'rejected']);
    }
    $response = $this->get('/admin/communities')->assertOk();
    foreach (pendingCommunityBadges($response->getContent()) as $nodes) {
        expect($nodes->length)->toBe(0);
    }
});

test('community navigation and count are hidden without read permission', function (): void {
    $reader = User::factory()->create(['is_active' => true]);
    $reader->assignRole('admin');
    $this->actingAs($reader)->get('/admin/communities')->assertForbidden();
    $html = view('layouts.partials.sidebar')->render();
    expect($html)->not->toContain('data-pending-community-nav-count')->not->toContain('href="'.route('admin.communities.index').'"');
});

test('school page submits its own community legality with existing document gates', function (): void {
    Storage::fake('local');
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('community_manager');
    $school = Community::factory()->create(['subtype' => 'school', 'status' => 'draft', 'npsn' => '12345678', 'contact_email' => 'official@example.invalid']);
    CommunityOfficial::create(['community_id' => $school->id, 'user_id' => $owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $record = SchoolRegistration::create(['community_id' => $school->id, 'user_id' => $owner->id, 'npsn' => $school->npsn, 'contact_email' => $school->contact_email, 'email_hash' => hash('sha256', $school->contact_email), 'status' => 'approved', 'admin_review_status' => 'approved', 'email_verified_at' => now()]);
    $url = '/panel/school-registrations/'.$record->id.'/request-verification';
    $this->actingAs($owner)->get('/panel/school-registrations/'.$record->id)->assertOk()->assertSee('action="'.url($url).'"', false)->assertSee('Ajukan Verifikasi Legalitas');
    $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('legality');
    expect($school->fresh()->status)->toBe('draft');
    Storage::disk('local')->put('legal/test.pdf', 'fixture');
    $school->documents()->create(['category' => 'institution_decree', 'name' => 'SK Sekolah', 'file_path' => 'legal/test.pdf', 'status' => 'pending']);
    $other = Community::factory()->create(['status' => 'draft']);
    CommunityOfficial::create(['community_id' => $other->id, 'user_id' => $owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $this->withSession(['current_community_id' => $other->id])->post($url)->assertRedirect('/panel/school-registrations/'.$record->id)->assertSessionHasNoErrors();
    expect($school->fresh()->status)->toBe('pending')->and($other->fresh()->status)->toBe('draft')->and($record->fresh()->status)->toBe('approved');
    $this->get('/panel/school-registrations/'.$record->id)->assertOk()->assertSee('Legalitas sedang diperiksa admin')->assertDontSee('action="'.url($url).'"', false);
    $this->postJson($url)->assertUnprocessable();
    $school->update(['status' => 'rejected']);
    $this->get('/panel/school-registrations/'.$record->id)->assertOk()->assertSee('Ajukan Ulang Verifikasi Legalitas');
    $record->update(['status' => 'pending']);
    $this->postJson($url)->assertForbidden();
    $record->update(['status' => 'approved']);
    $this->travel(61)->seconds();
    $school->update(['status' => 'verified']);
    $this->get('/panel/school-registrations/'.$record->id)->assertOk()->assertDontSee('action="'.url($url).'"', false);
    $this->postJson($url)->assertUnprocessable();
    $outsider = User::factory()->create(['is_active' => true]);
    $outsider->assignRole('community_manager');
    $this->actingAs($outsider)->postJson($url)->assertNotFound();
});
