<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\DocumentTypeRequest;
use App\Modules\MasterData\Models\DocumentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentTypeController extends Controller
{
    public function index(Request $request): View
    {
        $query = DocumentType::query();

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('scope')) {
            $query->where('scope', $request->input('scope'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $documentTypes = $query->orderBy('sort_order')->orderBy('id')->paginate(15)->withQueryString();
        $filters = $request->only(['search', 'scope', 'is_active']);

        return view('admin.document-types.index', compact('documentTypes', 'filters'));
    }

    public function create(): View
    {
        return view('admin.document-types.create');
    }

    public function store(DocumentTypeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        DocumentType::create($data);

        return redirect()
            ->route('admin.document-types.index')
            ->with('success', 'Jenis dokumen berhasil ditambahkan.');
    }

    public function edit(DocumentType $documentType): View
    {
        return view('admin.document-types.edit', compact('documentType'));
    }

    public function update(DocumentTypeRequest $request, DocumentType $documentType): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $documentType->update($data);

        return redirect()
            ->route('admin.document-types.index')
            ->with('success', 'Jenis dokumen berhasil diperbarui.');
    }

    public function destroy(DocumentType $documentType): RedirectResponse
    {
        $documentType->delete();

        return redirect()
            ->route('admin.document-types.index')
            ->with('success', 'Jenis dokumen berhasil dihapus.');
    }
}
