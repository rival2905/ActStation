<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Community\Http\Requests\CommunityRequest;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\SchoolRegistration;
use App\Modules\Community\Services\CommunityLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommunityController extends Controller
{
    public function index(Request $request): View
    {
        $query = Community::query()->with('sportCategory');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('subtype')) {
            $query->where('subtype', $request->input('subtype'));
        }

        if ($request->filled('sport_category_id')) {
            $query->where('sport_category_id', $request->input('sport_category_id'));
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }

        $communities = $query->latest()->paginate(100)->withQueryString();
        $filters = $request->only(['search', 'status', 'subtype', 'sport_category_id', 'city_id']);
        $sportCategories = DB::table('sport_categories')->where('is_active', true)->get(['id', 'name']);

        return view('admin.communities.index', compact('communities', 'filters', 'sportCategories'));
    }

    public function create(): View
    {
        $sportCategories = DB::table('sport_categories')->where('is_active', true)->get(['id', 'name']);

        return view('admin.communities.create', compact('sportCategories'));
    }

    public function store(CommunityRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('communities', 'public');
        }

        $community = Community::create($data);

        return redirect()
            ->route('admin.communities.index')
            ->with('success', 'Komunitas berhasil ditambahkan.');
    }

    public function show(Community $community): View
    {
        $community->load([
            'officials.user',
            'coaches.user',
            'athletes.user',
            'documents.verifiedBy',
            'activities',
            'verifiedBy',
        ]);

        $schoolRegistration = $community->subtype === 'school'
            ? SchoolRegistration::where('community_id', $community->id)->with('user')->latest()->first()
            : null;

        return view('admin.communities.show', compact('community', 'schoolRegistration'));
    }

    public function edit(Community $community): View
    {
        $sportCategories = DB::table('sport_categories')->where('is_active', true)->get(['id', 'name']);

        return view('admin.communities.edit', compact('community', 'sportCategories'));
    }

    public function update(CommunityRequest $request, Community $community): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            if ($community->logo && Storage::disk('public')->exists($community->logo)) {
                Storage::disk('public')->delete($community->logo);
            }
            $data['logo'] = $request->file('logo')->store('communities', 'public');
        }

        $community->update($data);

        return redirect()
            ->route('admin.communities.index')
            ->with('success', 'Komunitas berhasil diperbarui.');
    }

    public function destroy(Community $community): RedirectResponse
    {
        $community->delete();

        return redirect()
            ->route('admin.communities.index')
            ->with('success', 'Komunitas berhasil dihapus.');
    }

    public function verify(Request $request, Community $community, CommunityLifecycle $lifecycle): RedirectResponse
    {
        $decision = (string) $request->input('decision', 'verified');

        if ($decision === 'rejected') {
            $validated = $request->validate([
                'reject_reason' => ['required', 'string', 'min:3'],
            ]);

            try {
                $lifecycle->reject($community, $validated['reject_reason']);
            } catch (\Throwable $e) {
                throw ValidationException::withMessages(['status' => $e->getMessage()]);
            }

            return redirect()->back()->with('success', 'Komunitas berhasil ditolak.');
        }

        try {
            $lifecycle->verify($community);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Komunitas berhasil diverifikasi.');
    }

    public function revoke(Request $request, Community $community, CommunityLifecycle $lifecycle): RedirectResponse
    {
        $validated = $request->validate([
            'reject_reason' => ['required', 'string', 'min:3'],
        ]);

        try {
            $lifecycle->revoke($community, $validated['reject_reason']);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Verifikasi komunitas berhasil dicabut.');
    }
}
