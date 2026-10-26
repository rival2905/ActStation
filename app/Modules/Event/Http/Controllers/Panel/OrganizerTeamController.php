<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Contracts\CommunityLookup;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Exceptions\RosterValidationException;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\Event\Services\EventCommitteeAccess;
use App\Modules\Event\Services\RosterValidator;
use App\Modules\Event\Services\TeamPersonnelRoster;
use App\Modules\MasterData\Models\DocumentType;
use App\Modules\Payment\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizerTeamController extends Controller
{
    public function __construct(
        private readonly RosterValidator $rosterValidator,
        private readonly CommunityLookup $communityLookup,
    ) {}

    public function index(Request $request, Event $event): View
    {
        $this->authorizeOrganizer($event);

        $query = EventParticipant::query()
            ->where('event_id', $event->id)
            ->with(['category', 'members.user', 'paymentVerifiedBy']);

        if ($request->filled('category_id')) {
            $query->where('event_category_id', $request->input('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $participants = $query->latest('registered_at')->paginate(20)->withQueryString();
        $categories = $event->categories;
        $workspace = $request->routeIs('admin.*') ? 'admin' : 'organizer';

        $paidParticipantIds = DB::table('transactions')
            ->where('payable_type', 'event_participant')
            ->where('status', 'paid')
            ->whereIn('payable_id', $participants->pluck('id'))
            ->pluck('payable_id')
            ->all();

        $allReferencedCodes = $categories->flatMap(fn ($c) => (array) ($c->required_documents ?? []))
            ->unique()
            ->filter()
            ->values()
            ->all();

        $participantDocumentTypes = DocumentType::query()
            ->where(function ($q) use ($allReferencedCodes): void {
                $q->where('is_active', true);
                if (! empty($allReferencedCodes)) {
                    $q->orWhereIn('code', $allReferencedCodes);
                }
            })
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        $currentParticipantIds = $participants->pluck('id')->all();
        $allMemberUserIds = $participants->flatMap(fn ($p) => $p->members->pluck('user_id'))
            ->unique()
            ->map(fn ($id): int => (int) $id)
            ->all();

        $allPageDocs = collect();
        if (! empty($currentParticipantIds) || ! empty($allMemberUserIds)) {
            $allPageDocs = Document::query()
                ->where(function ($q) use ($currentParticipantIds, $allMemberUserIds): void {
                    if (! empty($currentParticipantIds)) {
                        $q->where(function ($teamQ) use ($currentParticipantIds): void {
                            $teamQ->where('documentable_type', EventParticipant::class)
                                ->whereIn('documentable_id', $currentParticipantIds);
                        });
                    }
                    if (! empty($allMemberUserIds)) {
                        $q->orWhere(function ($athQ) use ($allMemberUserIds): void {
                            $athQ->where('documentable_type', User::class)
                                ->whereIn('documentable_id', $allMemberUserIds);
                        });
                    }
                })
                ->get();
        }

        $participantDocuments = [];

        foreach ($participants as $participant) {
            $actualSubtype = '';
            if ($participant->community_id) {
                $communitySummary = $this->communityLookup->find((int) $participant->community_id);
                $actualSubtype = (string) ($communitySummary?->subtype ?? '');
            }

            $reqDocs = $participant->category?->required_documents ?? [];
            $applicableTeamCodes = [];
            $applicableAthleteCodes = [];

            foreach ($reqDocs as $docCode) {
                $dt = $participantDocumentTypes->get($docCode);
                if ($dt && (empty($actualSubtype) || $dt->appliesToEntity($actualSubtype))) {
                    if ($dt->scope === 'team') {
                        $applicableTeamCodes[] = $docCode;
                    } elseif ($dt->scope === 'athlete') {
                        $applicableAthleteCodes[] = $docCode;
                    }
                }
            }

            $pMemberIds = $participant->members->pluck('user_id')->map(fn ($id): int => (int) $id)->all();

            $pDocs = $allPageDocs->filter(function ($doc) use ($participant, $applicableTeamCodes, $applicableAthleteCodes, $pMemberIds): bool {
                if ($doc->documentable_type === EventParticipant::class && (int) $doc->documentable_id === (int) $participant->id) {
                    return in_array($doc->category, $applicableTeamCodes, true);
                }
                if ($doc->documentable_type === User::class && in_array((int) $doc->documentable_id, $pMemberIds, true)) {
                    return in_array($doc->category, $applicableAthleteCodes, true);
                }

                return false;
            })->values();

            $participantDocuments[$participant->id] = $pDocs;
        }

        $activeTab = (string) $request->input('tab', 'registered');
        if (! in_array($activeTab, ['registered', 'drafts'], true)) {
            $activeTab = 'registered';
        }

        $draftsQuery = RegistrationDraft::query()
            ->where('event_id', $event->id)
            ->whereNull('participant_id')
            ->with(['user', 'category'])
            ->latest('updated_at');

        if ($request->filled('category_id')) {
            $draftsQuery->where('event_category_id', $request->input('category_id'));
        }

        if ($request->input('status') === 'draft_expired') {
            $draftsQuery->where('expires_at', '<=', now());
        } elseif ($request->input('status') === 'draft_active') {
            $draftsQuery->unexpired();
        }

        $drafts = $draftsQuery->paginate(20, ['*'], 'drafts_page')->withQueryString();

        $draftCommunityIds = $drafts->pluck('community_id')->filter()->unique()->all();
        $draftCommunities = [];
        foreach ($draftCommunityIds as $cid) {
            $draftCommunities[$cid] = $this->communityLookup->find((int) $cid);
        }

        return view('panel.organizer.teams.index', compact(
            'event',
            'participants',
            'categories',
            'workspace',
            'paidParticipantIds',
            'participantDocuments',
            'participantDocumentTypes',
            'drafts',
            'draftCommunities',
            'activeTab',
        ));
    }

    public function downloadDocument(Event $event, EventParticipant $participant, int $document): StreamedResponse
    {
        $this->authorizeOrganizer($event);
        $this->authorizeParticipant($event, $participant);

        $participant->loadMissing(['category', 'members']);
        $category = $participant->category;
        abort_unless($category, 404, 'Kategori tidak ditemukan.');

        /** @var Document|null $doc */
        $doc = Document::query()->find($document);
        abort_unless($doc, 404, 'Berkas tidak ditemukan.');

        // Private files only
        abort_if((bool) $doc->is_public, 403, 'Akses ditolak: bukan berkas privat.');

        // Scope check: must belong to participant or roster member
        $memberIds = $participant->members->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
        $isTeamDoc = $doc->documentable_type === EventParticipant::class && (int) $doc->documentable_id === (int) $participant->id;
        $isAthleteDoc = $doc->documentable_type === User::class && in_array((int) $doc->documentable_id, $memberIds, true);

        abort_unless($isTeamDoc || $isAthleteDoc, 403, 'Akses ditolak: berkas tidak terikat dengan tim ini.');

        // Scope doc code: must be a required applicable document for this category and entity
        $requiredDocuments = $category->required_documents ?? [];
        abort_unless(in_array($doc->category, $requiredDocuments, true), 403, 'Akses ditolak: jenis dokumen tidak termasuk persyaratan kategori.');

        $docType = DocumentType::query()->where('code', $doc->category)->first();
        abort_unless($docType, 404, 'Jenis dokumen tidak ditemukan.');

        $actualSubtype = '';
        if ($participant->community_id) {
            $communitySummary = $this->communityLookup->find((int) $participant->community_id);
            $actualSubtype = (string) ($communitySummary?->subtype ?? '');
        }

        if (! empty($actualSubtype)) {
            abort_unless($docType->appliesToEntity($actualSubtype), 403, 'Akses ditolak: dokumen tidak berlaku untuk entitas ini.');
        }

        if ($isTeamDoc) {
            abort_unless($docType->scope === 'team', 403, 'Akses ditolak: scope dokumen tidak sesuai tim.');
        } else {
            abort_unless($docType->scope === 'athlete', 403, 'Akses ditolak: scope dokumen tidak sesuai atlet.');
        }

        $filePath = (string) $doc->file_path;
        abort_if(empty($filePath), 404, 'Berkas fisik tidak ditemukan.');

        $name = $doc->name ?? $doc->category;

        if (Storage::disk('local')->exists($filePath)) {
            return Storage::disk('local')->download($filePath, $name);
        }

        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->download($filePath, $name);
        }

        abort(404, 'Berkas fisik tidak ditemukan.');
    }

    public function verify(Event $event, EventParticipant $participant): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        $this->authorizeParticipant($event, $participant);

        return DB::transaction(function () use ($event, $participant): RedirectResponse {
            $event = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            EventCategory::query()->whereKey($participant->event_category_id)->lockForUpdate()->firstOrFail();
            $participant = EventParticipant::query()->whereKey($participant->id)->lockForUpdate()->firstOrFail();
            $participant->loadMissing(['category', 'members']);

            $category = $participant->category;
            abort_unless($category, 422, 'Kategori peserta tidak ditemukan.');

            if (! in_array($participant->status, ['registered', 'revision_needed', 'verified'], true)
                && ! ($category->registration_flow === 'screening_first' && $participant->status === 'waiting_list')) {
                return back()->with('error', 'Status pendaftaran ini tidak dapat diverifikasi.');
            }
            $hasFee = (float) $category->registration_fee > 0;
            $flow = (string) $category->registration_flow;

            if ($participant->members->isEmpty()) {
                return back()->with('error', 'Roster peserta belum tersedia dan tidak dapat diverifikasi.');
            }

            try {
                $this->rosterValidator->validate($participant, $participant->members->map(fn ($member): array => [
                    'user_id' => (int) $member->user_id,
                    'jersey_number' => $member->jersey_number,
                    'is_captain' => (bool) $member->is_captain,
                    'position' => $member->position,
                    'team_role' => $member->team_role,
                ])->all());
            } catch (RosterValidationException $exception) {
                return back()->withErrors($exception->errors())->with('error', 'Roster tidak lolos validasi kategori.');
            }

            $personnelEvent = clone $event;
            $personnelEvent->personnel_requirements = $participant->personnel_requirements ?? [];
            app(TeamPersonnelRoster::class)->validate($personnelEvent, (int) $participant->community_id, $participant->personnel()->get(['user_id', 'role_id'])->toArray());

            $requiredDocuments = $category->required_documents ?? [];
            if (! empty($requiredDocuments)) {
                $documentTypes = DocumentType::query()->whereIn('code', $requiredDocuments)->get()->keyBy('code');

                foreach ($requiredDocuments as $docCode) {
                    if (! $documentTypes->has($docCode)) {
                        return back()->with('error', "Jenis dokumen [{$docCode}] tidak valid atau tidak terdaftar di sistem.");
                    }
                }

                $actualSubtype = '';
                if ($participant->community_id) {
                    $communitySummary = $this->communityLookup->find((int) $participant->community_id);
                    $actualSubtype = (string) ($communitySummary?->subtype ?? '');
                }

                $applicableRequiredDocs = [];
                foreach ($requiredDocuments as $docCode) {
                    $docMeta = $documentTypes->get($docCode);
                    if (! in_array($docMeta->scope, ['team', 'athlete'], true)) {
                        return back()->with('error', "Scope dokumen [{$docCode}] tidak valid.");
                    }

                    if (empty($actualSubtype) || $docMeta->appliesToEntity($actualSubtype)) {
                        $applicableRequiredDocs[] = $docCode;
                    }
                }

                if (! empty($applicableRequiredDocs)) {
                    $memberIds = $participant->members->pluck('user_id')->map(fn ($id): int => (int) $id)->all();

                    $athleteDocuments = Document::query()
                        ->where('documentable_type', User::class)
                        ->whereIn('documentable_id', $memberIds)
                        ->whereIn('category', $applicableRequiredDocs)
                        ->where('status', 'verified')
                        ->get()
                        ->filter(fn (Document $d) => ! empty($d->file_path) && (Storage::disk('local')->exists($d->file_path) || Storage::disk('public')->exists($d->file_path)));

                    $teamDocuments = Document::query()
                        ->where('documentable_type', EventParticipant::class)
                        ->where('documentable_id', $participant->id)
                        ->whereIn('category', $applicableRequiredDocs)
                        ->where('status', 'verified')
                        ->get()
                        ->filter(fn (Document $d) => ! empty($d->file_path) && (Storage::disk('local')->exists($d->file_path) || Storage::disk('public')->exists($d->file_path)));

                    foreach ($applicableRequiredDocs as $documentCode) {
                        $docMeta = $documentTypes->get($documentCode);
                        if ($docMeta->scope === 'team') {
                            if (! $teamDocuments->contains('category', $documentCode)) {
                                return back()->with('error', "Dokumen tim [{$documentCode}] belum diverifikasi atau berkas fisik tidak ditemukan.");
                            }

                            continue;
                        }

                        if ($docMeta->scope === 'athlete') {
                            $coveredMembers = $athleteDocuments->where('category', $documentCode)->pluck('documentable_id')->map(fn ($id): int => (int) $id);
                            if ($coveredMembers->unique()->count() !== count(array_unique($memberIds))) {
                                return back()->with('error', "Dokumen atlet [{$documentCode}] belum diverifikasi untuk seluruh roster.");
                            }

                            continue;
                        }
                    }
                }
            }

            if ($flow === 'screening_first') {
                $categoryFull = $category->max_teams !== null && $category->participants()->occupyingSlot()->whereKeyNot($participant->id)->count() >= $category->max_teams;
                $eventFull = $event->max_participants !== null && $event->participants()->occupyingSlot()->whereKeyNot($participant->id)->count() >= $event->max_participants;
                if ($categoryFull || $eventFull) {
                    if ($category->enable_waiting_list) {
                        $participant->update(['status' => 'waiting_list', 'waiting_list_order' => (int) $category->participants()->where('status', 'waiting_list')->max('waiting_list_order') + 1]);

                        return back()->with('success', 'Berkas lolos verifikasi, tetapi kuota penuh. Tim masuk daftar tunggu tanpa tagihan.');
                    }

                    return back()->with('error', 'Kuota peserta lolos verifikasi sudah penuh. Pengajuan belum mengambil slot.');
                }
            }

            if ($flow === 'screening_first' && $hasFee && in_array($participant->status, ['registered', 'revision_needed', 'waiting_list'], true)) {
                $hours = (int) ($category->payment_time_limit_hours ?? 24);
                $deadline = now()->addHours($hours);
                if ($event->starts_at !== null && $deadline->greaterThan($event->starts_at)) {
                    $deadline = $event->starts_at->copy();
                }

                if ($deadline->lessThanOrEqualTo(now())) {
                    return back()->with('error', 'Tenggat pembayaran sudah melewati waktu mulai event.');
                }

                DB::transaction(function () use ($participant, $category, $deadline): void {
                    $participant->update([
                        'status' => 'pending_payment',
                        'payment_deadline_at' => $deadline,
                        'waiting_list_order' => null,
                        'revision_notes' => null,
                    ]);

                    Transaction::firstOrCreate(
                        ['payable_type' => 'event_participant', 'payable_id' => $participant->id],
                        [
                            'user_id' => $participant->user_id,
                            'invoice_number' => 'INV-REG-'.strtoupper(Str::random(8)),
                            'type' => 'registration',
                            'amount' => $category->registration_fee,
                            'fee' => 0.00,
                            'total' => $category->registration_fee,
                            'status' => 'pending',
                            'expired_at' => $deadline,
                        ]
                    );
                });

                return back()->with('success', "Berkas tim [{$participant->team_name}] lolos verifikasi. Invoice berlaku {$hours} jam dan tidak melewati waktu mulai event.");
            }

            if ($hasFee) {
                $paid = Transaction::query()
                    ->where('payable_type', 'event_participant')
                    ->where('payable_id', $participant->id)
                    ->where('status', 'paid')
                    ->exists();
                if (! $paid) {
                    return back()->with('error', 'Pendaftaran berbayar belum dapat dikonfirmasi sebelum pembayaran lunas.');
                }
            }

            $participant->update(['status' => 'confirmed', 'revision_notes' => null, 'waiting_list_order' => null]);

            return back()->with('success', "Tim [{$participant->team_name}] berhasil diverifikasi dan roster resmi terkunci (Roster Lock).");
        });
    }

    public function requestRevision(Request $request, Event $event, EventParticipant $participant): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        $this->authorizeParticipant($event, $participant);

        if (in_array($participant->status, ['confirmed', 'cancelled', 'rejected'], true)) {
            return back()->with('error', 'Status pendaftaran saat ini tidak dapat diminta revisi.');
        }

        $validated = $request->validate([
            'revision_notes' => ['required', 'string', 'max:1000'],
        ]);

        $participant->update([
            'status' => 'revision_needed',
            'revision_notes' => $validated['revision_notes'],
        ]);

        return back()->with('success', "Catatan perbaikan berhasil dikirim ke manajer tim [{$participant->team_name}].");
    }

    public function confirmManualPayment(Event $event, EventParticipant $participant): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        $this->authorizeParticipant($event, $participant);
        $participant->loadMissing(['category', 'members']);

        if ($participant->payment_channel !== 'manual_organizer' || ! $participant->payment_proof || $participant->status !== 'pending_payment') {
            return back()->with('error', 'Bukti pembayaran manual yang aktif wajib tersedia sebelum konfirmasi.');
        }

        $transaction = Transaction::query()
            ->where('payable_type', 'event_participant')
            ->where('payable_id', $participant->id)
            ->where('status', 'pending')
            ->first();
        if (! $transaction || ($transaction->expired_at !== null && now()->greaterThan($transaction->expired_at))) {
            return back()->with('error', 'Invoice pembayaran tidak aktif atau sudah kedaluwarsa.');
        }

        $paidAt = now();
        $flow = (string) $participant->category?->registration_flow;
        if (in_array($flow, ['payment_first', 'by_invitation'], true)
            && ($participant->category?->payment_start === null || $participant->category?->payment_end === null
                || $paidAt->lessThan($participant->category->payment_start) || $paidAt->greaterThan($participant->category->payment_end))) {
            return back()->with('error', 'Pembayaran manual berada di luar jendela pembayaran kategori.');
        }

        DB::transaction(function () use ($participant, $transaction, $paidAt): void {
            $transaction->update(['status' => 'paid', 'paid_at' => $paidAt]);
            $participant->update([
                'status' => $participant->category?->registration_flow === 'screening_first' ? 'confirmed' : 'registered',
                'payment_verified_at' => $paidAt,
                'payment_verified_by' => Auth::id(),
                'revision_notes' => null,
            ]);
        });

        return back()->with('success', "Pembayaran manual tim [{$participant->team_name}] telah diverifikasi lunas.");
    }

    public function destroy(Event $event, EventParticipant $participant): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        $this->authorizeParticipant($event, $participant);

        if (request()->routeIs('admin.*') && ! Auth::user()?->can('event-participant.delete') && ! Auth::user()?->hasRole('superadmin')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki izin untuk menghapus tim peserta.');
        }

        // 1. Cek pembayaran: HANYA yang belum bayar yang boleh dihapus
        $hasPaidTransaction = DB::table('transactions')
            ->where('payable_type', 'event_participant')
            ->where('payable_id', $participant->id)
            ->where('status', 'paid')
            ->exists();

        if ($hasPaidTransaction || $participant->payment_verified_at !== null) {
            return back()->with('error', "Tim [{$participant->team_name}] tidak dapat dihapus karena sudah memiliki pembayaran yang lunas (status paid). Lakukan proses refund atau pembatalan resmi terlebih dahulu.");
        }

        // 2. Cek apakah tim sudah terdaftar dalam jadwal pertandingan atau pool turnamen
        $inPool = $participant->poolTeams()->exists();
        $inGames = $participant->homeGames()->exists() || $participant->awayGames()->exists();
        if ($inPool || $inGames) {
            return back()->with('error', "Tim [{$participant->team_name}] tidak dapat dihapus karena sudah terdaftar dalam jadwal pertandingan atau pool turnamen.");
        }

        // 3. Cek status kompetisi
        if ($participant->status === 'attended') {
            return back()->with('error', "Tim [{$participant->team_name}] tidak dapat dihapus karena sudah berstatus hadir di kompetisi.");
        }

        $teamName = $participant->team_name;
        $category = $participant->category;

        DB::transaction(function () use ($participant, $category): void {
            // Hapus invoice/transaksi pending & expired yang belum terbayar
            DB::table('transactions')
                ->where('payable_type', 'event_participant')
                ->where('payable_id', $participant->id)
                ->whereIn('status', ['pending', 'expired'])
                ->delete();

            // Hapus dokumen tim terkait jika ada
            Document::query()
                ->where('documentable_type', EventParticipant::class)
                ->where('documentable_id', $participant->id)
                ->delete();

            // Reset token undangan terikat jika ada agar tidak tertahan
            DB::table('event_category_invitations')
                ->where('event_participant_id', $participant->id)
                ->update(['event_participant_id' => null, 'used_at' => null]);

            // Hapus tim peserta (anggota roster cascade otomatis via DB FK)
            $participant->delete();

            // Jika kategori memiliki waiting list dan kuota tim terbuka, promosikan antrean #1
            if ($category && $category->registration_flow !== 'screening_first' && $category->enable_waiting_list && $category->max_teams !== null) {
                $activeCount = $category->participants()
                    ->whereIn('status', ['registered', 'revision_needed', 'verified', 'pending_payment', 'confirmed'])
                    ->count();

                if ($activeCount < $category->max_teams) {
                    $nextWaiting = $category->participants()
                        ->where('status', 'waiting_list')
                        ->orderBy('waiting_list_order')
                        ->first();

                    if ($nextWaiting) {
                        $fee = (float) $category->registration_fee;
                        $flow = (string) $category->registration_flow;
                        $invoiceOnAdvance = $fee > 0 && in_array($flow, ['payment_first', 'by_invitation'], true);

                        $nextWaiting->update([
                            'status' => $invoiceOnAdvance ? 'pending_payment' : 'registered',
                            'waiting_list_order' => null,
                            'payment_deadline_at' => $invoiceOnAdvance ? $category->payment_end : null,
                        ]);

                        if ($invoiceOnAdvance) {
                            Transaction::firstOrCreate(
                                ['payable_type' => 'event_participant', 'payable_id' => $nextWaiting->id],
                                [
                                    'user_id' => $nextWaiting->user_id,
                                    'invoice_number' => 'INV-REG-'.strtoupper(Str::random(8)),
                                    'type' => 'registration',
                                    'amount' => $category->registration_fee,
                                    'fee' => 0.00,
                                    'total' => $category->registration_fee,
                                    'status' => 'pending',
                                    'expired_at' => $category->payment_end,
                                ]
                            );
                        }
                    }
                }
            }
        });

        return back()->with('success', "Tim [{$teamName}] berhasil dihapus dari daftar peserta.");
    }

    private function authorizeOrganizer(Event $event): void
    {
        if (! request()->routeIs('admin.*')) {
            app(EventCommitteeAccess::class)->current($event);
        }
    }

    private function authorizeParticipant(Event $event, EventParticipant $participant): void
    {
        abort_unless((int) $participant->event_id === (int) $event->id, 404);
    }
}
