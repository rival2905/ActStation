<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Services\EventVenueService;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
            ->with([
                'categories',
                'user',
                'sportCategory',
                'venue.province',
                'venue.city',
            ]);

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

        if ($request->filled('province_id')) {
            $provinceId = (string) $request->input('province_id');
            $query->whereHas('venue', function ($vq) use ($provinceId): void {
                $vq->where('province_id', $provinceId);
            });
        }

        if ($request->filled('city_id')) {
            $cityId = (string) $request->input('city_id');
            $cleanCityId = str_replace('.', '', $cityId);
            $dotCityId = strlen($cleanCityId) === 4 ? substr($cleanCityId, 0, 2).'.'.substr($cleanCityId, 2) : $cityId;
            $query->whereHas('venue', function ($vq) use ($cityId, $cleanCityId, $dotCityId): void {
                $vq->whereIn('city_id', array_unique([$cityId, $cleanCityId, $dotCityId]));
            });
        }

        $sort = (string) $request->input('sort', 'date_asc');
        match ($sort) {
            'date_desc' => $query->latest('starts_at'),
            'newest' => $query->latest('created_at'),
            default => $query->orderByRaw('CASE WHEN starts_at IS NULL THEN 1 ELSE 0 END, starts_at ASC'),
        };

        $events = $query->paginate(12)->withQueryString();
        $sportCategories = SportCategory::query()->where('is_active', true)->orderBy('name')->get();
        $provinces = DB::table('wilayah')->whereRaw('LENGTH(kode) = 2')->orderBy('nama')->get(['kode as id', 'nama as name']);

        $cities = collect();
        if ($request->filled('province_id')) {
            $pId = (string) $request->input('province_id');
            $cities = DB::table('wilayah')
                ->whereRaw('LENGTH(kode) = 5')
                ->where('kode', 'like', $pId.'.%')
                ->orderBy('nama')
                ->get(['kode as id', 'nama as name']);
        } else {
            $activeCityIds = DB::table('venues')
                ->whereNotNull('city_id')
                ->pluck('city_id')
                ->map(fn ($id) => strlen((string) $id) === 4 ? substr((string) $id, 0, 2).'.'.substr((string) $id, 2) : (string) $id)
                ->unique()
                ->all();

            if (! empty($activeCityIds)) {
                $cities = DB::table('wilayah')
                    ->whereIn('kode', $activeCityIds)
                    ->orderBy('nama')
                    ->get(['kode as id', 'nama as name']);
            }
        }

        return view('public.events.index', compact('events', 'sportCategories', 'provinces', 'cities', 'sort'));
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
                'documents',
            ])
            ->firstOrFail();

        $eventVenues = DB::table('venues')->whereIn('id', app(EventVenueService::class)->venueIds($event))->orderBy('name')->get(['id', 'name', 'address']);

        return view('public.events.show', compact('event', 'eventVenues'));
    }

    public function downloadDocument(string $slug, Document $document): StreamedResponse|RedirectResponse
    {
        $event = Event::query()->where('slug', $slug)->firstOrFail();

        if ($document->documentable_type !== Event::class || (int) $document->documentable_id !== (int) $event->id || ! $document->is_public) {
            abort(404, 'Dokumen publik tidak ditemukan untuk event ini.');
        }

        $downloadName = $document->name.(pathinfo($document->name, PATHINFO_EXTENSION) === '' && ($ext = pathinfo($document->file_path, PATHINFO_EXTENSION)) ? ".{$ext}" : '');

        if (Storage::disk('public')->exists($document->file_path)) {
            return Storage::disk('public')->download($document->file_path, $downloadName);
        }

        if (Storage::disk('local')->exists($document->file_path)) {
            return Storage::disk('local')->download($document->file_path, $downloadName);
        }

        abort(404, 'File fisik dokumen tidak ditemukan.');
    }

    public function sitemap(): Response
    {
        $events = Event::query()
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->get(['slug', 'updated_at']);

        $urls = [
            [
                'loc' => route('landing'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
            [
                'loc' => route('events.index'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '0.9',
            ],
            [
                'loc' => route('panduan.event'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ],
        ];

        foreach ($events as $event) {
            $lastmod = $event->updated_at?->toAtomString() ?? now()->toAtomString();
            $urls[] = [
                'loc' => route('events.show', $event->slug),
                'lastmod' => $lastmod,
                'changefreq' => 'daily',
                'priority' => '0.8',
            ];
            $urls[] = [
                'loc' => route('events.tournament', $event->slug),
                'lastmod' => $lastmod,
                'changefreq' => 'daily',
                'priority' => '0.7',
            ];
            $urls[] = [
                'loc' => route('events.schedule', $event->slug),
                'lastmod' => $lastmod,
                'changefreq' => 'daily',
                'priority' => '0.7',
            ];
            $urls[] = [
                'loc' => route('events.results', $event->slug),
                'lastmod' => $lastmod,
                'changefreq' => 'daily',
                'priority' => '0.7',
            ];
            $urls[] = [
                'loc' => route('events.tickets', $event->slug),
                'lastmod' => $lastmod,
                'changefreq' => 'daily',
                'priority' => '0.7',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= '    <url>'."\n";
            $xml .= '        <loc>'.htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8').'</loc>'."\n";
            $xml .= '        <lastmod>'.$u['lastmod'].'</lastmod>'."\n";
            $xml .= '        <changefreq>'.$u['changefreq'].'</changefreq>'."\n";
            $xml .= '        <priority>'.$u['priority'].'</priority>'."\n";
            $xml .= '    </url>'."\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
