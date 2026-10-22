<?php

declare(strict_types=1);

use App\Contracts\EmailDelivery;
use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SchoolAuthorityChange;
use App\Modules\Community\Models\SchoolRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->spy(EmailDelivery::class);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['panel_access' => 'internal']);
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web'], ['panel_access' => 'external']);
    Permission::firstOrCreate(['name' => 'community.verify', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'community.view', 'guard_name' => 'web']);
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo(['community.verify', 'community.view']);
    $this->owner = User::factory()->create(['is_active' => true]);
    $this->owner->assignRole('community_manager');
    $this->target = User::factory()->create(['is_active' => true]);
    $this->school = Community::factory()->create(['subtype' => 'school', 'contact_email' => 'official@example.invalid', 'status' => 'draft']);
    $this->official = CommunityOfficial::create(['community_id' => $this->school->id, 'user_id' => $this->owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $this->registration = SchoolRegistration::create(['community_id' => $this->school->id, 'user_id' => $this->owner->id, 'npsn' => '12345678', 'contact_email' => $this->school->contact_email, 'email_hash' => hash('sha256', $this->school->contact_email), 'status' => 'approved', 'admin_review_status' => 'approved', 'email_verified_at' => now()->subDay()]);
});

test('admin handover immediately preserves email verification and legality', function (): void {
    $verified = $this->registration->email_verified_at->toISOString();
    $pending = SchoolAuthorityChange::create(['school_registration_id' => $this->registration->id, 'requested_by' => $this->owner->id, 'target_user_id' => $this->target->id, 'target_email' => $this->target->email, 'new_email' => $this->school->contact_email, 'token_hash' => hash('sha256', str_repeat('p', 64)), 'status' => 'pending', 'expires_at' => now()->addHour()]);
    $this->actingAs($this->admin)->post('/admin/communities/'.$this->school->id.'/authority', ['target_email' => $this->target->email, 'reason' => 'Pemulihan berdasarkan surat tugas resmi.'])->assertRedirect()->assertSessionHasNoErrors();
    expect($this->registration->fresh()->user_id)->toBe($this->target->id)
        ->and($this->registration->fresh()->email_verified_at->toISOString())->toBe($verified)
        ->and($this->registration->fresh()->contact_email)->toBe('official@example.invalid')
        ->and($this->school->fresh()->status)->toBe('draft')
        ->and($this->official->fresh()->status)->toBe('inactive')
        ->and($this->owner->fresh()->hasRole('community_manager'))->toBeTrue()
        ->and($pending->fresh()->status)->toBe('revoked');
    $log = ActivityLog::where('user_id', $this->admin->id)->where('auditable_type', SchoolRegistration::class)->where('auditable_id', $this->registration->id)->latest('id')->firstOrFail();
    expect($log->new_values['reason'])->toBe('Pemulihan berdasarkan surat tugas resmi.');
    $this->actingAs($this->owner)->get('/panel/school-registrations/'.$this->registration->id)->assertNotFound();
    $this->assertDatabaseHas('community_officials', ['community_id' => $this->school->id, 'user_id' => $this->target->id, 'status' => 'active', 'ended_at' => null]);
});

test('admin handover activates existing pending manager assignment without duplicate row', function (): void {
    $pending = CommunityOfficial::create(['community_id' => $this->school->id, 'user_id' => $this->target->id, 'position' => 'manager', 'status' => 'pending', 'started_at' => today()]);
    $this->actingAs($this->admin)->post('/admin/communities/'.$this->school->id.'/authority', ['target_email' => $this->target->email, 'reason' => 'Pemulihan berdasarkan surat tugas resmi.'])->assertRedirect()->assertSessionHasNoErrors();
    expect($pending->fresh()->status)->toBe('active');
});

test('admin handover denies outsiders and invalid targets', function (): void {
    $url = '/admin/communities/'.$this->school->id.'/authority';
    $payload = ['target_email' => $this->target->email, 'reason' => 'Pemulihan berdasarkan surat tugas resmi.'];
    $this->actingAs($this->owner)->postJson($url, $payload)->assertForbidden();
    $reader = User::factory()->create(['is_active' => true]);
    $reader->assignRole('admin');
    $this->actingAs($reader)->postJson($url, $payload)->assertForbidden();
    $this->actingAs($this->admin)->postJson($url, [...$payload, 'target_email' => 'missing@example.invalid'])->assertUnprocessable();
    $this->target->update(['is_active' => false]);
    $this->postJson($url, $payload)->assertUnprocessable();
    $this->postJson($url, [...$payload, 'target_email' => ['invalid']])->assertUnprocessable();
    expect($this->registration->fresh()->user_id)->toBe($this->owner->id)->and($this->official->fresh()->status)->toBe('active');
});

test('admin detail has direct handover form without password or confirmation', function (): void {
    $response = $this->actingAs($this->admin)->get('/admin/communities/'.$this->school->id)->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    $forms = $xpath->query('//form[contains(@action, "/communities/'.$this->school->id.'/authority")]');
    expect($forms->length)->toBe(1);
    expect($xpath->query('.//input[@type="password"]', $forms->item(0))->length)->toBe(0);
    expect($forms->item(0)->hasAttribute('onsubmit'))->toBeFalse();
});
