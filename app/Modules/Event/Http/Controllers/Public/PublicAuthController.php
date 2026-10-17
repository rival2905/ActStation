<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

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
        // Normalisasi nomor telepon ke format E.164 (PRD_EXTERNAL §2.1) sebelum validasi
        $rawPhone = preg_replace('/[^0-9]/', '', (string) $request->input('phone_number'));
        if (str_starts_with((string) $rawPhone, '0')) {
            $phoneNormalized = '+62'.substr((string) $rawPhone, 1);
        } elseif (str_starts_with((string) $rawPhone, '62')) {
            $phoneNormalized = '+'.$rawPhone;
        } elseif ($rawPhone !== '') {
            $phoneNormalized = '+'.$rawPhone;
        } else {
            $phoneNormalized = null;
        }

        $request->merge(['phone_normalized' => $phoneNormalized]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'min:9', 'max:20'],
            'phone_normalized' => ['required', 'string', 'max:20', 'unique:users,phone_normalized'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'phone_normalized.unique' => 'Nomor WhatsApp / telepon sudah terdaftar di sistem.',
            'email.unique' => 'Alamat email sudah terdaftar di sistem.',
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'phone_normalized' => $validated['phone_normalized'],
                'password' => Hash::make($validated['password']),
                'is_active' => true,
            ]);

            // Guard terhadap role yang belum ter-seed di production
            Role::firstOrCreate(
                ['name' => 'member', 'guard_name' => 'web'],
                ['panel_access' => 'external']
            );
            $user->assignRole('member');

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        $personnelToken = $request->session()->pull('personnel_registration_return');
        if (is_string($personnelToken) && preg_match('/^[A-Za-z0-9]{64}$/D', $personnelToken)) {
            return redirect()->route('personnel-invitations.show', $personnelToken)->with('success', 'Akun siap. Tinjau peran lalu ajukan keanggotaan atau gabung roster.');
        }

        $committeeToken = $request->session()->pull('committee_registration_return');
        if (is_string($committeeToken) && preg_match('/^[A-Za-z0-9]{64}$/D', $committeeToken)) {
            return redirect()->route('committee-invitations.show', $committeeToken)->with('success', 'Akun siap. Tinjau jabatan lalu setujui pengajuan panitia.');
        }

        return redirect()
            ->route('events.index')
            ->with('success', 'Selamat datang di ActStation! Akun Anda aktif dan siap menjelajahi seluruh event.');
    }
}
