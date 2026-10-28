<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\SaveEventQuestionRequest;
use App\Modules\Event\Http\Requests\SubmitEventAnswersRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventAnswer;
use App\Modules\Event\Models\EventQuestion;
use App\Modules\Event\Services\EventQuestionService;
use Illuminate\Http\Request;

class EventQuestionController extends Controller
{
    public function __construct(private readonly EventQuestionService $questions) {}

    public function index(Request $request, Event $event)
    {
        return view('events.questions.index', ['event' => $event, 'questions' => $this->questions->definitions($event, $request->user())]);
    }

    public function store(SaveEventQuestionRequest $request, Event $event)
    {
        $this->questions->save($event, $request->user(), $request->validated());

        return back()->with('success', 'Pertanyaan disimpan.');
    }

    public function update(SaveEventQuestionRequest $request, Event $event, EventQuestion $question)
    {
        $this->questions->save($event, $request->user(), $request->validated(), $question);

        return back()->with('success', 'Pertanyaan diperbarui.');
    }

    public function edit(Request $request, Event $event, string $target, int $subjectId)
    {
        return view('events.questions.edit', $this->questions->form($event, $request->user(), $target, $subjectId));
    }

    public function submit(SubmitEventAnswersRequest $request, Event $event, string $target, int $subjectId)
    {
        $this->questions->submit($event, $request->user(), $target, $subjectId, $request->validated('answers', []));

        return back()->with('success', 'Jawaban disimpan. Minat bukan penunjukan tampil.');
    }

    public function report(Request $request, Event $event)
    {
        return view('events.questions.report', ['event' => $event, 'responses' => $this->questions->report($event, $request->user())]);
    }

    public function download(Request $request, Event $event, EventAnswer $answer, string $questionId)
    {
        return $this->questions->download($event, $request->user(), $answer, $questionId);
    }
}
