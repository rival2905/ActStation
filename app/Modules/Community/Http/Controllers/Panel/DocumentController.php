<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Modules\Community\Http\Requests\Panel\PanelDocumentRequest;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends PanelBaseController
{
    public function index(Request $request): View
    {
        $community = $this->currentCommunity($request);
        $this->authorize('view', $community);

        $documents = $community->documents()->with('verifiedBy')->latest()->get();

        return view('panel.documents.index', compact('community', 'documents'));
    }

    public function store(PanelDocumentRequest $request): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);

        $data = $request->validated();

        if (in_array($community->status, ['pending', 'verified'], true) && $community->isStatutoryCategory($data['category'])) {
            abort(403, 'Dokumen legalitas wajib tidak dapat diunggah saat komunitas dalam peninjauan atau telah terverifikasi.');
        }

        // Stored on private 'local' disk (inaccessible via public storage URL)
        $filePath = $request->file('file')->store('community_documents', 'local');

        $community->documents()->create([
            'category' => $data['category'],
            'name' => $data['name'],
            'document_number' => $data['document_number'] ?? null,
            'file_path' => $filePath,
            'status' => 'pending',
        ]);

        return redirect()
            ->route('panel.documents.index')
            ->with('success', 'Dokumen berhasil diunggah.');
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('view', $community);

        if ($document->documentable_type !== Community::class || (int) $document->documentable_id !== $community->id) {
            abort(403, 'Akses ditolak untuk dokumen komunitas lain.');
        }

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File dokumen tidak ditemukan.');
        }

        return Storage::disk('local')->download($document->file_path, $document->name);
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);

        if ($document->documentable_type !== Community::class || (int) $document->documentable_id !== $community->id) {
            abort(403, 'Akses ditolak untuk dokumen komunitas lain.');
        }

        $filePath = null;
        DB::transaction(function () use ($community, $document, &$filePath): void {
            /** @var Community $freshCommunity */
            $freshCommunity = Community::where('id', $community->id)->lockForUpdate()->firstOrFail();

            $isPendingOrVerified = in_array($freshCommunity->status, ['pending', 'verified'], true);
            $isStatutory = $freshCommunity->isStatutoryCategory($document->category);

            if ($document->status === 'verified' || ($isPendingOrVerified && $isStatutory)) {
                abort(403, 'Dokumen terverifikasi atau dokumen legalitas wajib tidak dapat dihapus saat peninjauan atau setelah terverifikasi.');
            }

            $filePath = $document->file_path;
            $document->delete();
        });

        if ($filePath && Storage::disk('local')->exists($filePath)) {
            Storage::disk('local')->delete($filePath);
        }

        return redirect()
            ->route('panel.documents.index')
            ->with('success', 'Dokumen berhasil dihapus.');
    }
}
