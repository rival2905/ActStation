<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Event\Models\Event;
use Illuminate\Http\Request;

class EventRegistrationReturn
{
    public const SESSION_KEY = 'event_registration_return';

    public function remember(Request $request, int $eventId, ?int $categoryId = null): void
    {
        $event = Event::query()
            ->where('id', $eventId)
            ->where('status', 'published')
            ->first();

        if (! $event) {
            return;
        }

        $validatedCategoryId = null;
        if ($categoryId !== null) {
            $categoryExists = $event->categories()
                ->where('id', $categoryId)
                ->exists();

            if ($categoryExists) {
                $validatedCategoryId = $categoryId;
            }
        }

        $request->session()->put(self::SESSION_KEY, [
            'event_id' => (int) $event->id,
            'category_id' => $validatedCategoryId,
        ]);
    }

    public function destination(Request $request): ?string
    {
        $data = $request->session()->get(self::SESSION_KEY);

        if (! is_array($data) || ! isset($data['event_id']) || ! is_numeric($data['event_id'])) {
            return null;
        }

        $eventId = (int) $data['event_id'];
        $rawCategoryId = $data['category_id'] ?? null;
        $categoryId = is_numeric($rawCategoryId) ? (int) $rawCategoryId : null;

        $event = Event::query()
            ->where('id', $eventId)
            ->where('status', 'published')
            ->first();

        if (! $event) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        $params = ['slug' => $event->slug];

        if ($categoryId !== null) {
            $categoryValid = $event->categories()
                ->where('id', $categoryId)
                ->exists();

            if ($categoryValid) {
                $params['event_category_id'] = $categoryId;
            }
        }

        return route('panel.community-events.create', $params);
    }

    public function publicDestinationFromIntended(Request $request): ?string
    {
        $intended = $request->session()->get('url.intended');
        $pattern = str_replace('EVENT_SLUG', '([^/?#]+)', preg_quote(route('panel.community-events.create', ['slug' => 'EVENT_SLUG']), '~'));
        if (! is_string($intended) || ! preg_match('~^'.$pattern.'(?:\?([^#]*))?$~D', $intended, $matches)) {
            return null;
        }

        $event = Event::query()->where('slug', rawurldecode($matches[1]))
            ->where('status', 'published')->where('registration_open', true)->first();
        if (! $event) {
            return null;
        }

        parse_str($matches[2] ?? '', $query);
        $categoryId = $query['event_category_id'] ?? null;
        $params = ['slug' => $event->slug];
        if (is_string($categoryId) && ctype_digit($categoryId) && $event->categories()->whereKey($categoryId)->exists()) {
            $params['event_category_id'] = (int) $categoryId;
        }
        $request->session()->forget('url.intended');

        return route('events.register', $params);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
