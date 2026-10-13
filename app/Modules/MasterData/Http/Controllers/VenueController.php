<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\VenueRequest;
use App\Modules\MasterData\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class VenueController extends Controller
{
    public function index(Request $request): View
    {
        $query = Venue::query()
            ->with(['province', 'city', 'subdistrict'])
            ->withCount('courts');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('province_id')) {
            $query->where('province_id', $request->input('province_id'));
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $venues = $query->latest()->paginate(15)->withQueryString();
        $filters = $request->only(['search', 'province_id', 'city_id', 'is_active']);

        return view('admin.venues.index', compact('venues', 'filters'));
    }

    public function create(): View
    {
        return view('admin.venues.create');
    }

    public function store(VenueRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('venues', 'public');
        }

        Venue::create($data);

        return redirect()
            ->route('admin.venues.index')
            ->with('success', 'Venue berhasil ditambahkan.');
    }

    public function show(Venue $venue): View
    {
        $courts = $venue->courts()->latest()->get();

        return view('admin.venues.show', compact('venue', 'courts'));
    }

    public function edit(Venue $venue): View
    {
        return view('admin.venues.edit', compact('venue'));
    }

    public function update(VenueRequest $request, Venue $venue): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            if ($venue->image) {
                Storage::disk('public')->delete($venue->image);
            }
            $data['image'] = $request->file('image')->store('venues', 'public');
        } else {
            unset($data['image']);
        }

        $venue->update($data);

        return redirect()
            ->route('admin.venues.index')
            ->with('success', 'Venue berhasil diperbarui.');
    }

    public function destroy(Venue $venue): RedirectResponse
    {
        if ($venue->image) {
            Storage::disk('public')->delete($venue->image);
        }

        $venue->delete();

        return redirect()
            ->route('admin.venues.index')
            ->with('success', 'Venue berhasil dihapus.');
    }
}
