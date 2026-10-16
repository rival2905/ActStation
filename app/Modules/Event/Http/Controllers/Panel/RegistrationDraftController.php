<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Contracts\CommunityLookup;
use App\Contracts\ContextualAthleteInvitation;
use App\Contracts\RosterProvider;
use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\Panel\DraftAthleteInvitationRequest;
use App\Modules\Event\Http\Requests\Panel\RegistrationDraftRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Services\AthleteEventWorkspace;
use App\Modules\Event\Services\DraftPersonnelInvitations;
use App\Modules\Event\Services\RegistrationDraftService;
use App\Modules\Event\Services\TeamPersonnelRoster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegistrationDraftController extends Controller
{
    public function __construct(
        private readonly CommunityLookup $communityLookup,
        private readonly RegistrationDraftService $draftService,
        private readonly ContextualAthleteInvitation $contextualInvitation,
        private readonly RosterProvider $rosterProvider,
        private readonly AthleteEventWorkspace $athleteWorkspace,
    ) {}

    public function candidates(Request $request, string $slug, string $draft): JsonResponse
    {
        $event = Event::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();
        $community = $this->communityLookup->findForUser((int) Auth::id());
        $draftModel = $this->draftService->authorizeDraft($draft, Auth::user(), $community, $event);
        abort_if($draftModel->isSubmitted() || $draftModel->event_category_id === null, 409, 'Konteks draft tidak lagi aktif.');

        return response()->json([
            'personnel_options' => app(TeamPersonnelRoster::class)->options($event, (int) $draftModel->community_id),
            'personnel' => $draftModel->payload['personnel'] ?? [],
            'revision' => $draftModel->revision,
            'candidates' => $this->rosterProvider->candidatesForDraft(
                (string) $draftModel->id,
                (int) $draftModel->community_id,
            )->map(fn ($candidate): array => array_replace($candidate->jsonSerialize(), [
                'meets_membership_requirements' => $candidate->meetsMembershipRequirements && $draftModel->category->acceptsAthleteGender($candidate->gender),
                'reason' => $draftModel->category->acceptsAthleteGender($candidate->gender) ? $candidate->reason : 'Jenis kelamin atlet tidak sesuai dengan kategori.',
                'documents' => $this->athleteWorkspace->getRequiredAthleteDocumentsForDraft($draftModel, $candidate->userId),
            ]))->values(),
        ]);
    }

    public function storePersonnelInvitation(DraftAthleteInvitationRequest $request, string $slug, string $draft): JsonResponse
    {
        $event = Event::where('slug', $slug)->where('status', 'published')->firstOrFail();
        $community = $this->communityLookup->findForUser((int) Auth::id());
        $draftModel = $this->draftService->authorizeDraft($draft, Auth::user(), $community, $event);
        $url = app(DraftPersonnelInvitations::class)->create($draftModel, (int) $request->validated('revision'));

        return response()->json(['invite_url' => $url, 'draft_id' => $draftModel->id, 'event_category_id' => $draftModel->event_category_id], 201);
    }

    public function storeAthleteInvitation(
        DraftAthleteInvitationRequest $request,
        string $slug,
        string $draft,
    ): JsonResponse {
        $event = Event::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();
        $community = $this->communityLookup->findForUser((int) Auth::id());
        $draftModel = $this->draftService->authorizeDraft($draft, Auth::user(), $community, $event);

        abort_if($draftModel->isSubmitted(), 409, 'Draft telah diserahkan.');
        abort_if($draftModel->event_category_id === null, 422, 'Kategori draft wajib dipilih.');
        abort_if((int) $request->validated('revision') !== (int) $draftModel->revision, 409, 'Konflik revisi draft.');

        $context = $this->draftService->validInvitationContext(
            (string) $draftModel->id,
            (int) $draftModel->community_id,
            (int) $draftModel->event_category_id,
        );
        abort_if($context === null, 409, 'Konteks draft tidak lagi aktif.');

        $result = $this->contextualInvitation->createForDraft(
            $context->communityId,
            $context->draftId,
            $context->eventCategoryId,
            $context->maxRoster,
            Auth::user(),
        );

        return response()->json([
            'invite_url' => route('invitations.public.show', $result['plain_token']),
            'draft_id' => $context->draftId,
            'event_category_id' => $context->eventCategoryId,
        ], 201);
    }

    public function store(RegistrationDraftRequest $request, string $slug): JsonResponse
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $community = $this->communityLookup->findForUser((int) Auth::id());
        if (! $community) {
            return response()->json(['message' => 'Anda harus terdaftar sebagai pengurus aktif komunitas.'], 403);
        }

        abort_if($request->filled('panel_community_id')
            && (string) $request->input('panel_community_id') !== (string) $community->id,
            422, 'Panel aktif telah berubah. Muat ulang halaman.');

        $draft = $this->draftService->createDraft(
            Auth::user(),
            (int) $community->id,
            $event,
            $request->validated(),
            $request,
        );

        return response()->json([
            'message' => 'Draft pendaftaran berhasil disimpan.',
            'draft' => [
                'id' => $draft->id,
                'event_id' => $draft->event_id,
                'event_category_id' => $draft->event_category_id,
                'revision' => $draft->revision,
                'expires_at' => $draft->expires_at?->toIso8601String(),
                'payload' => $draft->payload,
                'files' => $this->draftService->sanitizeFilesForResponse($draft->files),
            ],
        ], 201);
    }

    public function show(Request $request, string $slug, string $draft): JsonResponse
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $community = $this->communityLookup->findForUser((int) Auth::id());
        $draftModel = $this->draftService->authorizeDraft($draft, Auth::user(), $community, $event);

        $payload = (array) ($draftModel->payload ?? []);
        $sanitizedPayload = array_filter(
            $payload,
            fn ($key) => ! str_starts_with((string) $key, '_'),
            ARRAY_FILTER_USE_KEY
        );

        return response()->json([
            'draft' => [
                'id' => $draftModel->id,
                'event_id' => $draftModel->event_id,
                'event_category_id' => $draftModel->event_category_id,
                'revision' => $draftModel->revision,
                'expires_at' => $draftModel->expires_at?->toIso8601String(),
                'participant_id' => $draftModel->participant_id ?? ($payload['_participant_id'] ?? null),
                'is_submitted' => $draftModel->isSubmitted(),
                'payload' => $sanitizedPayload,
                'files' => $this->draftService->sanitizeFilesForResponse($draftModel->files),
            ],
        ]);
    }

    public function update(RegistrationDraftRequest $request, string $slug, string $draft): JsonResponse
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $community = $this->communityLookup->findForUser((int) Auth::id());
        $draftModel = $this->draftService->authorizeDraft($draft, Auth::user(), $community, $event);

        $ifMatch = $request->header('If-Match');
        $clientRevision = $ifMatch !== null && $ifMatch !== ''
            ? (int) trim($ifMatch, '"')
            : ($request->filled('revision') ? (int) $request->input('revision') : null);

        if ($clientRevision === null) {
            return response()->json(['message' => 'Header If-Match atau field revision wajib disertakan untuk update draft.'], 409);
        }

        $updatedDraft = $this->draftService->updateDraft(
            $draftModel,
            $request->validated(),
            $clientRevision,
            $request,
        );

        return response()->json([
            'message' => 'Draft pendaftaran berhasil diperbarui.',
            'draft' => [
                'id' => $updatedDraft->id,
                'event_id' => $updatedDraft->event_id,
                'event_category_id' => $updatedDraft->event_category_id,
                'revision' => $updatedDraft->revision,
                'expires_at' => $updatedDraft->expires_at?->toIso8601String(),
                'payload' => $updatedDraft->payload,
                'files' => $this->draftService->sanitizeFilesForResponse($updatedDraft->files),
            ],
        ]);
    }

    public function destroy(Request $request, string $slug, string $draft): JsonResponse
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $community = $this->communityLookup->findForUser((int) Auth::id());
        $draftModel = $this->draftService->authorizeDraft($draft, Auth::user(), $community, $event);

        if ($draftModel->isSubmitted()) {
            return response()->json(['message' => 'Draft telah diserahkan dan tidak dapat dihapus.'], 409);
        }

        $this->draftService->deleteDraft($draftModel);

        return response()->json(['message' => 'Draft pendaftaran berhasil dihapus.']);
    }
}
