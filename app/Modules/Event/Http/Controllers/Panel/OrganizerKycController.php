<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Contracts\CommunityLookup;
use App\Contracts\EntityRequirements;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Event\Http\Requests\Panel\OrganizerKycRequest;
use App\Modules\Event\Services\OrganizerKycService;
use App\Modules\Event\Services\OrganizerRequirements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizerKycController extends Controller
{
    public function __construct(
        private readonly CommunityLookup $communityLookup,
        private readonly OrganizerRequirements $organizerRequirements,
    ) {}

    public function show(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $banks = OrganizerKycRequest::BANKS;

        $organizerCommunity = $this->communityLookup->findForUser($user->id);

        $legalDocumentLabels = EntityRequirements::DOCUMENT_LABELS;

        $requiredLegalDocumentCodes = [];
        $entityLegalDocuments = [];

        if ($organizerCommunity) {
            $requiredLegalDocumentCodes = EntityRequirements::legalDocumentCodes(
                $organizerCommunity->subtype,
                $organizerCommunity->institution_type
            );

            $entityLegalDocuments = $this->communityLookup->legalDocumentsForUser($user->id, $organizerCommunity->id);
        }

        $organizerDocumentLabels = OrganizerRequirements::DOCUMENT_LABELS;
        $organizerDocuments = $user->documents()
            ->whereIn('category', OrganizerRequirements::categories())
            ->get()
            ->keyBy(fn ($doc) => Str::after((string) $doc->category, 'organizer_'));

        $organizerDocumentCodes = $this->organizerRequirements->documentCodes($organizerCommunity !== null);

        $reusableDocuments = [];
        $hasSelectedSameEntityPrior = $user->organizer_community_id !== null
            && $organizerCommunity !== null
            && (int) $user->organizer_community_id === (int) $organizerCommunity->id;

        foreach ($organizerDocuments as $code => $doc) {
            $storageExists = $doc->file_path && Storage::disk('local')->exists($doc->file_path);
            $isNotRejected = $doc->status !== 'rejected';
            $isPrivate = ! $doc->is_public;

            if ($storageExists && $isNotRejected && $isPrivate) {
                if ($code === 'authorization_letter') {
                    if ($hasSelectedSameEntityPrior) {
                        $reusableDocuments[$code] = true;
                    }
                } else {
                    $reusableDocuments[$code] = true;
                }
            }
        }

        return view('panel.organizer.apply', compact(
            'user',
            'banks',
            'organizerCommunity',
            'entityLegalDocuments',
            'legalDocumentLabels',
            'requiredLegalDocumentCodes',
            'organizerDocumentLabels',
            'organizerDocuments',
            'organizerDocumentCodes',
            'reusableDocuments'
        ));
    }

    public function store(OrganizerKycRequest $request, OrganizerKycService $service): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $service->apply($user, $request);

        return redirect()
            ->route('panel.organizer.apply')
            ->with('success', 'Kirim Pengajuan Penyelenggara berhasil! Berkas Anda sedang dalam proses peninjauan oleh tim admin.');
    }

    public function downloadDocument(int $document): StreamedResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $doc = $user->documents()
            ->where('id', $document)
            ->whereIn('category', OrganizerRequirements::categories())
            ->where('is_public', false)
            ->firstOrFail();

        if (! Storage::disk('local')->exists($doc->file_path)) {
            abort(404, 'Berkas tidak ditemukan.');
        }

        return Storage::disk('local')->download($doc->file_path, basename($doc->file_path));
    }
}
