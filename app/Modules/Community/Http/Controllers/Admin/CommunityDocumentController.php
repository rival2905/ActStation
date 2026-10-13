<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Community\Http\Requests\CommunityDocumentRequest;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunityDocumentController extends Controller
{
    public function index(Community $community): View
    {
        $documents = $community->documents()->with('verifiedBy')->latest()->get();

        return view('admin.communities.documents.index', compact('community', 'documents'));
    }

    public function store(CommunityDocumentRequest $request, Community $community): RedirectResponse
    {
        $data = $request->validated();
        $filePath = $request->file('file')->store('community_documents', 'local');

        $community->documents()->create([
            'category' => $data['category'],
            'name' => $data['name'],
            'document_number' => $data['document_number'] ?? null,
            'file_path' => $filePath,
            'status' => 'pending',
        ]);

        return redirect()
            ->route('admin.communities.documents.index', $community)
            ->with('success', 'Dokumen berhasil diunggah.');
    }

    public function download(Community $community, Document $document): StreamedResponse
    {
        abort_if($document->documentable_type !== Community::class || (int) $document->documentable_id !== $community->id, 404);

        if (! $document->file_path || ! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File dokumen tidak ditemukan.');
        }

        return Storage::disk('local')->download($document->file_path, $document->name);
    }

    public function destroy(Community $community, Document $document): RedirectResponse
    {
        abort_if($document->documentable_type !== Community::class || (int) $document->documentable_id !== $community->id, 404);

        if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        $document->delete();

        return redirect()
            ->route('admin.communities.documents.index', $community)
            ->with('success', 'Dokumen berhasil dihapus.');
    }

    public function verify(Request $request, Document $document): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:verified,rejected'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $document->update([
            'status' => $validated['status'],
            'verified_at' => now(),
            'verified_by' => auth()->id(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Status dokumen berhasil diperbarui.');
    }
}
