<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Community\Http\Requests\Panel\SchoolAuthorityRequest;
use App\Modules\Community\Models\SchoolAuthorityChange;
use App\Modules\Community\Services\SchoolAuthorityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class SchoolAuthorityController extends Controller
{
    public function store(SchoolAuthorityRequest $request, int $registration, SchoolAuthorityService $service): RedirectResponse
    {
        $targetEmail = $request->validated('target_email');
        $targetUserId = $request->validated('target_user_id') ? (int) $request->validated('target_user_id') : null;

        $service->requestChange(
            $registration,
            $request->user(),
            $targetEmail,
            $targetUserId,
            $request->validated('new_email')
        );

        return back()->with('success', 'Konfirmasi pengalihan dikirim ke email tujuan. Pengurus lama tetap aktif sampai pengganti menerima.');
    }

    public function officialEmail(SchoolAuthorityRequest $request, int $registration, SchoolAuthorityService $service): RedirectResponse
    {
        $service->requestOfficialEmailChange($registration, $request->user(), $request->validated('new_email'));

        return back()->with('success', 'Permintaan verifikasi dikirim ke email resmi baru. Email lama tetap berlaku sampai terkonfirmasi.');
    }

    public function show(Request $request, int $change, SchoolAuthorityService $service): View
    {
        $token = $request->query('token');
        abort_unless(is_string($token) && strlen($token) === 64, 403);

        $changeModel = $service->pending($change, $request->user(), $token);
        $targetEmail = $changeModel->target_email ?? $changeModel->targetUser?->email;

        $targetUser = $changeModel->target_user_id
            ? User::find($changeModel->target_user_id)
            : ($targetEmail ? User::where('email', $targetEmail)->first() : null);

        $mode = 'action';
        $isEmailChange = $service->isEmailChange($changeModel);
        if ($isEmailChange) {
            $mode = Auth::check() ? 'action' : 'login';
        } elseif (! $targetUser) {
            $mode = 'register';
        } elseif (! Auth::check()) {
            $mode = 'login';
        }

        if ($mode === 'login') {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return view('panel.school_registrations.authority', [
            'change' => $changeModel,
            'targetEmail' => $targetEmail,
            'targetUser' => $targetUser,
            'token' => $token,
            'mode' => $mode,
            'isEmailChange' => $isEmailChange,
        ]);
    }

    public function accept(SchoolAuthorityRequest $request, int $change, SchoolAuthorityService $service): RedirectResponse
    {
        abort_unless($request->user() && $request->user()->is_active, 403);
        $pending = $service->pending($change, $request->user(), $request->validated('token'));
        $emailOnly = $service->isEmailChange($pending);
        $communityId = $pending->registration->community_id;
        $service->accept($change, $request->user(), $request->validated('token'));
        $request->session()->put('current_community_id', $communityId);

        return redirect()->route('panel.profile.show')->with('success', $emailOnly ? 'Email resmi sekolah berhasil diperbarui.' : 'Penanggung jawab sekolah berhasil diperbarui.');
    }

    public function reject(Request $request, int $change, SchoolAuthorityService $service): RedirectResponse
    {
        $token = $request->input('token') ?: $request->query('token');
        abort_unless(is_string($token) && strlen($token) === 64, 403);

        $service->reject($change, $request->user(), $token);

        return redirect()->route('panel.profile.show')->with('info', 'Pengalihan penanggung jawab sekolah telah ditolak.');
    }

    public function register(Request $request, int $change, SchoolAuthorityService $service): RedirectResponse
    {
        $token = $request->query('token') ?: $request->input('token');
        abort_unless(is_string($token) && strlen($token) === 64, 403);

        $changeModel = SchoolAuthorityChange::findOrFail($change);
        abort_if($service->isEmailChange($changeModel), 403);
        abort_unless(
            $changeModel->status === 'pending'
            && $changeModel->expires_at->isFuture()
            && hash_equals($changeModel->token_hash, hash('sha256', $token)),
            403
        );

        $request->validate(['phone_number' => ['required', 'string', 'min:9', 'max:20']]);
        $rawPhone = preg_replace('/[^0-9]/', '', $request->input('phone_number'));
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
            'phone_number' => ['required', 'string', 'min:9', 'max:20'],
            'phone_normalized' => ['required', 'string', 'max:20', 'unique:users,phone_normalized'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'phone_normalized.unique' => 'Nomor WhatsApp / telepon sudah terdaftar di sistem.',
        ]);

        $targetEmail = $changeModel->target_email;
        abort_unless($targetEmail && ! User::where('email', $targetEmail)->exists(), 422);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $targetEmail,
            'phone_number' => $validated['phone_number'],
            'phone_normalized' => $validated['phone_normalized'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web'], ['panel_access' => 'external']);
        $user->assignRole('member');

        $changeModel->update(['target_user_id' => $user->id]);

        Auth::login($user);
        $request->session()->regenerate();

        $confirmUrl = URL::temporarySignedRoute(
            'panel.school-authority.confirm',
            $changeModel->expires_at,
            ['change' => $changeModel->id, 'token' => $token]
        );

        return redirect()->to($confirmUrl)->with('success', 'Akun berhasil dibuat. Silakan konfirmasi persetujuan pengalihan penanggung jawab.');
    }
}
