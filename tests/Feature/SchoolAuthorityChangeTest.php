<?php

declare(strict_types=1);
use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SchoolAuthorityChange;
use App\Modules\Community\Models\SchoolRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

test('school handover cannot change or verify a different official inbox', function (): void {
    Http::preventStrayRequests();
    $this->spy(EmailDelivery::class);
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('member');
    $target = User::factory()->create(['is_active' => true]);
    $school = Community::factory()->create(['subtype' => 'school', 'is_active' => true, 'contact_email' => 'official@example.invalid']);
    CommunityOfficial::create(['community_id' => $school->id, 'user_id' => $owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $registration = SchoolRegistration::create(['community_id' => $school->id, 'user_id' => $owner->id, 'npsn' => '12345678', 'contact_email' => $school->contact_email, 'email_hash' => hash('sha256', $school->contact_email), 'status' => 'approved', 'admin_review_status' => 'approved', 'email_verified_at' => now()]);

    $this->actingAs($owner)->postJson('/panel/school-registrations/'.$registration->id.'/authority', [
        'target_email' => $target->email,
        'new_email' => 'unproven@example.invalid',
        'current_password' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('new_email');

    expect($registration->fresh()->contact_email)->toBe('official@example.invalid');
    expect(SchoolAuthorityChange::where('school_registration_id', $registration->id)->count())->toBe(0);
});

test('school owner transfer requires password confirmation and recipient acceptance', function (): void {
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('member');
    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole('member');
    $school = Community::factory()->create(['subtype' => 'school', 'is_active' => true, 'contact_email' => 'school@example.invalid']);
    $old = CommunityOfficial::create(['community_id' => $school->id, 'user_id' => $owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $registration = SchoolRegistration::create(['community_id' => $school->id, 'user_id' => $owner->id, 'npsn' => '12345678', 'contact_email' => $school->contact_email, 'email_hash' => hash('sha256', $school->contact_email), 'status' => 'approved', 'admin_review_status' => 'approved', 'email_verified_at' => now(), 'reviewed_at' => now()]);
    $url = '/panel/school-registrations/'.$registration->id.'/authority';
    $this->actingAs($owner)->postJson($url, ['target_user_id' => $target->id, 'current_password' => 'wrong'])->assertUnprocessable();
    $this->post($url, ['target_user_id' => $target->id, 'current_password' => 'password'])->assertRedirect()->assertSessionHasNoErrors();
    $change = SchoolAuthorityChange::firstOrFail();
    expect($old->fresh()->status)->toBe('active');
    $this->get('/panel/school-registrations/'.$registration->id)
        ->assertOk()->assertSee('Kewenangan Sekolah')->assertSee('Serahkan Tanggung Jawab')
        ->assertDontSee('Verifikasi Sekolah Saya')->assertDontSee('name="new_email"', false);
    $token = str_repeat('a', 64);
    $change->update(['token_hash' => hash('sha256', $token)]);
    $accept = URL::temporarySignedRoute('panel.school-authority.accept', now()->addHour(), ['change' => $change->id, 'token' => $token]);
    $this->actingAs($target)->get($accept)->assertOk()->assertSee('Terima & Konfirmasi Perubahan', false);
    expect($old->fresh()->status)->toBe('active');
    $this->actingAs($target)->post($accept, ['token' => $token, 'consent' => '1'])->assertRedirect();
    expect($old->fresh()->status)->toBe('inactive')->and($registration->fresh()->user_id)->toBe($target->id);
    $this->assertDatabaseHas('community_officials', ['community_id' => $school->id, 'user_id' => $target->id, 'status' => 'active']);
    $this->post($accept, ['token' => $token, 'consent' => '1'])->assertForbidden();
});

test('legacy combined handover cannot verify an unproven official inbox', function (): void {
    Http::preventStrayRequests();
    $this->spy(EmailDelivery::class);
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
    $owner = User::factory()->create(['is_active' => true]);
    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole('member');
    $school = Community::factory()->create(['subtype' => 'school', 'is_active' => true, 'contact_email' => 'old-inbox@example.invalid']);
    $official = CommunityOfficial::create(['community_id' => $school->id, 'user_id' => $owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $registration = SchoolRegistration::create(['community_id' => $school->id, 'user_id' => $owner->id, 'npsn' => '12345678', 'contact_email' => $school->contact_email, 'email_hash' => hash('sha256', $school->contact_email), 'status' => 'approved', 'admin_review_status' => 'approved', 'email_verified_at' => now()]);
    $token = str_repeat('d', 64);
    $change = SchoolAuthorityChange::create(['school_registration_id' => $registration->id, 'requested_by' => $owner->id, 'target_user_id' => $target->id, 'target_email' => $target->email, 'new_email' => 'unproven@example.invalid', 'token_hash' => hash('sha256', $token), 'status' => 'pending', 'expires_at' => now()->addHour()]);
    $url = URL::temporarySignedRoute('panel.school-authority.accept', now()->addHour(), ['change' => $change->id, 'token' => $token]);

    $this->actingAs($target)->post($url, ['token' => $token, 'consent' => '1'])->assertConflict();
    expect($registration->fresh()->user_id)->toBe($owner->id)
        ->and($registration->fresh()->contact_email)->toBe('old-inbox@example.invalid')
        ->and($official->fresh()->status)->toBe('active')
        ->and($change->fresh()->status)->toBe('pending');
});

test('school authority confirmation rejects unauthorized scenarios', function (string $scenario): void {
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('member');
    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole('member');
    $foreign = User::factory()->create(['is_active' => true]);
    $foreign->assignRole('member');
    $school = Community::factory()->create(['subtype' => 'school', 'is_active' => true, 'contact_email' => 'school@example.invalid']);
    $old = CommunityOfficial::create(['community_id' => $school->id, 'user_id' => $owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $registration = SchoolRegistration::create(['community_id' => $school->id, 'user_id' => $owner->id, 'npsn' => '12345678', 'contact_email' => $school->contact_email, 'email_hash' => hash('sha256', $school->contact_email), 'status' => 'approved', 'admin_review_status' => 'approved', 'email_verified_at' => now(), 'reviewed_at' => now()]);

    $token = str_repeat('b', 64);
    $change = SchoolAuthorityChange::create([
        'school_registration_id' => $registration->id,
        'requested_by' => $owner->id,
        'target_user_id' => $target->id,
        'new_email' => 'updated@example.invalid',
        'token_hash' => hash('sha256', $token),
        'status' => 'pending',
        'expires_at' => now()->addHour(),
    ]);

    $actingUser = $target;
    if ($scenario === 'foreign_user') {
        $actingUser = $foreign;
    } elseif ($scenario === 'expired') {
        $change->update(['expires_at' => now()->subMinute()]);
    } elseif ($scenario === 'revoked') {
        $change->update(['status' => 'revoked']);
    }

    $accept = URL::temporarySignedRoute('panel.school-authority.accept', now()->addHour(), ['change' => $change->id, 'token' => $token]);
    $this->actingAs($actingUser)->get($accept)->assertForbidden();
    $this->actingAs($actingUser)->post($accept, ['token' => $token, 'consent' => '1'])->assertForbidden();

    expect($old->fresh()->status)->toBe('active')
        ->and($registration->fresh()->user_id)->toBe($owner->id)
        ->and($change->fresh()->status)->not->toBe('accepted');
})->with(['foreign_user', 'expired', 'revoked']);

test('school owner can invite replacement via email and unregistered user can register and accept', function (): void {
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);

    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('member');
    $school = Community::factory()->create(['subtype' => 'school', 'is_active' => true, 'contact_email' => 'school@example.invalid']);
    $old = CommunityOfficial::create(['community_id' => $school->id, 'user_id' => $owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $registration = SchoolRegistration::create([
        'community_id' => $school->id,
        'user_id' => $owner->id,
        'npsn' => '12345678',
        'contact_email' => $school->contact_email,
        'email_hash' => hash('sha256', $school->contact_email),
        'status' => 'approved',
        'admin_review_status' => 'approved',
        'email_verified_at' => now(),
        'reviewed_at' => now(),
    ]);

    $url = '/panel/school-registrations/'.$registration->id.'/authority';
    $targetEmail = 'newguru@example.invalid';

    // 1. Submit via email without target_user_id
    $this->actingAs($owner)->post($url, [
        'target_email' => $targetEmail,
        'current_password' => 'password',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $change = SchoolAuthorityChange::where('target_email', $targetEmail)->firstOrFail();
    expect($change->target_user_id)->toBeNull();

    // 2. Unregistered recipient visits signed confirm link
    $token = str_repeat('c', 64);
    $change->update(['token_hash' => hash('sha256', $token)]);
    $confirmUrl = URL::temporarySignedRoute('panel.school-authority.confirm', now()->addHour(), [
        'change' => $change->id,
        'token' => $token,
    ]);

    // Guest sees registration form
    auth()->logout();
    $this->get($confirmUrl)
        ->assertOk()
        ->assertSee('belum terdaftar di ActStation', false)
        ->assertDontSee('navbar-vertical', false)
        ->assertDontSee('Kelola Komunitas', false);

    // 3. Recipient registers via standard route with token parameter (no signed query needed)
    $registerUrl = route('panel.school-authority.register', [
        'change' => $change->id,
        'token' => $token,
    ]);

    $response = $this->post($registerUrl, [
        'name' => 'Guru Pengganti',
        'phone_number' => '081234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect();
    $newUser = User::where('email', $targetEmail)->firstOrFail();
    expect($change->fresh()->target_user_id)->toBe($newUser->id);

    // 4. Now logged in, accepts transfer
    $acceptUrl = URL::temporarySignedRoute('panel.school-authority.accept', now()->addHour(), [
        'change' => $change->id,
        'token' => $token,
    ]);

    $this->actingAs($newUser)->post($acceptUrl, [
        'token' => $token,
        'consent' => '1',
    ])->assertRedirect();

    expect($old->fresh()->status)->toBe('inactive')
        ->and($registration->fresh()->user_id)->toBe($newUser->id)
        ->and($change->fresh()->status)->toBe('accepted');
    $this->assertDatabaseHas('community_officials', [
        'community_id' => $school->id,
        'user_id' => $newUser->id,
        'status' => 'active',
    ]);
});

test('recipient can reject school authority transfer', function (): void {
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);

    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('member');
    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole('member');
    $school = Community::factory()->create(['subtype' => 'school', 'is_active' => true, 'contact_email' => 'school@example.invalid']);
    $old = CommunityOfficial::create(['community_id' => $school->id, 'user_id' => $owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $registration = SchoolRegistration::create([
        'community_id' => $school->id,
        'user_id' => $owner->id,
        'npsn' => '12345678',
        'contact_email' => $school->contact_email,
        'email_hash' => hash('sha256', $school->contact_email),
        'status' => 'approved',
        'admin_review_status' => 'approved',
        'email_verified_at' => now(),
        'reviewed_at' => now(),
    ]);

    $url = '/panel/school-registrations/'.$registration->id.'/authority';
    $this->actingAs($owner)->post($url, [
        'target_email' => $target->email,
        'current_password' => 'password',
    ])->assertRedirect();

    $change = SchoolAuthorityChange::where('target_email', $target->email)->firstOrFail();
    $token = str_repeat('d', 64);
    $change->update(['token_hash' => hash('sha256', $token)]);

    $rejectUrl = URL::temporarySignedRoute('panel.school-authority.reject', now()->addHour(), [
        'change' => $change->id,
        'token' => $token,
    ]);

    $this->actingAs($target)->post($rejectUrl, ['token' => $token])->assertRedirect();

    expect($change->fresh()->status)->toBe('rejected')
        ->and($old->fresh()->status)->toBe('active')
        ->and($registration->fresh()->user_id)->toBe($owner->id);
});
