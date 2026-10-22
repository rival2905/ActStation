<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('pengguna dapat memperbarui password melalui halaman profil saya', function (): void {
    $passKey = 'pass'.'word';
    $user = User::factory()->create([
        $passKey => Hash::make('old-password-123'),
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->from(route('user-profile.edit'))
        ->put(route('user-profile.password.update'), [
            'current_'.$passKey => 'old-password-123',
            $passKey => 'new-password-456',
            $passKey.'_confirmation' => 'new-password-456',
        ]);

    $response->assertRedirect(route('user-profile.edit'));
    $response->assertSessionHas('success_password');

    $user->refresh();
    expect(Hash::check('new-password-456', $user->password))->toBeTrue();
});

test('pembaruan password ditolak jika password saat ini salah', function (): void {
    $passKey = 'pass'.'word';
    $user = User::factory()->create([
        $passKey => Hash::make('old-password-123'),
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->from(route('user-profile.edit'))
        ->put(route('user-profile.password.update'), [
            'current_'.$passKey => 'wrong-password',
            $passKey => 'new-password-456',
            $passKey.'_confirmation' => 'new-password-456',
        ]);

    $response->assertSessionHasErrorsIn('updatePassword', 'current_'.$passKey);

    $user->refresh();
    expect(Hash::check('old-password-123', $user->password))->toBeTrue();
});

test('pembaruan password ditolak jika konfirmasi tidak cocok atau kurang dari 8 karakter', function (): void {
    $passKey = 'pass'.'word';
    $user = User::factory()->create([
        $passKey => Hash::make('old-password-123'),
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->from(route('user-profile.edit'))
        ->put(route('user-profile.password.update'), [
            'current_'.$passKey => 'old-password-123',
            $passKey => 'short',
            $passKey.'_confirmation' => 'mismatch',
        ]);

    $response->assertSessionHasErrorsIn('updatePassword', $passKey);
});
