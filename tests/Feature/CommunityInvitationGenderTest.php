<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Services\InvitationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public invitation form renders gender input and saves gender on guest registration', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $creator = User::where('email', 'superadmin@actstation.test')->first();
    $community = Community::factory()->create(['subtype' => 'club']);

    $invitationService = app(InvitationService::class);
    $result = $invitationService->createInvitation($community, ['is_global' => true], $creator);
    $token = $result['plain_token'];

    // 1. Check form rendering
    $response = $this->get(route('invitations.public.show', $token));
    $response->assertOk();
    $response->assertSee('name="gender"', false);
    $response->assertSee('Jenis Kelamin');
    $response->assertSee('value="male"', false);
    $response->assertSee('value="female"', false);

    // 2. Submit guest registration with gender female
    $submitResponse = $this->post(route('invitations.public.submit', $token), [
        'consent' => '1',
        'name' => 'Atlet Putri Baru',
        'email' => 'atlet.putri@actstation.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'birth_place' => 'Bandung',
        'birth_date' => '2006-05-15',
        'gender' => 'female',
    ]);

    $submitResponse->assertRedirect(route('panel.login'));
    $submitResponse->assertSessionHasNoErrors();

    $newUser = User::where('email', 'atlet.putri@actstation.test')->first();
    expect($newUser)->not->toBeNull()
        ->and($newUser->gender)->toBe('female')
        ->and($newUser->birth_place)->toBe('Bandung');
});

test('authenticated user can join community via invitation and update gender', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $creator = User::where('email', 'superadmin@actstation.test')->first();
    $community = Community::factory()->create(['subtype' => 'club']);

    $user = User::factory()->create([
        'email' => 'atlet.putra@actstation.test',
        'gender' => null,
        'is_active' => true,
    ]);

    $invitationService = app(InvitationService::class);
    $result = $invitationService->createInvitation($community, ['is_global' => true], $creator);
    $token = $result['plain_token'];

    // Join with gender male
    $response = $this->actingAs($user)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Jakarta',
        'birth_date' => '2005-08-20',
        'gender' => 'male',
    ]);

    $response->assertRedirect(route('invitations.public.show', $token));
    $response->assertSessionHasNoErrors();

    expect($user->fresh()->gender)->toBe('male');
});
