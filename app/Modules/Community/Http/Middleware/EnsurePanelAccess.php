<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hasPanelAccess = $request->user()?->roles
            ->contains(fn ($role) => in_array($role->panel_access, ['external', 'both'], true));

        if (! $hasPanelAccess) {
            abort(403, 'Akun tidak memiliki hak akses panel eksternal.');
        }

        return $next($request);
    }
}
