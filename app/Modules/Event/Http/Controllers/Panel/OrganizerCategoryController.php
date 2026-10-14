<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\Panel\StoreOrganizerCategoryRequest;
use App\Modules\Event\Http\Requests\Panel\UpdateOrganizerCategoryRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\DocumentType;
use App\Modules\Payment\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrganizerCategoryController extends Controller
{
    public function index(Event $event): View
    {
        $this->authorizeOrganizer($event);

        $categories = $event->categories()->withCount('participants')->get();
        $developmentLevels = DevelopmentLevel::query()->where('is_active', true)->orderBy('sort_order')->get();
        $allReferencedCodes = $categories->flatMap(fn ($c) => (array) ($c->required_documents ?? []))
            ->unique()
            ->filter()
            ->values()
            ->all();

        $documentTypes = DocumentType::query()
            ->where(function ($q) use ($allReferencedCodes): void {
                $q->where('is_active', true);
                if (! empty($allReferencedCodes)) {
                    $q->orWhereIn('code', $allReferencedCodes);
                }
            })
            ->orderBy('sort_order')
            ->get();
        $communities = DB::table('communities')->where('status', 'verified')->orderBy('name')->get(['id', 'name']);

        return view('panel.organizer.categories.index', compact('event', 'categories', 'developmentLevels', 'documentTypes', 'communities'));
    }

    public function store(StoreOrganizerCategoryRequest $request, Event $event): RedirectResponse
    {
        $this->authorizeOrganizer($event);

        $data = $request->categoryData(true);
        $data['slug'] = $this->uniqueSlug($event, $data['name']);

        $event->categories()->create($data);

        return back()->with('success', 'Kategori kompetisi baru berhasil ditambahkan.');
    }

    public function update(UpdateOrganizerCategoryRequest $request, Event $event, EventCategory $category): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        abort_unless((int) $category->event_id === (int) $event->id, 404);

        $category->update($request->categoryData());

        return back()->with('success', 'Kategori kompetisi berhasil diperbarui.');
    }

    public function destroy(Event $event, EventCategory $category): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        abort_unless((int) $category->event_id === (int) $event->id, 404);

        $hasParticipants = $category->participants()->exists();
        $hasTransactions = Transaction::query()
            ->where('payable_type', 'event_participant')
            ->whereIn('payable_id', $category->participants()->select('id'))
            ->exists();

        if ($hasParticipants || $hasTransactions) {
            return back()->with('error', 'Kategori tidak dapat dihapus karena sudah memiliki riwayat tim peserta atau transaksi.');
        }

        $category->delete();

        return back()->with('success', 'Kategori berhasil dihapus.');
    }

    public function toggleRosterVisibility(Event $event, EventCategory $category): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        abort_unless((int) $category->event_id === (int) $event->id, 404);

        $category->update(['is_roster_public' => ! $category->is_roster_public]);

        return back()->with('success', 'Visibilitas roster kategori berhasil diperbarui.');
    }

    private function uniqueSlug(Event $event, string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'category';
        $slug = $baseSlug;
        $suffix = 1;

        while ($event->categories()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        return $slug;
    }

    private function authorizeOrganizer(Event $event): void
    {
        if ($event->user_id !== Auth::id() && ! Auth::user()?->hasRole('superadmin')) {
            abort(403, 'Akses ditolak.');
        }
    }
}
