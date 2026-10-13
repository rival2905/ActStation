<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\VenueCourtRequest;
use App\Modules\MasterData\Models\Venue;
use App\Modules\MasterData\Models\VenueCourt;
use Illuminate\Http\RedirectResponse;

class VenueCourtController extends Controller
{
    public function index(Venue $venue): RedirectResponse
    {
        return redirect()->route('admin.venues.show', $venue);
    }

    public function store(VenueCourtRequest $request, Venue $venue): RedirectResponse
    {
        $venue->courts()->create($request->validated());

        return redirect()
            ->route('admin.venues.show', $venue)
            ->with('success', 'Lapangan berhasil ditambahkan.');
    }

    public function update(VenueCourtRequest $request, Venue $venue, VenueCourt $court): RedirectResponse
    {
        $court->update($request->validated());

        return redirect()
            ->route('admin.venues.show', $venue)
            ->with('success', 'Lapangan berhasil diperbarui.');
    }

    public function destroy(Venue $venue, VenueCourt $court): RedirectResponse
    {
        $court->delete();

        return redirect()
            ->route('admin.venues.show', $venue)
            ->with('success', 'Lapangan berhasil dihapus.');
    }
}
