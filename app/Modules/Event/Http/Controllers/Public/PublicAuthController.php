<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PublicAuthController extends Controller
{
    public function showRegisterForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('events.index');
        }

        return view('public.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Normalisasi nomor telepon ke format E.164 (PRD_EXTERNAL §2.1)
        $rawPhone = preg_replace('/[^0-9]/', '', $validated['phone_number']);
        if (str_starts_with((string) $rawPhone, '0')) {
            $phoneNormalized = '+62'.substr((string) $rawPhone, 1);
        } elseif (str_starts_with((string) $rawPhone, '62')) {
            $phoneNormalized = '+'.$rawPhone;
        } else {
            $phoneNormalized = '+'.$rawPhone;
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
            'phone_normalized' => $phoneNormalized,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $user->assignRole('member');

        Auth::login($user);

        return redirect()
            ->route('events.index')
            ->with('success', 'Selamat datang di ActStation! Akun Anda aktif dan siap menjelajahi seluruh event.');
    }
}
