<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\CommunityLookup;
use App\Contracts\DTOs\CommunitySummary;
use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventActivity;
use App\Modules\Event\Models\EventAnswer;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\RegistrationDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RegistrationAdditionalInformationService
{
    public function __construct(private readonly EventQuestionService $questions) {}

    private function context(CommunitySummary $community): array
    {
        return ['school_level' => $community->school_level, 'institution_type' => $community->institution_type, 'subtype' => $community->subtype];
    }

    private function institution(Event $event, CommunitySummary $community): ?EventAnswer
    {
        return EventAnswer::where('event_id', $event->id)->where('target', 'institution')->where('subject_id', $community->id)->first();
    }

    private function version(?EventAnswer $answer): string
    {
        return hash('sha256', (string) $answer?->getRawOriginal('answers'));
    }

    private function activities(Event $event, CommunitySummary $community)
    {
        return EventActivity::where('event_id', $event->id)->get()->filter(fn ($activity) => $activity->acceptsInstitution($community))->values();
    }

    public function read(Event $event, CommunitySummary $community, RegistrationDraft $draft): array
    {
        $institution = $this->institution($event, $community);
        $safe = fn (array $answers) => array_map(fn ($answer) => is_array($answer) ? array_intersect_key($answer, array_flip(['name', 'mime'])) : $answer, $answers);
        $context = $this->context($community);
        $institutionQuestions = $this->questions->questions($event, 'institution', $context);
        $teamQuestions = $this->questions->questions($event, 'team', $context);
        $activities = $this->activities($event, $community);
        $documents = $event->documents()->where('is_public', true)->get()->map(fn ($document) => ['name' => $document->name, 'url' => route('events.documents.download', [$event->slug, $document->id])])->all();

        return ['draft' => ['id' => $draft->id, 'revision' => $draft->revision], 'additional' => [
            'enabled' => $institutionQuestions->isNotEmpty() || $teamQuestions->isNotEmpty() || $activities->isNotEmpty() || count($documents) > 0,
            'institution' => ['questions' => $institutionQuestions->toArray(), 'answers' => $safe($institution?->answers ?? [])],
            'team' => ['questions' => $teamQuestions->toArray(), 'answers' => $safe($draft->payload['additional_team_answers'] ?? [])],
            'institution_version' => $this->version($institution),
            'activities' => $activities->map(fn ($activity) => $activity->only(['id', 'name', 'description', 'fee_mode', 'fee', 'included_slots', 'min_members', 'max_members', 'is_open']))->all(),
            'selected_activity_ids' => array_values(array_intersect($draft->payload['additional_activity_ids'] ?? [], $activities->modelKeys())),
            'documents' => $documents,
        ]];
    }

    public function save(Event $event, User $user, CommunitySummary $community, RegistrationDraft $draft, array $data): array
    {
        $created = [];
        try {
            return DB::transaction(function () use ($event, $user, $community, $draft, $data, &$created): array {
                $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
                $draft = RegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
                abort_unless($user->is_active && $community->is_active && app(CommunityLookup::class)->canManage((int) $user->id, $community->id)
                    && (int) $draft->user_id === (int) $user->id && (int) $draft->community_id === $community->id && (int) $draft->event_id === (int) $event->id, 403);
                abort_if($draft->isExpired(), 410);
                abort_if($draft->isSubmitted() || (int) ($data['revision'] ?? -1) !== $draft->revision, 409, 'Konflik revisi draft. Muat ulang sebelum menyimpan.');
                abort_unless($event->status === 'published', 409);
                $institution = $this->institution($event, $community);
                $context = $this->context($community);
                $institutionQuestions = $this->questions->questions($event, 'institution', $context);
                if ($institutionQuestions->isNotEmpty()) {
                    abort_unless(hash_equals($this->version($institution), (string) ($data['institution_version'] ?? '')), 409, 'Jawaban instansi telah berubah. Muat ulang sebelum menyimpan.');
                }
                $complete = (bool) ($data['complete'] ?? false);
                $institutionAnswers = $this->questions->cleanAnswers($event, $institutionQuestions, $data['institution_answers'] ?? [], $institution?->answers ?? [], $complete, $created);
                $teamAnswers = $this->questions->cleanAnswers($event, $this->questions->questions($event, 'team', $context), $data['team_answers'] ?? [], $draft->payload['additional_team_answers'] ?? [], $complete, $created);
                $ids = array_values(array_unique(array_map('intval', array_filter($data['selected_activity_ids'] ?? [], fn ($id) => $id !== null && $id !== ''))));
                if (array_diff($ids, $this->activities($event, $community)->where('is_open', true)->modelKeys())) {
                    throw ValidationException::withMessages(['selected_activity_ids' => 'Kegiatan tidak tersedia untuk instansi ini.']);
                }
                if ($institutionQuestions->isNotEmpty() && $institutionAnswers !== ($institution?->answers ?? [])) {
                    EventAnswer::updateOrCreate(['event_id' => $event->id, 'target' => 'institution', 'subject_id' => $community->id], ['answers' => $institutionAnswers, 'submitted_by' => $user->id, 'submitted_at' => now()]);
                }
                $draft->update(['payload' => array_replace($draft->payload ?? [], ['additional_team_answers' => $teamAnswers, 'additional_activity_ids' => $ids]), 'revision' => $draft->revision + 1]);

                return $this->read($event, $community, $draft->fresh());
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($created);
            throw $exception;
        }
    }

    public function assertComplete(Event $event, CommunitySummary $community, ?RegistrationDraft $draft): void
    {
        $context = $this->context($community);
        $this->questions->assertAnswersComplete($event, $this->questions->questions($event, 'institution', $context), $this->institution($event, $community)?->answers ?? []);
        $this->questions->assertAnswersComplete($event, $this->questions->questions($event, 'team', $context), $draft?->payload['additional_team_answers'] ?? []);
    }

    public function finalize(Event $event, User $user, ?RegistrationDraft $draft, EventParticipant $participant): void
    {
        $answers = $draft?->payload['additional_team_answers'] ?? [];
        if ($answers !== []) {
            EventAnswer::updateOrCreate(['event_id' => $event->id, 'target' => 'team', 'subject_id' => $participant->id], ['answers' => $answers, 'submitted_by' => $user->id, 'submitted_at' => now()]);
        }
    }
}
