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
use App\Modules\Event\Services\EventInvitationService;
use App\Modules\Event\Services\RegistrationGuard;
use App\Modules\Event\Services\RosterValidator;
use App\Modules\MasterData\Models\DocumentType;
use App\Modules\Payment\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CommunityEventRegistrationController extends Controller
{
    public function __construct(
        private readonly CommunityLookup $communityLookup,
        private readonly RosterProvider $rosterProvider,
        private readonly RegistrationGuard $registrationGuard,
        private readonly RosterValidator $rosterValidator,
        private readonly EventInvitationService $eventInvitationService,
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

        $myRegistrations = collect();
        if ($community) {
            $myRegistrations = EventParticipant::query()
                ->where('community_id', $community->id)
                ->with(['event', 'category'])
                ->latest()
                ->get();
        }

        return view('panel.events.index', compact('events', 'community', 'myRegistrations'));
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
            return redirect()
                ->route('panel.community-events.index')
                ->with('error', 'Anda harus terdaftar sebagai pengurus aktif komunitas untuk mendaftarkan tim turnamen.');
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

        $documentTypes = DocumentType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        $vaultDocuments = Document::query()
            ->where('documentable_type', User::class)
            ->whereIn('documentable_id', array_unique($allUserIds))
            ->get()
            ->groupBy('documentable_id');

        return view('panel.events.register', compact('event', 'community', 'eligibleRosterByCategory', 'documentTypes', 'vaultDocuments'));
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $community = $this->communityLookup->findForUser((int) Auth::id());
        if (! $community) {
            return back()->with('error', 'Akun Anda tidak terasosiasi dengan komunitas aktif.');
        }

        $validated = $request->validate([
            'event_category_id' => ['required', 'integer', 'exists:event_categories,id'],
            'registration_source' => ['nullable', 'in:club,community'],
            'community_subtype' => ['nullable', 'in:school,university,institution,club,free_association'],
            'invitation_token' => ['nullable', 'string', 'size:64'],
            'team_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'members' => ['required', 'array', 'min:1'],
            'members.*.user_id' => ['required', 'integer'],
            'members.*.jersey_number' => ['required', 'integer', 'between:0,99'],
            'members.*.is_captain' => ['nullable', 'boolean'],
            'members.*.position' => ['nullable', 'string', 'max:50'],
            'members.*.team_role' => ['nullable', 'string', 'max:50'],
            'team_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'athlete_documents' => ['nullable', 'array'],
            'athlete_documents.*.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        /** @var EventCategory $category */
        $category = EventCategory::query()
            ->where('id', $validated['event_category_id'])
            ->where('event_id', $event->id)
            ->firstOrFail();

        // Cek Kelengkapan Dokumen Sesuai Syarat Kategori (Digital Vault Validation)
        $requiredDocs = $category->required_documents ?? [];
        if (! empty($requiredDocs)) {
            $docTypeModels = DocumentType::query()
                ->whereIn('code', $requiredDocs)
                ->get()
                ->keyBy('code');

            $selectedUserIds = array_column($validated['members'], 'user_id');
            $existingVaultDocs = Document::query()
                ->where('documentable_type', User::class)
                ->whereIn('documentable_id', $selectedUserIds)
                ->get()
                ->groupBy('documentable_id');

            $uploadedFiles = $request->file('athlete_documents') ?? [];

            foreach ($requiredDocs as $docCode) {
                $docMeta = $docTypeModels->get($docCode);
                $docName = $docMeta?->name ?? $docCode;

                if ($docMeta?->scope === 'team') {
                    if (! $request->hasFile('team_document')) {
                        return back()->withInput()->with('error', "Dokumen tim [{$docName}] wajib dilampirkan.");
                    }
                } else {
                    foreach ($validated['members'] as $idx => $m) {
                        $uid = (int) $m['user_id'];
                        $hasInVault = isset($existingVaultDocs[$uid]) && $existingVaultDocs[$uid]->contains('category', $docCode);
                        $hasInUpload = isset($uploadedFiles[$uid][$docCode]);

                        if (! $hasInVault && ! $hasInUpload) {
                            return back()->withInput()->with('error', "Dokumen [{$docName}] untuk pemain #{$m['jersey_number']} belum tersedia di Digital Vault dan belum diunggah.");
                        }
                    }
                }
            }
        }

        // Status akhir ditentukan di transaksi setelah kuota kategori dikunci.

        try {
            /** @var EventParticipant $participant */
            $participant = DB::transaction(function () use ($event, $category, $community, $validated, $request): EventParticipant {
                Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
                /** @var EventCategory $lockedCategory */
                $lockedCategory = EventCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
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
                        if ($file) {
                            $path = $file->store('vault_documents', 'public');
                            Document::updateOrCreate(
                                ['documentable_type' => User::class, 'documentable_id' => (int) $userId, 'category' => $documentCode],
                                ['name' => ucfirst(str_replace('_', ' ', $documentCode)), 'file_path' => $path, 'status' => 'pending']
                            );
                        }
                    }
                }

                if ($request->hasFile('team_document')) {
                    Document::create([
                        'documentable_type' => EventParticipant::class,
                        'documentable_id' => $participant->id,
                        'category' => 'recommendation_letter',
                        'name' => 'Surat Rekomendasi / Mandat Tim',
                        'file_path' => $request->file('team_document')->store('participant_documents', 'public'),
                        'status' => 'pending',
                    ]);
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

                return $participant;
            });
        } catch (RegistrationGuardException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (RosterValidationException $e) {
            return back()->withInput()->withErrors($e->errors())->with('error', 'Validasi susunan roster gagal.');
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
}
