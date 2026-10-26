<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\AssistantEventSearch;
use App\Modules\Event\Models\Event;

class AssistantEventSearchService implements AssistantEventSearch
{
    public function search(string $query = '', ?string $from = null, ?string $to = null): array
    {
        $events = Event::query()->whereIn('status', ['published', 'ongoing', 'completed']);
        foreach (preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $events->where(function ($q) use ($word): void {
                $q->where('title', 'like', '%'.addcslashes($word, '%_\\').'%')
                    ->orWhereHas('sportCategory', fn ($sport) => $sport->where('name', 'like', '%'.addcslashes($word, '%_\\').'%'));
            });
        }
        if ($from !== null) {
            $events->whereDate('ends_at', '>=', $from);
        }
        if ($to !== null) {
            $events->whereDate('starts_at', '<=', $to);
        }

        return $events->orderBy('starts_at')->limit(5)
            ->get(['title', 'slug', 'status', 'starts_at', 'ends_at', 'registration_deadline'])
            ->map(fn (Event $event): array => [
                'title' => $event->title,
                'status' => $event->status,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'registration_deadline' => $event->registration_deadline?->toIso8601String(),
                'url' => route('events.show', $event->slug, false),
            ])->all();
    }
}
