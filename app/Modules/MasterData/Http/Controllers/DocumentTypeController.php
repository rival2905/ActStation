<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\DocumentTypeRequest;
use App\Modules\MasterData\Models\DocumentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        DB::transaction(function () use ($data, $documentType): void {
            /** @var DocumentType $locked */
            $locked = DocumentType::query()->whereKey($documentType->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isInUse()) {
                $errors = [];

                if (array_key_exists('code', $data) && $data['code'] !== $locked->code) {
                    $errors['code'] = 'Kode jenis dokumen tidak dapat diubah karena sedang digunakan.';
                }

                if (array_key_exists('scope', $data) && $data['scope'] !== $locked->scope) {
                    $errors['scope'] = 'Scope jenis dokumen tidak dapat diubah karena sedang digunakan.';
                }

                if (array_key_exists('applicable_entities', $data)) {
                    if (DocumentType::normalizeEntities($data['applicable_entities']) !== DocumentType::normalizeEntities($locked->applicable_entities)) {
                        $errors['applicable_entities'] = 'Entitas yang berlaku tidak dapat diubah karena sedang digunakan.';
                    }
                }

                if (! empty($errors)) {
                    throw ValidationException::withMessages($errors);
                }
            }

            $locked->update($data);
        });

        return redirect()
            ->route('admin.document-types.index')
            ->with('success', 'Jenis dokumen berhasil diperbarui.');
    }

    public function destroy(DocumentType $documentType): RedirectResponse
    {
        return DB::transaction(function () use ($documentType): RedirectResponse {
            /** @var DocumentType $locked */
            $locked = DocumentType::query()->whereKey($documentType->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isInUse()) {
                return back()->with('error', 'Jenis dokumen tidak dapat dihapus karena sedang digunakan.');
            }

            $locked->delete();

            return redirect()
                ->route('admin.document-types.index')
                ->with('success', 'Jenis dokumen berhasil dihapus.');
        });
    }
}
