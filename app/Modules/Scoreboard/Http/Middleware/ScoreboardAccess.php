<?php

declare(strict_types=1);

namespace App\Modules\Scoreboard\Http\Middleware;

use App\Modules\Scoreboard\Services\ScoreboardService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScoreboardAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $community = app(ScoreboardService::class)->community($request);
        $request->attributes->set('scoreboard_community', $community);

        return $next($request)->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
