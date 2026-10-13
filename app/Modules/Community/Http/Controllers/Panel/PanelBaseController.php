<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
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
                $community = Community::find($communityId);
                if ($community) {
                    return $community;
                }
            }
        }

        /** @var CommunityOfficial|null $official */
        $official = CommunityOfficial::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->with('community')
            ->first();

        if ($official && $official->community) {
            return $official->community;
        }

        if ($user->hasRole('superadmin') || $user->hasRole('admin')) {
            $community = Community::first();
            if ($community) {
                return $community;
            }
        }

        abort(403, 'Anda bukan pengurus aktif komunitas manapun.');
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
