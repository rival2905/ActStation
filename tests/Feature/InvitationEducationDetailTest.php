<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityInvitation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

test('academic invitation saves education detail for guest and existing member', function (string $subtype, string $identifier, string $detail): void {
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $creator = User::factory()->create(['is_active' => true]);
    $community = Community::factory()->create(['subtype' => $subtype, 'is_active' => true]);
    $token = Str::random(64);
    $invite = CommunityInvitation::create(['community_id' => $community->id, 'created_by' => $creator->id, 'token' => hash('sha256', $token), 'invite_type' => 'athlete', 'is_global' => true, 'status' => 'pending', 'expires_at' => now()->addWeek()]);
    $this->get(route('invitations.public.show', $token))->assertOk()->assertSee($subtype === 'university' ? 'Prodi / Fakultas' : 'Kelas / Jurusan');
    $email = Str::random(10).'@example.test';
    $this->post(route('invitations.public.submit', $token), ['consent' => '1', 'name' => 'Siswa Uji', 'email' => $email, 'password' => 'test-password-123', 'password_confirmation' => 'test-password-123', 'birth_place' => 'Bandung', 'birth_date' => '2005-01-01', 'gender' => 'male', 'identity_number' => $identifier, 'education_detail' => $detail])->assertRedirect()->assertSessionHasNoErrors();
    $user = User::where('email', $email)->firstOrFail();
    expect($user->educations()->firstOrFail()->education_detail)->toBe($detail);
    $this->actingAs($user)->post(route('invitations.public.join', $token), ['consent' => '1', 'education_detail' => 'Detail Baru'])->assertRedirect()->assertSessionHasNoErrors();
    expect($user->educations()->firstOrFail()->education_detail)->toBe('Detail Baru')->and($user->educations()->count())->toBe(1);
    $this->postJson(route('invitations.public.join', $token), ['consent' => '1', 'education_detail' => ['bad']])->assertUnprocessable()->assertJsonValidationErrors('education_detail');
    expect($user->educations()->firstOrFail()->education_detail)->toBe('Detail Baru');
})->with([['school', '0012345678', 'XI / IPA'], ['university', 'M2026001', 'Informatika / Teknik']]);
