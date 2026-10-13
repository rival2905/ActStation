<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\Admin\StoreCategoryRequest;
use App\Modules\Event\Http\Requests\Admin\UpdateCategoryRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\DocumentType;
use App\Modules\Payment\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Event $event): View
    {
        $categories = $event->categories()->withCount('participants')->get();
        $developmentLevels = DevelopmentLevel::query()->where('is_active', true)->orderBy('sort_order')->get();
        $documentTypes = DocumentType::query()->where('is_active', true)->orderBy('sort_order')->get();
        $communities = DB::table('communities')->where('status', 'verified')->orderBy('name')->get(['id', 'name']);

        return view('admin.events.categories.index', compact('event', 'categories', 'developmentLevels', 'documentTypes', 'communities'));
    }

    public function store(StoreCategoryRequest $request, Event $event): RedirectResponse
    {
        $data = $request->categoryData(true);
        $data['slug'] = $this->uniqueSlug($event, $data['name']);

        $event->categories()->create($data);

        return back()->with('success', 'Kategori event berhasil ditambahkan.');
    }

    public function update(UpdateCategoryRequest $request, Event $event, EventCategory $category): RedirectResponse
    {
        abort_unless((int) $category->event_id === (int) $event->id, 404);

        $category->update($request->categoryData());

        return back()->with('success', 'Kategori event berhasil diperbarui.');
    }

    public function destroy(Event $event, EventCategory $category): RedirectResponse
    {
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

        return back()->with('success', 'Kategori event berhasil dihapus.');
    }

    public function toggleRosterVisibility(Event $event, EventCategory $category): RedirectResponse
    {
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
}
