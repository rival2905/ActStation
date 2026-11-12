<?php

declare(strict_types=1);

namespace App\Modules\Scoreboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scoreboard\Http\Requests\ShotAccessRequest;
use App\Modules\Scoreboard\Http\Resources\ScoreboardResource;
use App\Modules\Scoreboard\Services\ScoreboardService;
use Illuminate\Http\Request;

class ShotAccessController extends Controller
{
    public function store(ShotAccessRequest $request, string $session, ScoreboardService $service)
    {
        return (new ScoreboardResource($service->shareShot($request, $session, (int) $request->validated('hours'))))->response()->setStatusCode(201);
    }

    public function destroy(ShotAccessRequest $request, string $session, ScoreboardService $service): ScoreboardResource
    {
        return new ScoreboardResource($service->revokeShot($request, $session));
    }

    public function show(Request $request, string $session, ScoreboardService $service)
    {
        return response()->view('panel.scoreboard.shot-guest', $service->shotPage($request, $session))
            ->withHeaders(['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']);
    }

    public function snapshot(Request $request, string $session, ScoreboardService $service)
    {
        return (new ScoreboardResource($service->readShot($request, $session)))->response()
            ->withHeaders(['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']);
    }

    public function command(ShotAccessRequest $request, string $session, ScoreboardService $service)
    {
        return (new ScoreboardResource($service->command($request, $session, $request->validated(), true)))->response()
            ->withHeaders(['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']);
    }

    public function receipt(Request $request, string $session, ScoreboardService $service)
    {
        return (new ScoreboardResource($service->shotReceipt($request, $session)))->response()
            ->withHeaders(['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']);
    }
}
