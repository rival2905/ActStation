<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Middleware;

use App\Modules\Community\Models\Community;
use App\Modules\Community\Services\CommunityLookupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidCommunityContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        if ($request->routeIs(
            'panel.switch',
            'panel.community.switch',
            'panel.login',
            'panel.login.attempt',
            'panel.logout',
            'panel.community.create',
            'panel.community.store'
        )) {
            return $next($request);
        }

        if ($request->has('panel_community_id')) {
            $expectedToken = $request->input('panel_community_id');
            if ($expectedToken !== null && (string) $expectedToken !== '') {
                $user = $request->user();
                if (! $user) {
                    abort(401);
                }

                $activeCommunityId = CommunityLookupService::resolveActiveCommunityId((int) $user->id);

                if (! $activeCommunityId && ($user->hasRole('superadmin') || $user->hasRole('admin'))) {
                    $activeCommunityId = (int) Community::whereNull('deleted_at')->where('is_active', true)->value('id');
                }

                if (! $activeCommunityId || (int) $expectedToken !== (int) $activeCommunityId) {
                    $message = 'Konteks komunitas telah berubah. Silakan muat ulang halaman.';
                    if ($request->expectsJson() || $request->header('X-Inertia')) {
                        return response()->json([
                            'message' => $message,
                            'errors' => ['panel_community_id' => [$message]],
                        ], 422);
                    }

                    abort(422, $message);
                }
            }
        }

        return $next($request);
    }
}
