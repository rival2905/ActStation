<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Ticketing\Http\Requests\Admin\StoreTicketCategoryRequest;
use App\Modules\Ticketing\Http\Requests\Admin\UpdateTicketCategoryRequest;
use App\Modules\Ticketing\Models\TicketCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TicketCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = TicketCategory::query();

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('event_id')) {
            $query->where('event_id', $request->input('event_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->input('is_active'));
        }

        $categories = $query
            ->latest()
            ->paginate(15)
            ->withQueryString();
        $events = DB::table('events')->select(['id', 'title'])->get();

        return view('admin.ticket-categories.index', compact('categories', 'events'));
    }

    public function create(): View
    {
        $events = DB::table('events')->select(['id', 'title'])->get();

        return view('admin.ticket-categories.create', compact('events'));
    }

    public function store(StoreTicketCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        TicketCategory::create($data);

        return redirect()
            ->route('admin.ticket-categories.index')
            ->with('success', 'Kategori tiket berhasil dibuat.');
    }

    public function edit(TicketCategory $ticketCategory): View
    {
        $events = DB::table('events')->select(['id', 'title'])->get();

        return view('admin.ticket-categories.edit', [
            'category' => $ticketCategory,
            'events' => $events,
        ]);
    }

    public function update(UpdateTicketCategoryRequest $request, TicketCategory $ticketCategory): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $ticketCategory->update($data);

        return redirect()
            ->route('admin.ticket-categories.index')
            ->with('success', 'Kategori tiket berhasil diperbarui.');
    }

    public function destroy(TicketCategory $ticketCategory): RedirectResponse
    {
        if ($ticketCategory->sold_count > 0) {
            return back()->with('error', 'Kategori tiket tidak dapat dihapus karena tiket telah terjual.');
        }

        $ticketCategory->delete();

        return redirect()
            ->route('admin.ticket-categories.index')
            ->with('success', 'Kategori tiket berhasil dihapus.');
    }
}
