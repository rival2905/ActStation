<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('admin.auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'is_active' => true,
        ];
        $remember = $request->boolean('remember');

        if (! Auth::validate($credentials)) {
            $user = Auth::getProvider()->retrieveByCredentials(['email' => $request->input('email')]);
            event(new Failed('web', $user, $request->only('email', 'password')));

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => __('auth.failed')]);
        }

        /** @var User $user */
        $user = Auth::getProvider()->retrieveByCredentials($credentials);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $hasInternalRole = $user->roles->contains(
            fn ($role) => in_array($role->panel_access, ['internal', 'both'], true)
        );

        $targetRoute = $user->getPanelHomeRoute();

        if ($hasInternalRole) {
            $intended = $request->session()->get('url.intended');
            if ($intended !== null && str_starts_with($intended, url('/admin'))) {
                return redirect()->intended($targetRoute);
            }

            $request->session()->forget('url.intended');

            return redirect()->to($targetRoute);
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

        return redirect()->route('admin.login');
    }
}
