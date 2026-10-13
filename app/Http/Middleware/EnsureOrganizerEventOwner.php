<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Event\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizerEventOwner
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');

        abort_unless($event instanceof Event && (int) $event->user_id === (int) $request->user()?->id, 403);

        return $next($request);
    }
}
