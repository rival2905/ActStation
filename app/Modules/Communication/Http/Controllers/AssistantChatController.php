<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Http\Requests\AssistantArchiveRequest;
use App\Modules\Communication\Http\Requests\AssistantMessageRequest;
use App\Modules\Communication\Http\Resources\AssistantArchiveResource;
use App\Modules\Communication\Http\Resources\AssistantResource;
use App\Modules\Communication\Services\AssistantChatService;
use App\Modules\Communication\Services\AssistantHistoryArchive;
use Illuminate\Http\Request;

class AssistantChatController extends Controller
{
    public function archive(AssistantArchiveRequest $request, AssistantHistoryArchive $archive): AssistantArchiveResource
    {
        return new AssistantArchiveResource($archive->search(
            $request->user(), $request->validated('q') ?? '', (int) ($request->validated('page') ?? 1)
        ));
    }

    public function send(AssistantMessageRequest $request, AssistantChatService $chat): AssistantResource
    {
        return new AssistantResource($chat->send($request->user(), $request->validated('message')));
    }

    public function clear(Request $request, AssistantChatService $chat): AssistantResource
    {
        return new AssistantResource($chat->clear($request->user()));
    }

    public function history(Request $request, AssistantChatService $chat): AssistantResource
    {
        return new AssistantResource($chat->history($request->user()));
    }
}
