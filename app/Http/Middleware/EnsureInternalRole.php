<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hasInternalAccess = $request->user()?->roles
            ->contains(fn ($role) => in_array($role->panel_access, ['internal', 'both'], true));

        if (! $hasInternalAccess) {
            abort(403, 'Akun tidak memiliki hak akses internal.');
        }

        return $next($request);
    }
}
