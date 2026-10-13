<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePasswordRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        $user = auth()->user();

        return view('admin.profile.edit', compact('user'));
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $data = $request->safe()->only(['name', 'email', 'phone_number']);

        if ($request->hasFile('image')) {
            if ($user->image && Storage::disk('public')->exists($user->image)) {
                Storage::disk('public')->delete($user->image);
            }
            $data['image'] = $request->file('image')->store('avatars', 'public');
        }

        if (array_key_exists('phone_number', $data)) {
            if (! empty($data['phone_number'])) {
                $digits = preg_replace('/\D+/', '', (string) $data['phone_number']);
                if (str_starts_with($digits, '0')) {
                    $digits = '62'.substr($digits, 1);
                }
                $data['phone_normalized'] = '+'.$digits;
            } else {
                $data['phone_normalized'] = null;
            }
        }

        $user->update($data);

        return redirect()->route('admin.profile.edit')->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $user->update([
            'password' => $request->input('password'),
        ]);

        return redirect()->route('admin.profile.edit')->with('success', 'Password berhasil diperbarui.');
    }
}
