<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Http\Requests\GalleryMediaRequest;
use App\Modules\Content\Http\Requests\GalleryRequest;
use App\Modules\Content\Models\Gallery;
use App\Modules\Content\Models\GalleryMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GalleryController extends Controller
{
    public function index(Request $request): View
    {
        $query = Gallery::query()->withCount('media');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $galleries = $query->latest()->paginate(15)->withQueryString();
        $filters = $request->only(['search']);

        return view('admin.galleries.index', compact('galleries', 'filters'));
    }

    public function create(): View
    {
        return view('admin.galleries.create');
    }

    public function store(GalleryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('galleries', 'public');
        }

        Gallery::create($data);

        return redirect()
            ->route('admin.galleries.index')
            ->with('success', 'Galeri berhasil ditambahkan.');
    }

    public function show(Gallery $gallery): View
    {
        $gallery->load(['media' => fn ($q) => $q->orderBy('sort_order')]);

        return view('admin.galleries.show', compact('gallery'));
    }

    public function edit(Gallery $gallery): View
    {
        return view('admin.galleries.edit', compact('gallery'));
    }

    public function update(GalleryRequest $request, Gallery $gallery): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('cover_image')) {
            if ($gallery->cover_image && Storage::disk('public')->exists($gallery->cover_image)) {
                Storage::disk('public')->delete($gallery->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('galleries', 'public');
        } else {
            unset($data['cover_image']);
        }
        $gallery->update($data);

        return redirect()
            ->route('admin.galleries.index')
            ->with('success', 'Galeri berhasil diperbarui.');
    }

    public function destroy(Gallery $gallery): RedirectResponse
    {
        if ($gallery->cover_image && Storage::disk('public')->exists($gallery->cover_image)) {
            Storage::disk('public')->delete($gallery->cover_image);
        }

        foreach ($gallery->media as $media) {
            if ($media->type === 'photo' && Storage::disk('public')->exists($media->file_path)) {
                Storage::disk('public')->delete($media->file_path);
            }
        }

        $gallery->delete();

        return redirect()
            ->route('admin.galleries.index')
            ->with('success', 'Galeri berhasil dihapus.');
    }

    public function storeMedia(GalleryMediaRequest $request, Gallery $gallery): RedirectResponse
    {
        $data = $request->validated();

        if ($data['type'] === 'photo') {
            $filePath = $request->file('file')->store('galleries', 'public');
        } else {
            $filePath = (string) $data['video_url'];
        }

        $gallery->media()->create([
            'type' => $data['type'],
            'file_path' => $filePath,
            'caption' => $data['caption'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return redirect()
            ->route('admin.galleries.show', $gallery)
            ->with('success', 'Media berhasil ditambahkan.');
    }

    public function destroyMedia(Gallery $gallery, GalleryMedia $media): RedirectResponse
    {
        abort_unless((int) $media->gallery_id === (int) $gallery->id, 404);

        if ($media->type === 'photo' && Storage::disk('public')->exists($media->file_path)) {
            Storage::disk('public')->delete($media->file_path);
        }

        $media->delete();

        return redirect()
            ->route('admin.galleries.show', $gallery)
            ->with('success', 'Media berhasil dihapus.');
    }
}
