<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Models\Event;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicEventController extends Controller
{
    public function landing(Request $request): View
    {
        $featuredEvents = Event::query()
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->with(['categories', 'user', 'sportCategory'])
            ->latest('starts_at')
            ->take(6)
            ->get();

        return view('public.landing', compact('featuredEvents'));
    }

    public function index(Request $request): View
    {
        $query = Event::query()
            ->where('status', 'published')
            ->with(['categories', 'user', 'sportCategory']);

        if ($request->filled('q')) {
            $searchTerm = '%'.trim((string) $request->input('q')).'%';
            $query->where(function ($q) use ($searchTerm): void {
                $q->where('title', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm);
            });
        }

        if ($request->filled('sport_category_id')) {
            $query->where('sport_category_id', $request->input('sport_category_id'));
        }

        $events = $query->latest('starts_at')->paginate(12)->withQueryString();
        $sportCategories = SportCategory::query()->where('is_active', true)->orderBy('name')->get();

        return view('public.events.index', compact('events', 'sportCategories'));
    }

    public function show(string $slug): View
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->with([
                'categories.participants' => fn ($q) => $q->whereIn('status', ['confirmed', 'registered']),
                'user',
                'sportCategory',
                'documents' => fn ($q) => $q->where('is_public', true),
            ])
            ->firstOrFail();

        return view('public.events.show', compact('event'));
    }

    public function downloadDocument(string $slug, Document $document): StreamedResponse|RedirectResponse
    {
        $event = Event::query()->where('slug', $slug)->firstOrFail();

        if ($document->documentable_type !== Event::class || (int) $document->documentable_id !== (int) $event->id || ! $document->is_public) {
            abort(404, 'Dokumen publik tidak ditemukan untuk event ini.');
        }

        if (Storage::disk('public')->exists($document->file_path)) {
            return Storage::disk('public')->download($document->file_path, $document->name);
        }

        if (Storage::disk('local')->exists($document->file_path)) {
            return Storage::disk('local')->download($document->file_path, $document->name);
        }

        abort(404, 'File fisik dokumen tidak ditemukan.');
    }
}
