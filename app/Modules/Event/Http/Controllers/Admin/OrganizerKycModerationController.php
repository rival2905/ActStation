<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Admin;

use App\Contracts\CommunityLookup;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Event\Http\Requests\Admin\RejectOrganizerKycRequest;
use App\Modules\Event\Services\OrganizerKycService;
use App\Modules\Event\Services\OrganizerRequirements;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizerKycModerationController extends Controller
{
    public function __construct(
        private readonly CommunityLookup $communityLookup,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');
        $allowlist = ['all', 'pending', 'verified', 'rejected', 'unverified'];

        if (! in_array($status, $allowlist, true)) {
            $status = 'pending';
        }

        $query = User::query();

        if ($status !== 'all') {
            $query->where('kyc_status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('identity_number', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $applicants = $query->latest('kyc_submitted_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'pending' => User::where('kyc_status', 'pending')->count(),
            'verified' => User::where('kyc_status', 'verified')->count(),
            'rejected' => User::where('kyc_status', 'rejected')->count(),
            'unverified' => User::where('kyc_status', 'unverified')->count(),
            'all' => User::count(),
        ];

        return view('admin.organizers.kyc.index', compact('applicants', 'status', 'counts'));
    }

    public function show(User $user, OrganizerKycService $service): View
    {
        $documents = $user->documents()
            ->whereIn('category', OrganizerRequirements::categories())
            ->get()
            ->keyBy(fn ($doc) => Str::after((string) $doc->category, 'organizer_'));

        $community = null;
        $communityDocuments = [];

        if ($user->organizer_community_id) {
            $community = $this->communityLookup->find((int) $user->organizer_community_id);

            if ($community) {
                $communityDocuments = $this->communityLookup->legalDocumentsForUser($user->id, (int) $user->organizer_community_id);
            }
        }

        $approvalIssue = null;
        if ($user->kyc_status === 'pending') {
            try {
                $service->assertApprovalRequirements($user);
            } catch (DomainException $e) {
                $approvalIssue = $e->getMessage();
            }
        }

        $documentLabels = OrganizerRequirements::DOCUMENT_LABELS;

        return view('admin.organizers.kyc.show', compact('user', 'documents', 'community', 'communityDocuments', 'documentLabels', 'approvalIssue'));
    }

    public function approve(Request $request, User $user, OrganizerKycService $service): RedirectResponse
    {
        /** @var User $reviewer */
        $reviewer = Auth::user();

        if ($reviewer->id === $user->id) {
            abort(403, 'Anda tidak dapat menyetujui pengajuan KYC diri sendiri.');
        }

        try {
            $service->approve($reviewer, $user);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.organizer-kyc.show', $user)
            ->with('success', 'Pengajuan verifikasi penyelenggara berhasil disetujui.');
    }

    public function reject(RejectOrganizerKycRequest $request, User $user, OrganizerKycService $service): RedirectResponse
    {
        /** @var User $reviewer */
        $reviewer = Auth::user();

        if ($reviewer->id === $user->id) {
            abort(403, 'Anda tidak dapat menolak pengajuan KYC diri sendiri.');
        }

        $notes = (string) $request->validated('notes');

        try {
            $service->reject($reviewer, $user, $notes);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.organizer-kyc.show', $user)
            ->with('success', 'Pengajuan verifikasi penyelenggara telah ditolak dengan catatan.');
    }

    public function downloadDocument(User $user, int $document): StreamedResponse
    {
        $doc = $user->documents()
            ->where('id', $document)
            ->whereIn('category', OrganizerRequirements::categories())
            ->where('is_public', false)
            ->firstOrFail();

        if (! Storage::disk('local')->exists($doc->file_path)) {
            abort(404, 'Berkas dokumen tidak ditemukan di penyimpanan server.');
        }

        return Storage::disk('local')->download($doc->file_path, basename($doc->file_path));
    }

    public function downloadEntityDocument(User $user, string $code): StreamedResponse
    {
        if (! $user->organizer_community_id) {
            abort(404, 'Pemohon tidak memiliki entitas komunitas terdaftar.');
        }

        return $this->communityLookup->legalDocumentDownloadForUser(
            $user->id,
            (int) $user->organizer_community_id,
            $code
        );
    }
}
