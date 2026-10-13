<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizerDocumentController extends Controller
{
    public function index(Event $event): View
    {
        $this->authorizeAccess($event);

        $workspace = request()->routeIs('admin.*') ? 'admin' : 'organizer';
        $event->loadMissing(['documents.verifiedBy', 'venue', 'user']);

        return view('panel.organizer.workspace.documents', compact('event', 'workspace'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeOrganizer($event);

        $validated = $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $filePath = $request->file('file')->store('event_documents', 'public');

        if ($validated['category'] === 'venue_approval') {
            $event->update(['venue_approval_doc' => $filePath]);
        }
        $isPublic = $request->has('is_public')
            ? $request->boolean('is_public')
            : ($validated['category'] === 'technical_handbook');

        $event->documents()->create([
            'category' => $validated['category'],
            'name' => $validated['name'],
            'document_number' => $validated['document_number'] ?? null,
            'file_path' => $filePath,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
            'is_public' => $isPublic,
        ]);

        return back()->with('success', "Dokumen [{$validated['name']}] berhasil diunggah.");
    }

    public function download(Event $event, Document $document): StreamedResponse|RedirectResponse
    {
        $this->authorizeAccess($event);

        if ($document->documentable_type !== Event::class || (int) $document->documentable_id !== (int) $event->id) {
            abort(404, 'Dokumen tidak ditemukan untuk event ini.');
        }

        if (Storage::disk('public')->exists($document->file_path)) {
            return Storage::disk('public')->download($document->file_path, $document->name);
        }

        if (Storage::disk('local')->exists($document->file_path)) {
            return Storage::disk('local')->download($document->file_path, $document->name);
        }

        abort(404, 'File dokumen fisik tidak ditemukan di penyimpanan server.');
    }

    public function destroy(Event $event, Document $document): RedirectResponse
    {
        $this->authorizeOrganizer($event);

        if ($document->documentable_type !== Event::class || (int) $document->documentable_id !== (int) $event->id) {
            abort(404, 'Dokumen tidak ditemukan untuk event ini.');
        }

        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        if (Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        if ($event->venue_approval_doc === $document->file_path) {
            $event->update(['venue_approval_doc' => null]);
        }

        $docName = $document->name;
        $document->delete();

        return back()->with('success', "Dokumen [{$docName}] berhasil dihapus.");
    }

    public function togglePublic(Event $event, Document $document): RedirectResponse
    {
        $this->authorizeOrganizer($event);

        if ($document->documentable_type !== Event::class || (int) $document->documentable_id !== (int) $event->id) {
            abort(404, 'Dokumen tidak ditemukan untuk event ini.');
        }

        $document->update([
            'is_public' => ! $document->is_public,
        ]);

        $statusLabel = $document->is_public ? 'ditampilkan di publik' : 'disembunyikan dari publik';

        return back()->with('success', "Visibilitas dokumen [{$document->name}] diubah: {$statusLabel}.");
    }

    public function verify(Request $request, Event $event, Document $document): RedirectResponse
    {
        if ($document->documentable_type !== Event::class || (int) $document->documentable_id !== (int) $event->id) {
            abort(404, 'Dokumen tidak ditemukan untuk event ini.');
        }

        $document->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => Auth::id(),
            'notes' => $request->input('notes') ?? $document->notes,
        ]);

        return back()->with('success', "Dokumen [{$document->name}] berhasil diverifikasi.");
    }

    public function reject(Request $request, Event $event, Document $document): RedirectResponse
    {
        if ($document->documentable_type !== Event::class || (int) $document->documentable_id !== (int) $event->id) {
            abort(404, 'Dokumen tidak ditemukan untuk event ini.');
        }

        $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $document->update([
            'status' => 'rejected',
            'verified_at' => now(),
            'verified_by' => Auth::id(),
            'notes' => $request->input('notes'),
        ]);

        return back()->with('success', "Dokumen [{$document->name}] ditolak dengan catatan.");
    }

    private function authorizeAccess(Event $event): void
    {
        if (request()->routeIs('admin.*')) {
            return;
        }

        if ($event->user_id !== Auth::id()) {
            abort(403, 'Akses ditolak.');
        }
    }

    private function authorizeOrganizer(Event $event): void
    {
        if (request()->routeIs('admin.*')) {
            return;
        }

        if ($event->user_id !== Auth::id() && ! Auth::user()?->hasRole('superadmin')) {
            abort(403, 'Akses ditolak.');
        }
    }
}
