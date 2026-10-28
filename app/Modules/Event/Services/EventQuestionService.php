<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\CommunityLookup;
use App\Models\User;
use App\Modules\Event\Http\Requests\SaveEventQuestionRequest;
use App\Modules\Event\Models\ActivityMember;
use App\Modules\Event\Models\ActivityRegistration;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventAnswer;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\EventQuestion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventQuestionService
{
    public function definitions(Event $event, User $user): array
    {
        abort_unless(app(EventCommitteeAccess::class)->owner($event, $user), 403);

        return EventQuestion::where('event_id', $event->id)->orderBy('order')->orderBy('id')->get()->toArray();
    }

    public function save(Event $event, User $user, array $data, ?EventQuestion $question = null): EventQuestion
    {
        abort_unless(app(EventCommitteeAccess::class)->owner($event, $user), 403);
        abort_if($question && (int) $question->event_id !== (int) $event->id, 404);
        $data += ['order' => 0, 'is_required' => false];
        $valid = Validator::make($data, (new SaveEventQuestionRequest)->rules())->validate();

        return DB::transaction(function () use ($event, $question, $valid): EventQuestion {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($question && EventAnswer::where('event_id', $event->id)->exists()) {
                throw ValidationException::withMessages(['question' => 'Definisi terkunci setelah jawaban tersimpan. Buat pertanyaan baru.']);
            }
            if ($valid['type'] === 'select' && empty($valid['options'])) {
                throw ValidationException::withMessages(['options' => 'Pilihan jawaban diperlukan.']);
            }
            if (! empty($valid['depends_on_question_id'])) {
                $parent = EventQuestion::where('event_id', $event->id)->whereKey($valid['depends_on_question_id'])->first();
                if (! $parent || $parent->target !== $valid['target'] || $parent->type !== 'select'
                    || $parent->order >= $valid['order'] || (int) $parent->id === (int) $question?->id
                    || ! in_array($valid['conditional_value'] ?? null, $parent->options ?? [], true)) {
                    throw ValidationException::withMessages(['depends_on_question_id' => 'Kondisi harus memilih jawaban pertanyaan pilihan sebelumnya pada sasaran yang sama.']);
                }
            }
            if ($question && EventQuestion::where('depends_on_question_id', $question->id)->exists()) {
                throw ValidationException::withMessages(['question' => 'Pertanyaan induk yang sudah dipakai kondisi tidak dapat diubah.']);
            }
            $valid['event_id'] = $event->id;
            if ($question) {
                $question->update($valid);

                return $question->fresh();
            }

            return EventQuestion::create($valid);
        });
    }

    public function form(Event $event, User $user, string $target, int $subjectId): array
    {
        $subject = $this->subject($event, $user, $target, $subjectId);
        $questions = $this->questions($event, $target, $subject);
        $answer = EventAnswer::where('event_id', $event->id)->where('target', $target)->where('subject_id', $subjectId)->first();

        return ['event' => $event, 'target' => $target, 'subject_id' => $subjectId, 'subject_summary' => $subject,
            'questions' => $questions->toArray(), 'answers' => $answer?->answers ?? [], 'answer' => $answer];
    }

    public function submit(Event $event, User $user, string $target, int $subjectId, array $answers): void
    {
        $created = [];
        try {
            DB::transaction(function () use ($event, $user, $target, $subjectId, $answers, &$created): void {
                Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
                $subject = $this->subject($event, $user, $target, $subjectId);
                $questions = $this->questions($event, $target, $subject);
                $record = EventAnswer::where('event_id', $event->id)->where('target', $target)->where('subject_id', $subjectId)->lockForUpdate()->first();
                $previous = $record?->answers ?? [];
                $clean = $this->cleanAnswers($event, $questions, $answers, $previous, true, $created);
                EventAnswer::updateOrCreate(['event_id' => $event->id, 'target' => $target, 'subject_id' => $subjectId],
                    ['answers' => $clean, 'submitted_by' => $user->id, 'submitted_at' => now()]);
                // ponytail: prior private files retained for rollback safety; add retention cleanup when policy is defined.
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($created);
            throw $exception;
        }
    }

    public function cleanAnswers(Event $event, Collection $questions, array $answers, array $previous, bool $complete, array &$created): array
    {
        $known = $questions->pluck('id')->map(fn ($id) => (string) $id)->all();
        if (array_diff(array_map('strval', array_keys($answers)), $known)) {
            throw ValidationException::withMessages(['answers' => 'Jawaban tidak sesuai pertanyaan pada sasaran ini.']);
        }
        $clean = [];
        foreach ($questions as $question) {
            $id = (string) $question->id;
            $parent = (string) $question->depends_on_question_id;
            if ($question->depends_on_question_id && ($clean[$parent] ?? null) !== $question->conditional_value) {
                continue;
            }
            $value = $answers[$id] ?? null;
            if ($question->type === 'file') {
                if ($value === null && isset($previous[$id]['path']) && str_starts_with($previous[$id]['path'], 'event_answers/'.$event->id.'/') && ! str_contains($previous[$id]['path'], '..') && Storage::disk('local')->exists($previous[$id]['path'])) {
                    $clean[$id] = $previous[$id];

                    continue;
                }
                Validator::make(['answers' => [$id => $value]], ['answers.'.$id => [$complete && $question->is_required ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048']])->validate();
                if ($value instanceof UploadedFile) {
                    $path = $value->store('event_answers/'.$event->id, 'local');
                    if (! $path) {
                        throw new \RuntimeException('Penyimpanan berkas gagal.');
                    }
                    $created[] = $path;
                    $clean[$id] = ['path' => $path, 'name' => basename($value->getClientOriginalName()), 'mime' => $value->getMimeType()];
                }

                continue;
            }
            $rules = [$complete && $question->is_required ? 'required' : 'nullable'];
            $rules = array_merge($rules, $question->type === 'number' ? ['integer', 'min:0', 'max:1000000'] : ['string', 'max:5000']);
            if ($question->type === 'select') {
                $rules[] = Rule::in($question->options ?? []);
            }
            Validator::make(['answers' => [$id => $value]], ['answers.'.$id => $rules])->validate();
            if ($value !== null && $value !== '') {
                $clean[$id] = (string) $value;
            }
        }

        return $clean;
    }

    public function assertAnswersComplete(Event $event, Collection $questions, array $answers): void
    {
        $values = $answers;
        foreach ($questions as $question) {
            if ($question->type === 'file') {
                unset($values[$question->id]);
            }
        }
        $created = [];
        $this->cleanAnswers($event, $questions, $values, $answers, true, $created);
    }

    public function assertActivityComplete(Event $event, ActivityRegistration $registration): void
    {
        $community = $registration->community_id ? app(CommunityLookup::class)->find((int) $registration->community_id) : null;
        $context = ['school_level' => $community?->school_level, 'institution_type' => $community?->institution_type, 'subtype' => $community?->subtype];
        $subjects = $registration->members()->get()->map(fn ($member) => ['participant', (int) $member->id])->all();
        if ($registration->community_id) {
            $subjects[] = ['institution', (int) $registration->community_id];
        }
        if ($registration->parent_participant_id) {
            $subjects[] = ['team', (int) $registration->parent_participant_id];
        }
        foreach ($subjects as [$target, $id]) {
            $answers = EventAnswer::where('event_id', $event->id)->where('target', $target)->where('subject_id', $id)->first()?->answers ?? [];
            $clean = [];
            foreach ($this->questions($event, $target, $context) as $question) {
                if ($question->depends_on_question_id && ($clean[$question->depends_on_question_id] ?? null) !== $question->conditional_value) {
                    continue;
                }
                $value = $answers[$question->id] ?? null;
                if ($question->type === 'file') {
                    $available = is_array($value) && isset($value['path']) && str_starts_with($value['path'], 'event_answers/'.$event->id.'/')
                        && ! str_contains($value['path'], '..') && Storage::disk('local')->exists($value['path']);
                    if ($question->is_required && ! $available) {
                        throw ValidationException::withMessages(['answers' => 'Lengkapi berkas wajib: '.$question->label]);
                    }
                } else {
                    $rules = [$question->is_required ? 'required' : 'nullable'];
                    $rules = array_merge($rules, $question->type === 'number' ? ['integer', 'min:0', 'max:1000000'] : ['string', 'max:5000']);
                    if ($question->type === 'select') {
                        $rules[] = Rule::in($question->options ?? []);
                    }
                    Validator::make(['answers' => $value], ['answers' => $rules])->validate();
                }
                $clean[$question->id] = $value;
            }
        }
    }

    public function report(Event $event, User $user): array
    {
        abort_unless(app(EventCommitteeAccess::class)->allows($event, $user, 'teams.view'), 403);

        return EventAnswer::where('event_id', $event->id)->latest('id')->get()->map(fn ($answer) => [
            'answer_id' => $answer->id, 'subject_id' => $answer->subject_id, 'target' => $answer->target,
            'subject_name' => ucfirst($answer->target).' #'.$answer->subject_id,
            'answers' => $answer->answers, 'submitted_at' => $answer->submitted_at?->toIso8601String(),
        ])->all();
    }

    public function download(Event $event, User $user, EventAnswer $answer, string $questionId): StreamedResponse
    {
        abort_unless((int) $answer->event_id === (int) $event->id, 404);
        if (! app(EventCommitteeAccess::class)->allows($event, $user, 'teams.view')) {
            $this->subject($event, $user, $answer->target, (int) $answer->subject_id);
        }
        $file = $answer->answers[$questionId] ?? null;
        abort_unless(is_array($file) && isset($file['path']) && str_starts_with($file['path'], 'event_answers/'.$event->id.'/')
            && ! str_contains($file['path'], '..') && Storage::disk('local')->exists($file['path']), 404);

        return Storage::disk('local')->download($file['path'], $file['name'], ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function subject(Event $event, User $user, string $target, int $id): array
    {
        abort_unless($user->is_active, 403);
        $communityId = null;
        $name = '#'.$id;
        if ($target === 'institution') {
            $communityId = $id;
            abort_unless(app(CommunityLookup::class)->canManage((int) $user->id, $id), 403);
        } elseif ($target === 'team') {
            $team = EventParticipant::where('event_id', $event->id)->findOrFail($id);
            $communityId = $team->community_id;
            abort_unless($communityId ? app(CommunityLookup::class)->canManage((int) $user->id, (int) $communityId) : (int) $team->user_id === (int) $user->id, 403);
            $name = $team->team_name;
        } elseif ($target === 'participant') {
            $member = ActivityMember::with('registration.activity')->findOrFail($id);
            abort_unless((int) $member->registration?->activity?->event_id === (int) $event->id && (int) $member->user_id === (int) $user->id, 403);
            $communityId = $member->registration->community_id;
            $name = $user->name;
        } else {
            abort(404);
        }
        $community = $communityId ? app(CommunityLookup::class)->find((int) $communityId) : null;
        if ($target === 'institution') {
            abort_unless($community && $community->is_active, 403);
            $name = $community->name;
        }

        return ['name' => $name, 'school_level' => $community?->school_level, 'institution_type' => $community?->institution_type, 'subtype' => $community?->subtype];
    }

    public function questions(Event $event, string $target, array $subject): Collection
    {
        return EventQuestion::where('event_id', $event->id)->where('target', $target)->orderBy('order')->orderBy('id')->get()->filter(function ($question) use ($subject): bool {
            return (! $question->school_levels || in_array(strtolower((string) $subject['school_level']), array_map('strtolower', $question->school_levels), true))
                && (! $question->institution_types || in_array($subject['institution_type'], $question->institution_types, true) || in_array($subject['subtype'], $question->institution_types, true));
        })->values();
    }
}
