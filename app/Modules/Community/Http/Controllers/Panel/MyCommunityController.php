<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Services\MemberCommunityDirectory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyCommunityController extends Controller
{
    public function show(Request $request, Community $community, MemberCommunityDirectory $service): View
    {
        return view('panel.community-directory', ['directory' => $service->show($request->user(), $community)]);
    }

    public function index(Request $request): View
    {
        $memberships = CommunityAthlete::query()->where('user_id', $request->user()->id)
            ->whereNull('left_at')->whereIn('status', ['pending', 'active'])
            ->whereHas('community', fn ($query) => $query->where('is_active', true))
            ->with('community')->latest()->get();

        $officials = CommunityOfficial::query()->where('user_id', $request->user()->id)
            ->where('status', 'active')->whereNull('ended_at')
            ->whereHas('community', fn ($query) => $query->where('is_active', true))
            ->with('community')->orderBy('id')->get();

        return view('panel.my-communities', compact('memberships', 'officials'));
    }
}
