<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PanelAuthController extends Controller
{
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            return redirect()->to($user->getPanelHomeRoute());
        }

        return view('panel.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Kredensial tidak cocok dengan data kami.']);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Akun Anda dinonaktifkan. Silakan hubungi pengurus.']);
        }
        $request->session()->regenerate();

        $hasInternalRole = $user->roles
            ->contains(fn ($role) => in_array($role->panel_access, ['internal', 'both'], true));

        $targetRoute = $user->getPanelHomeRoute();

        if ($hasInternalRole) {
            return redirect()->intended($targetRoute);
        }

        // Akun eksternal: abaikan intended URL internal agar tidak membentur 403
        $intended = $request->session()->get('url.intended');
        if ($intended !== null && ! str_starts_with($intended, url('/admin'))) {
            return redirect()->intended($targetRoute);
        }

        $request->session()->forget('url.intended');

        return redirect()->to($targetRoute);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('panel.login');
    }
}
