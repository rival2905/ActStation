<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Services\CommunityLookupService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class PanelBaseController extends Controller
{
    use AuthorizesRequests;

    /**
     * Resolve the current community scoped to the authenticated manager.
     */
    protected function currentCommunity(Request $request): Community
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Superadmin/admin override support
        if ($user->hasRole('superadmin') || $user->hasRole('admin')) {
            $communityId = $request->input('community_id') ?? session('current_community_id');
            if ($communityId) {
                $community = Community::whereNull('deleted_at')->where('is_active', true)->find($communityId);
                if ($community) {
                    $this->validateCommunityContext($request, $community);

                    return $community;
                }
            }
        }

        $communityId = CommunityLookupService::resolveActiveCommunityId($user->id);

        if ($communityId) {
            $community = Community::whereNull('deleted_at')->where('is_active', true)->find($communityId);
            if ($community) {
                $this->validateCommunityContext($request, $community);

                return $community;
            }
        }

        if ($user->hasRole('superadmin') || $user->hasRole('admin')) {
            $community = Community::whereNull('deleted_at')->where('is_active', true)->first();
            if ($community) {
                $this->validateCommunityContext($request, $community);

                return $community;
            }
        }

        abort(403, 'Anda bukan pengurus aktif komunitas manapun.');
    }

    /**
     * Guard against cross-entity stale context form tampering.
     */
    protected function validateCommunityContext(Request $request, Community $community): void
    {
        if (! $request->isMethodSafe() && $request->has('panel_community_id')) {
            $token = $request->input('panel_community_id');
            if ($token !== null && (string) $token !== '') {
                if ((int) $token !== (int) $community->id) {
                    $message = 'Konteks komunitas telah berubah. Silakan muat ulang halaman.';
                    if ($request->expectsJson() || $request->header('X-Inertia')) {
                        abort(response()->json([
                            'message' => $message,
                            'errors' => ['panel_community_id' => [$message]],
                        ], 422));
                    }

                    abort(422, $message);
                }
            }
        }
    }

    /**
     * Guard against cross-community IDOR tampering.
     */
    protected function assertBelongsToCommunity(Community $community, int $foreignCommunityId): void
    {
        if ($community->id !== $foreignCommunityId) {
            abort(403, 'Akses ditolak untuk data komunitas lain.');
        }
    }
}
