<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class UserProfileController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->is_active, 403);
        $user = $request->user();

        return view('user-profile.edit', [
            'user' => $user,
            'educations' => $user->educations()->latest('updated_at')->get(),
            'documents' => $user->documents()->where('is_public', false)->latest()->get(),
            'layout' => $user->roles->contains(fn ($role): bool => in_array($role->panel_access, ['internal', 'both'], true)) ? 'layouts.app' : 'layouts.panel',
        ]);
    }

    public function update(UpdateUserProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->only(['name', 'email', 'phone_number']);
        $digits = preg_replace('/\D+/', '', (string) ($data['phone_number'] ?? ''));
        $data['phone_normalized'] = $digits === '' ? null : '+'.(str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits);
        $oldImage = $user->image;
        $newImage = $request->file('image')?->store('avatars', 'public');
        if ($newImage) {
            $data['image'] = $newImage;
        }
        try {
            $user->update($data);
        } catch (Throwable $e) {
            if ($newImage) {
                Storage::disk('public')->delete($newImage);
            }
            throw $e;
        }
        if ($newImage && $oldImage && str_starts_with($oldImage, 'avatars/') && ! str_contains($oldImage, '..')) {
            Storage::disk('public')->delete($oldImage);
        }

        return redirect()->route('user-profile.edit')->with('success', 'Profil pribadi berhasil diperbarui.');
    }

    public function download(Request $request, int $document): StreamedResponse
    {
        abort_unless($request->user()->is_active, 403);
        $doc = $request->user()->documents()->where('is_public', false)->findOrFail($document);
        $path = (string) $doc->file_path;
        abort_if($path === '' || str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, ':') || str_contains($path, '\\') || str_contains($path, "\0"), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
