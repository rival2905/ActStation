<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Contracts\CommunityLookup;
use App\Contracts\RosterProvider;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Exceptions\RegistrationGuardException;
use App\Modules\Event\Exceptions\RosterValidationException;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\EventParticipantMember;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\Event\Services\EventInvitationService;
use App\Modules\Event\Services\RegistrationDraftService;
use App\Modules\Event\Services\RegistrationGuard;
use App\Modules\Event\Services\RosterValidator;
use App\Modules\Event\Services\TeamPersonnelRoster;
use App\Modules\MasterData\Models\DocumentType;
use App\Modules\Payment\Models\Transaction;
use App\Services\EventRegistrationReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommunityEventRegistrationController extends Controller
{
    public function __construct(
        private readonly CommunityLookup $communityLookup,
        private readonly RosterProvider $rosterProvider,
        private readonly RegistrationGuard $registrationGuard,
        private readonly RosterValidator $rosterValidator,
        private readonly EventInvitationService $eventInvitationService,
        private readonly RegistrationDraftService $registrationDraftService,
    ) {}

    public function index(): View
    {
        $events = Event::query()
            ->where('status', 'published')
            ->where('registration_open', true)
            ->with(['categories'])
            ->latest()
            ->paginate(12);

        $community = $this->communityLookup->findForUser((int) Auth::id());
        request()->attributes->set('panel_active_community', $community);

        $myRegistrations = collect();
        if ($community) {
            $myRegistrations = EventParticipant::query()
                ->where('community_id', $community->id)
                ->with(['event', 'category'])
                ->latest()
                ->get();
        }

        return view('panel.events.index', [
            'events' => $events,
            'community' => $community,
            'activeCommunity' => $community,
            'myRegistrations' => $myRegistrations,
        ]);
    }

    public function create(string $slug): View|RedirectResponse
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with(['categories'])
            ->firstOrFail();

        $community = $this->communityLookup->findForUser((int) Auth::id());

        if (! $community) {
            $context = request()->validate(['event_category_id' => ['nullable', 'integer']]);
            app(EventRegistrationReturn::class)->remember(request(), (int) $event->id, isset($context['event_category_id']) ? (int) $context['event_category_id'] : null);

            return redirect()
                ->route('panel.community.create')
                ->with('info', 'Buat atau pilih komunitas terlebih dahulu, lalu lanjutkan pendaftaran event ini.');
        }

        // Ambil atlet yang eligible per kategori untuk form selection
        $eligibleRosterByCategory = [];
        $allUserIds = [];
        foreach ($event->categories as $category) {
            $athletes = $this->rosterProvider->eligibleAthletes(
                $community->id,
                $category->id
            );
            $eligibleRosterByCategory[$category->id] = $athletes;
            foreach ($athletes as $ath) {
                $allUserIds[] = $ath->userId;
            }
        }

        $allReferencedCodes = $event->categories->flatMap(fn ($c) => (array) ($c->required_documents ?? []))
            ->unique()
            ->filter()
            ->values()
            ->all();

        $documentTypes = DocumentType::query()
            ->where(function ($q) use ($allReferencedCodes): void {
                $q->where('is_active', true);
                if (! empty($allReferencedCodes)) {
                    $q->orWhereIn('code', $allReferencedCodes);
                }
            })
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        $vaultDocuments = Document::query()
            ->where('documentable_type', User::class)
            ->whereIn('documentable_id', array_unique($allUserIds))
            ->get()
            ->groupBy('documentable_id');

        $legalDocuments = $this->communityLookup->legalDocumentsForUser((int) Auth::id(), (int) $community->id);

        $registrations = EventParticipant::query()
            ->where('community_id', $community->id)
            ->where('event_id', $event->id)
            ->with(['category'])
            ->withCount('members')
            ->latest()
            ->get();

        $participantIds = $registrations->pluck('id')->filter()->all();
        $invoices = empty($participantIds) ? collect() : Transaction::query()
            ->where('payable_type', 'event_participant')
            ->whereIn('payable_id', $participantIds)
            ->where('type', 'registration')
            ->latest()
            ->get()
            ->unique('payable_id')
            ->keyBy('payable_id');

        foreach ($registrations as $reg) {
            $inv = $invoices->get($reg->id);
            $reg->setAttribute('currentinvoice', $inv);
            $reg->setAttribute('current_invoice', $inv);
            $reg->setRelation('currentInvoice', $inv);
        }

        $registrationDrafts = RegistrationDraft::query()
            ->where('user_id', Auth::id())
            ->where('community_id', $community->id)
            ->where('event_id', $event->id)
            ->whereNull('participant_id')
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('category')
            ->latest()
            ->get()
            ->reject(fn (RegistrationDraft $d) => $d->isSubmitted());

        $maxTeamsPerEntity = $event->categories->mapWithKeys(function (EventCategory $category) {
            return [$category->id => (int) ($category->getAttribute('max_teams_per_entity') ?? 1)];
        });
        foreach ($event->categories as $cat) {
            if ($cat->getAttribute('max_teams_per_entity') === null) {
                $cat->setAttribute('max_teams_per_entity', 1);
            }
        }

        $personnelOptions = app(TeamPersonnelRoster::class)->options($event, (int) $community->id);

        return view('panel.events.register', compact(
            'personnelOptions',
            'event',
            'community',
            'eligibleRosterByCategory',
            'documentTypes',
            'vaultDocuments',
            'legalDocuments',
            'registrations',
            'registrationDrafts',
            'maxTeamsPerEntity',
        ));
    }

    public function store(Request $request, string $slug): RedirectResponse|JsonResponse
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $community = $this->communityLookup->findForUser((int) Auth::id());
        if (! $community) {
            if ($request->expectsJson()) {
                abort(403, 'Akun Anda tidak terasosiasi dengan komunitas aktif.');
            }

            return back()->with('error', 'Akun Anda tidak terasosiasi dengan komunitas aktif.');
        }

        abort_if($request->filled('panel_community_id')
            && (string) $request->input('panel_community_id') !== (string) $community->id,
            422, 'Panel aktif telah berubah. Muat ulang halaman.');

        $draftId = $request->input('draft_id');
        $draft = null;
        if ($draftId !== null && $draftId !== '') {
            if (! is_string($draftId) || ! Str::isUuid($draftId)) {
                return $this->validationFail($request, 'draft_id', 'Format ID draft pendaftaran tidak valid.');
            }

            $draft = RegistrationDraft::find($draftId);
            if (! $draft) {
                return $this->validationFail($request, 'draft_id', 'Draft pendaftaran tidak ditemukan.');
            }

            if ((int) $draft->user_id !== (int) Auth::id()
                || (int) $draft->community_id !== (int) $community->id
                || (int) $draft->event_id !== (int) $event->id
            ) {
                abort(403, 'Akses draft pendaftaran ditolak.');
            }

            if ($draft->isExpired()) {
                if ($request->expectsJson()) {
                    abort(410, 'Draft pendaftaran sudah kedaluwarsa.');
                }

                return back()->withInput()->with('error', 'Draft pendaftaran sudah kedaluwarsa.');
            }

            if ($draft->isSubmitted()) {
                $existingParticipant = $draft->participant_id ? EventParticipant::find($draft->participant_id) : null;
                if ($existingParticipant) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => 'Pendaftaran tim sudah pernah diserahkan.',
                            'participant_id' => $existingParticipant->id,
                            'redirect_url' => route('panel.community-events.create', $event->slug),
                        ]);
                    }

                    return redirect()
                        ->route('panel.community-events.create', $event->slug)
                        ->with('info', "Pendaftaran tim '{$existingParticipant->team_name}' sudah pernah diserahkan.");
                }

                if ($request->expectsJson()) {
                    abort(409, 'Draft telah diserahkan dan tidak dapat diserahkan ulang.');
                }

                return redirect()
                    ->route('panel.community-events.create', $event->slug)
                    ->with('error', 'Draft telah diserahkan dan tidak dapat diserahkan ulang.');
            }

            if ($draft->event_category_id !== null && $request->filled('event_category_id')) {
                if ((int) $draft->event_category_id !== (int) $request->input('event_category_id')) {
                    return $this->validationFail($request, 'event_category_id', 'Kategori turnamen tidak sesuai dengan kategori draft pendaftaran.');
                }
            }

            $this->registrationDraftService->resolveStagedFiles($draft, $request);
        }

        $validated = $request->validate([
            'draft_id' => ['nullable', 'string'],
            'draft_revision' => [($draftId !== null && $draftId !== '') ? 'required' : 'nullable', 'integer'],
            'event_category_id' => ['required', 'integer', 'exists:event_categories,id'],
            'registration_source' => ['nullable', 'in:club,community'],
            'community_subtype' => ['nullable', 'in:school,university,institution,club,free_association'],
            'invitation_token' => ['nullable', 'string', 'size:64'],
            'team_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'personnel' => ['nullable', 'array', 'max:200'],
            'personnel.*' => ['array'],
            'personnel.*.user_id' => ['required', 'integer', 'min:1'],
            'personnel.*.role_id' => ['required', 'integer', 'min:1'],
            'members' => ['required', 'array', 'min:1'],
            'members.*.user_id' => ['required', 'integer'],
            'members.*.jersey_number' => ['required', 'integer', 'between:0,99'],
            'members.*.is_captain' => ['nullable', 'boolean'],
            'members.*.position' => ['nullable', 'string', 'max:50'],
            'members.*.team_role' => ['nullable', 'in:player'],
            'team_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'team_documents' => ['nullable', 'array'],
            'team_documents.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'athlete_documents' => ['nullable', 'array'],
            'athlete_documents.*.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'personnel_licenses' => ['nullable', 'array'],
            'personnel_licenses.*' => ['nullable', 'array'],
            'personnel_licenses.*.*' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:1024'],
        ]);
        /** @var EventCategory $category */
        $category = EventCategory::query()
            ->where('id', $validated['event_category_id'])
            ->where('event_id', $event->id)
            ->firstOrFail();

        $actualSubtype = (string) ($community->subtype ?? '');

        // Cek Kelengkapan Dokumen Sesuai Syarat Kategori (Digital Vault Validation)
        $requiredDocs = $category->required_documents ?? [];
        $applicableTeamRequiredDocs = [];
        $applicableAthleteRequiredDocs = [];
        $docTypeModels = collect();

        if (! empty($requiredDocs)) {
            $docTypeModels = DocumentType::query()
                ->whereIn('code', $requiredDocs)
                ->get()
                ->keyBy('code');

            foreach ($requiredDocs as $docCode) {
                if (! $docTypeModels->has($docCode)) {
                    return $this->validationFail($request, 'required_documents', "Jenis dokumen [{$docCode}] tidak valid atau tidak terdaftar di sistem.");
                }
            }

            foreach ($requiredDocs as $docCode) {
                $meta = $docTypeModels->get($docCode);
                if (! in_array($meta->scope, ['team', 'athlete'], true)) {
                    return $this->validationFail($request, 'required_documents', "Scope dokumen [{$docCode}] tidak valid.");
                }

                if (! empty($actualSubtype) && ! $meta->appliesToEntity($actualSubtype)) {
                    continue;
                }

                if ($meta->scope === 'team') {
                    $applicableTeamRequiredDocs[] = $docCode;
                } else {
                    $applicableAthleteRequiredDocs[] = $docCode;
                }
            }
        }

        // Validasi upload keys and scopes BEFORE storage
        $selectedUserIds = array_map(fn ($m) => (int) $m['user_id'], $validated['members']);
        $rawAthleteUploads = $request->all('athlete_documents')['athlete_documents'] ?? [];
        if (is_array($rawAthleteUploads)) {
            foreach ($rawAthleteUploads as $rawUid => $docs) {
                $uid = (int) $rawUid;
                if (! in_array($uid, $selectedUserIds, true)) {
                    return $this->validationFail($request, 'athlete_documents', "Unggahan dokumen atlet ditolak: ID atlet [{$rawUid}] tidak terdaftar dalam roster tim.");
                }

                if (is_array($docs)) {
                    foreach (array_keys($docs) as $docCode) {
                        if (! in_array($docCode, $applicableAthleteRequiredDocs, true)) {
                            return $this->validationFail($request, 'athlete_documents', "Unggahan dokumen atlet ditolak: jenis dokumen [{$docCode}] tidak berlaku atau bukan dokumen atlet yang diwajibkan.");
                        }
                    }
                }
            }
        }

        $rawTeamUploads = $request->all('team_documents')['team_documents'] ?? [];
        if (is_array($rawTeamUploads)) {
            foreach (array_keys($rawTeamUploads) as $docCode) {
                if (! in_array($docCode, $applicableTeamRequiredDocs, true)) {
                    return $this->validationFail($request, 'team_documents', "Unggahan dokumen tim ditolak: jenis dokumen [{$docCode}] tidak berlaku atau bukan dokumen tim yang diwajibkan.");
                }
            }
        }

        if ($request->hasFile('team_document')) {
            if (count($applicableTeamRequiredDocs) === 0) {
                return $this->validationFail($request, 'team_document', 'Unggahan dokumen tim ditolak: kategori ini tidak mewajibkan dokumen tim.');
            }
            if (count($applicableTeamRequiredDocs) > 1) {
                return $this->validationFail($request, 'team_documents', 'Unggahan dokumen tim ditolak: kategori mewajibkan beberapa dokumen tim secara terpisah.');
            }
        }

        // Validate presence of required team documents
        if (count($applicableTeamRequiredDocs) === 1) {
            $singleCode = $applicableTeamRequiredDocs[0];
            $hasSingle = $request->hasFile("team_documents.{$singleCode}") || $request->hasFile('team_document');
            if (! $hasSingle) {
                $docName = $docTypeModels->get($singleCode)?->name ?? $singleCode;

                return $this->validationFail($request, 'team_documents', "Dokumen tim [{$docName}] wajib dilampirkan.");
            }
        } elseif (count($applicableTeamRequiredDocs) > 1) {
            foreach ($applicableTeamRequiredDocs as $docCode) {
                if (! $request->hasFile("team_documents.{$docCode}")) {
                    $docName = $docTypeModels->get($docCode)?->name ?? $docCode;

                    return $this->validationFail($request, 'team_documents', "Dokumen tim [{$docName}] wajib dilampirkan.");
                }
            }
        }

        // Validate presence of required athlete documents (physical file check in vault)
        if (! empty($applicableAthleteRequiredDocs)) {
            $existingVaultDocs = Document::query()
                ->where('documentable_type', User::class)
                ->whereIn('documentable_id', $selectedUserIds)
                ->where('status', '!=', 'rejected')
                ->get()
                ->filter(function (Document $doc): bool {
                    return ! empty($doc->file_path)
                        && (Storage::disk('local')->exists($doc->file_path) || Storage::disk('public')->exists($doc->file_path));
                })
                ->groupBy('documentable_id');

            $uploadedFiles = $request->file('athlete_documents') ?? [];

            foreach ($applicableAthleteRequiredDocs as $docCode) {
                $docMeta = $docTypeModels->get($docCode);
                $docName = $docMeta?->name ?? $docCode;

                foreach ($validated['members'] as $idx => $m) {
                    $uid = (int) $m['user_id'];
                    $hasInVault = isset($existingVaultDocs[$uid]) && $existingVaultDocs[$uid]->contains('category', $docCode);
                    $hasInUpload = isset($uploadedFiles[$uid][$docCode]) && $uploadedFiles[$uid][$docCode] instanceof UploadedFile;

                    if (! $hasInVault && ! $hasInUpload) {
                        return $this->validationFail($request, 'athlete_documents', "Dokumen [{$docName}] untuk pemain #{$m['jersey_number']} belum tersedia di Digital Vault dan belum diunggah.");
                    }
                }
            }
        }

        // Cek Dokumen Legalitas Entitas (Entity Requirements)
        if (! empty($category->required_entity_documents)) {
            $legalDocs = $this->communityLookup->legalDocumentsForUser((int) Auth::id(), (int) $community->id);
            foreach ($category->required_entity_documents as $docCode) {
                if (! isset($legalDocs[$docCode])) {
                    if ($request->expectsJson()) {
                        throw ValidationException::withMessages(['documents' => "Dokumen legalitas entitas [{$docCode}] belum diverifikasi atau tidak tersedia."]);
                    }

                    return back()->withInput()->with('error', "Dokumen legalitas entitas [{$docCode}] belum diverifikasi atau tidak tersedia.");
                }
            }
        }

        $newlyStoredFiles = [];

        try {
            /** @var EventParticipant $participant */
            $participant = DB::transaction(function () use (
                $event,
                $category,
                $community,
                $validated,
                $request,
                $docTypeModels,
                $applicableTeamRequiredDocs,
                $draftId,
                &$newlyStoredFiles
            ): EventParticipant {
                $event = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
                /** @var EventCategory $lockedCategory */
                $lockedCategory = EventCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

                $lockedDraft = null;
                if ($draftId !== null) {
                    /** @var RegistrationDraft $lockedDraft */
                    $lockedDraft = RegistrationDraft::query()->where('id', $draftId)->lockForUpdate()->firstOrFail();
                    if ($lockedDraft->isExpired()) {
                        abort(410, 'Draft pendaftaran sudah kedaluwarsa.');
                    }
                    if ($lockedDraft->isSubmitted()) {
                        $existing = $lockedDraft->participant_id ? EventParticipant::find($lockedDraft->participant_id) : null;
                        if ($existing) {
                            return $existing;
                        }

                        abort(409, 'Draft telah diserahkan dan tidak dapat diserahkan ulang.');
                    }
                    if (! $request->filled('draft_revision')) {
                        abort(409, 'Konflik revisi: draft_revision wajib disertakan.');
                    }
                    if ((int) $request->input('draft_revision') !== (int) $lockedDraft->revision) {
                        abort(409, 'Konflik revisi: draft telah diperbarui oleh sesi lain.');
                    }
                    if ($lockedDraft->event_category_id !== null && (int) $lockedDraft->event_category_id !== (int) $lockedCategory->id) {
                        throw ValidationException::withMessages([
                            'event_category_id' => 'Kategori turnamen tidak sesuai dengan kategori draft pendaftaran.',
                        ]);
                    }
                }
                $invitation = null;
                if ($lockedCategory->registration_flow === 'by_invitation') {
                    $invitation = $this->eventInvitationService->authorize(
                        $lockedCategory,
                        (int) $community->id,
                        $validated['invitation_token'] ?? null,
                    );
                }

                $this->registrationGuard->validate(
                    event: $event,
                    category: $lockedCategory,
                    communityId: $community->id,
                    teamName: $validated['team_name'],
                    allowWaitingList: (bool) $lockedCategory->enable_waiting_list,
                    registrationSource: $validated['registration_source'] ?? null,
                    communitySubtype: $validated['community_subtype'] ?? null,
                    invitationAuthorized: $invitation !== null,
                    userId: (int) Auth::id(),
                );

                $activeTeams = $lockedCategory->participants()
                    ->where(function ($query): void {
                        $query->whereIn('status', ['registered', 'revision_needed', 'verified', 'confirmed'])
                            ->orWhere(function ($pending): void {
                                $pending->where('status', 'pending_payment')
                                    ->where(function ($deadline): void {
                                        $deadline->whereNull('payment_deadline_at')->orWhere('payment_deadline_at', '>=', now());
                                    });
                            });
                    })
                    ->count();
                $isWaitingList = $lockedCategory->registration_flow !== 'screening_first'
                    && $lockedCategory->max_teams !== null
                    && $activeTeams >= $lockedCategory->max_teams;
                $waitingListOrder = $isWaitingList
                    ? (int) $lockedCategory->participants()->where('status', 'waiting_list')->max('waiting_list_order') + 1
                    : null;
                $fee = (float) $lockedCategory->registration_fee;
                $invoiceOnRegistration = $fee > 0 && in_array($lockedCategory->registration_flow, ['payment_first', 'by_invitation'], true);
                $initialStatus = $isWaitingList ? 'waiting_list' : ($invoiceOnRegistration ? 'pending_payment' : 'registered');

                $participant = EventParticipant::create([
                    'event_id' => $event->id,
                    'event_category_id' => $lockedCategory->id,
                    'registrant_type' => 'community',
                    'personnel_requirements' => $event->personnel_requirements ?? [],
                    'community_id' => $community->id,
                    'user_id' => Auth::id(),
                    'team_name' => trim($validated['team_name']),
                    'contact_person' => $validated['contact_person'],
                    'contact_phone' => $validated['contact_phone'],
                    'notes' => $validated['notes'] ?? null,
                    'status' => $initialStatus,
                    'waiting_list_order' => $waitingListOrder,
                    'payment_deadline_at' => $invoiceOnRegistration ? $lockedCategory->payment_end : null,
                    'payment_channel' => 'gateway_escrow',
                    'registered_at' => now(),
                ]);

                $this->rosterValidator->validate($participant, $validated['members']);
                $licensePaths = [];
                $uploadedLicenses = $request->file('personnel_licenses') ?? [];
                foreach ($uploadedLicenses as $uId => $roles) {
                    if (is_array($roles)) {
                        foreach ($roles as $rId => $file) {
                            if ($file instanceof UploadedFile) {
                                $path = $file->store('participant_documents/personnel_licenses', 'local');
                                $newlyStoredFiles[] = $path;
                                $licensePaths["{$uId}:{$rId}"] = $path;
                            }
                        }
                    }
                }
                app(TeamPersonnelRoster::class)->sync($participant, $validated['personnel'] ?? [], $licensePaths);

                foreach ($validated['members'] as $member) {
                    EventParticipantMember::create([
                        'event_participant_id' => $participant->id,
                        'user_id' => (int) $member['user_id'],
                        'jersey_number' => (int) $member['jersey_number'],
                        'is_captain' => ! empty($member['is_captain']),
                        'position' => $member['position'] ?? null,
                        'team_role' => $member['team_role'] ?? 'player',
                    ]);
                }

                $uploadedFiles = $request->file('athlete_documents') ?? [];
                foreach ($uploadedFiles as $userId => $documents) {
                    foreach ($documents as $documentCode => $file) {
                        if ($file instanceof UploadedFile) {
                            $path = $file->store('vault_documents', 'local');
                            $newlyStoredFiles[] = $path;

                            $docName = $docTypeModels->get($documentCode)?->name ?? ucfirst(str_replace('_', ' ', (string) $documentCode));
                            $existingDoc = Document::query()
                                ->where('documentable_type', User::class)
                                ->where('documentable_id', (int) $userId)
                                ->where('category', (string) $documentCode)
                                ->first();

                            if ($existingDoc) {
                                $existingDoc->update([
                                    'name' => $docName,
                                    'file_path' => $path,
                                    'status' => 'pending',
                                    'verified_at' => null,
                                    'verified_by' => null,
                                    'is_public' => false,
                                ]);
                            } else {
                                Document::create([
                                    'documentable_type' => User::class,
                                    'documentable_id' => (int) $userId,
                                    'category' => (string) $documentCode,
                                    'name' => $docName,
                                    'file_path' => $path,
                                    'status' => 'pending',
                                    'verified_at' => null,
                                    'verified_by' => null,
                                    'is_public' => false,
                                ]);
                            }
                        }
                    }
                }

                if (count($applicableTeamRequiredDocs) === 1 && $request->hasFile('team_document') && ! $request->hasFile("team_documents.{$applicableTeamRequiredDocs[0]}")) {
                    $singleCode = $applicableTeamRequiredDocs[0];
                    $file = $request->file('team_document');
                    if ($file instanceof UploadedFile) {
                        $path = $file->store('participant_documents', 'local');
                        $newlyStoredFiles[] = $path;
                        $docName = $docTypeModels->get($singleCode)?->name ?? 'Surat Rekomendasi / Mandat Tim';
                        Document::create([
                            'documentable_type' => EventParticipant::class,
                            'documentable_id' => $participant->id,
                            'category' => $singleCode,
                            'name' => $docName,
                            'file_path' => $path,
                            'status' => 'pending',
                            'verified_at' => null,
                            'verified_by' => null,
                            'is_public' => false,
                        ]);
                    }
                }

                $uploadedTeamDocs = $request->file('team_documents') ?? [];
                foreach ($uploadedTeamDocs as $docCode => $file) {
                    if ($file instanceof UploadedFile && in_array($docCode, $applicableTeamRequiredDocs, true)) {
                        $path = $file->store('participant_documents', 'local');
                        $newlyStoredFiles[] = $path;
                        $docName = $docTypeModels->get($docCode)?->name ?? ucfirst(str_replace('_', ' ', (string) $docCode));
                        Document::create([
                            'documentable_type' => EventParticipant::class,
                            'documentable_id' => $participant->id,
                            'category' => (string) $docCode,
                            'name' => $docName,
                            'file_path' => $path,
                            'status' => 'pending',
                            'verified_at' => null,
                            'verified_by' => null,
                            'is_public' => false,
                        ]);
                    }
                }

                if ($invitation !== null) {
                    $this->eventInvitationService->redeem($invitation, $participant);
                }

                if ($invoiceOnRegistration && ! $isWaitingList) {
                    Transaction::create([
                        'user_id' => Auth::id(),
                        'invoice_number' => 'INV-REG-'.strtoupper(Str::random(8)),
                        'type' => 'registration',
                        'payable_type' => 'event_participant',
                        'payable_id' => $participant->id,
                        'amount' => $lockedCategory->registration_fee,
                        'fee' => 0.00,
                        'total' => $lockedCategory->registration_fee,
                        'status' => 'pending',
                        'expired_at' => $lockedCategory->payment_end,
                    ]);
                }

                if ($lockedDraft !== null) {
                    $tombstonePayload = (array) ($lockedDraft->payload ?? []);
                    $tombstonePayload['_tombstone'] = true;
                    $tombstonePayload['_submitted'] = true;
                    $tombstonePayload['_participant_id'] = $participant->id;
                    $lockedDraft->payload = $tombstonePayload;
                    $lockedDraft->files = ['team_documents' => [], 'athlete_documents' => [], 'personnel_licenses' => []];
                    $lockedDraft->participant_id = $participant->id;
                    $lockedDraft->save();
                }

                return $participant;
            });
        } catch (\Throwable $e) {
            foreach ($newlyStoredFiles as $path) {
                Storage::disk('local')->delete($path);
            }

            if ($e instanceof RegistrationGuardException) {
                if ($request->expectsJson()) {
                    throw ValidationException::withMessages(['registration' => $e->getMessage()]);
                }
                session()->flash('error', $e->getMessage());

                return back()->withInput()->with('error', $e->getMessage());
            }

            if ($e instanceof RosterValidationException) {
                if ($request->expectsJson()) {
                    throw ValidationException::withMessages($e->errors());
                }
                session()->flash('error', 'Validasi susunan roster gagal.');

                return back()->withInput()->withErrors($e->errors())->with('error', 'Validasi susunan roster gagal.');
            }

            if ($e instanceof ValidationException) {
                throw $e;
            }

            throw $e;
        }

        if ($draft !== null) {
            $this->registrationDraftService->cleanStagedFiles($draft);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $participant->wasRecentlyCreated ? "Pendaftaran tim '{$participant->team_name}' berhasil dikirim." : 'Pendaftaran tim sudah pernah diserahkan.',
                'participant_id' => $participant->id,
                'status' => $participant->status,
                'redirect_url' => route('panel.community-events.create', $event->slug),
            ], $participant->wasRecentlyCreated ? 201 : 200);
        }

        if ($participant->wasRecentlyCreated === false) {
            return redirect()
                ->route('panel.community-events.create', $event->slug)
                ->with('info', "Pendaftaran tim '{$participant->team_name}' sudah pernah diserahkan.");
        }

        if ($draftId !== null || $request->input('redirect_to') === 'summary') {
            return redirect()
                ->route('panel.community-events.create', $event->slug)
                ->with('success', "Pendaftaran tim '{$participant->team_name}' berhasil disimpan.");
        }

        $initialStatus = (string) $participant->status;
        $waitingListOrder = $participant->waiting_list_order;

        if ($initialStatus === 'pending_payment') {
            return redirect()
                ->route('panel.community-events.checkout', $participant->id)
                ->with('success', 'Pendaftaran berhasil dibuat! Silakan selesaikan pembayaran untuk mengunci slot turnamen.');
        }

        if ($initialStatus === 'waiting_list') {
            return redirect()
                ->route('panel.community-events.roster', $participant->id)
                ->with('warning', "Kuota penuh. Tim Anda masuk ke antrean Waiting List nomor urut #{$waitingListOrder}.");
        }

        return redirect()
            ->route('panel.community-events.roster', $participant->id)
            ->with('success', 'Pendaftaran tim berhasil diserahkan ke panitia untuk kurasi berkas.');
    }

    private function validationFail(Request $request, string $field, string $message): RedirectResponse
    {
        if ($request->expectsJson()) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return back()->withInput()->withErrors([$field => $message])->with('error', $message);
    }
}
