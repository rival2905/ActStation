<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Models\User;
use App\Modules\Communication\Models\AssistantExchange;
use App\Modules\Communication\Models\AssistantThread;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AssistantHistoryArchive
{
    public function record(User $user, array $previousMessages, string $question, array $answer): void
    {
        abort_unless($user->is_active && $user->can('assistant.use'), 403);
        DB::transaction(function () use ($user, $previousMessages, $question, $answer): void {
            if (! AssistantExchange::where('user_id', $user->id)->exists()) {
                for ($i = 0; $i + 1 < count($previousMessages); $i++) {
                    if (($previousMessages[$i]['role'] ?? null) === 'user' && ($previousMessages[$i + 1]['role'] ?? null) === 'assistant') {
                        $this->store($user, $previousMessages[$i]['content'], $previousMessages[++$i]);
                    }
                }
            }
            $this->store($user, $question, $answer);
        });
    }

    private function store(User $user, string $question, array $answer): void
    {
        AssistantExchange::create(['user_id' => $user->id, 'payload' => [
            'question' => $question, 'answer' => $answer['content'] ?? '', 'sources' => $answer['sources'] ?? [],
        ]]);
    }

    public function search(User $user, string $query = '', int $page = 1): array
    {
        abort_unless($user->is_active && $user->can('assistant.use'), 403);
        if (! AssistantExchange::where('user_id', $user->id)->exists()) {
            $lock = Cache::lock('assistant:'.$user->id, 65);
            if ($lock->get()) {
                try {
                    DB::transaction(function () use ($user): void {
                        if (AssistantExchange::where('user_id', $user->id)->exists()) {
                            return;
                        }
                        $previous = AssistantThread::where('user_id', $user->id)->first()?->messages ?? [];
                        for ($i = 0; $i + 1 < count($previous); $i++) {
                            if (($previous[$i]['role'] ?? null) === 'user' && ($previous[$i + 1]['role'] ?? null) === 'assistant') {
                                $this->store($user, $previous[$i]['content'], $previous[++$i]);
                            }
                        }
                    });
                } finally {
                    $lock->release();
                }
            }
        }
        $page = max(1, $page);
        $rows = AssistantExchange::where('user_id', $user->id)->latest('id');
        $query = trim($query);
        if ($query === '') {
            $items = $rows->offset(($page - 1) * 20)->limit(21)->get();
        } else {
            // ponytail: decrypt only latest 500 exchanges; add blind indexing when archives need wider search.
            $words = preg_split('/\s+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY);
            $items = $rows->limit(500)->get()->filter(function (AssistantExchange $exchange) use ($words): bool {
                $payload = $exchange->payload;
                $text = mb_strtolower($payload['question'].' '.$payload['answer']);
                foreach ($words as $word) {
                    if (! str_contains($text, $word)) {
                        return false;
                    }
                }

                return true;
            })->slice(($page - 1) * 20, 21)->values();
        }

        return ['items' => $items->take(20)->map(fn (AssistantExchange $exchange): array => [
            'id' => $exchange->id, ...$exchange->payload, 'created_at' => $exchange->created_at?->toIso8601String(),
        ])->values()->all(), 'page' => $page, 'has_more' => $items->count() > 20, 'search_window' => $query === '' ? null : 500];
    }
}
