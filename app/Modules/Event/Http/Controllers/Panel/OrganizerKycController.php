<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrganizerKycController extends Controller
{
    public function show(): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('panel.organizer.apply', compact('user'));
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'identity_number' => ['required', 'string', 'max:30'],
            'identity_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'bank_name' => ['required', 'string', 'max:50'],
            'bank_account_number' => ['required', 'string', 'max:50'],
            'bank_account_holder' => ['required', 'string', 'max:255'],
            'emergency_contact' => ['required', 'string', 'max:20'],
        ]);

        $updateData = [
            'identity_number' => $validated['identity_number'],
            'bank_name' => $validated['bank_name'],
            'bank_account_number' => $validated['bank_account_number'],
            'bank_account_holder' => $validated['bank_account_holder'],
            'emergency_contact' => $validated['emergency_contact'],
            'kyc_status' => 'verified',
        ];

        if ($request->hasFile('identity_image')) {
            $path = $request->file('identity_image')->store('organizer_kyc', 'local');
            $updateData['identity_image'] = $path;
        }

        $user->update($updateData);

        if (! $user->hasRole('organizer')) {
            $user->assignRole('organizer');
        }

        return redirect()
            ->route('panel.organizer.events.create')
            ->with('success', 'Profil PIC penyelenggara berhasil diverifikasi! Anda sekarang dapat membuat turnamen baru.');
    }
}
