<?php

declare(strict_types=1);

namespace App\Modules\Scoreboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scoreboard\Http\Requests\ScoreboardRequest;
use App\Modules\Scoreboard\Http\Resources\ScoreboardResource;
use App\Modules\Scoreboard\Services\ScoreboardService;
use Illuminate\Http\Request;

class ScoreboardController extends Controller
{
    public function index(Request $request, ScoreboardService $service)
    {
        return view('panel.scoreboard.index', $service->listing($request));
    }

    public function show(Request $request, string $session, ScoreboardService $service)
    {
        return view('panel.scoreboard.console', ['session' => $service->read($request, $session),
            'community' => $service->community($request), 'viewMode' => $request->query('console') === 'shot' ? 'shot' : 'console']);
    }

    public function display(Request $request, string $session, string $viewMode, ScoreboardService $service)
    {
        abort_unless(in_array($viewMode, ['main', 'shot-left', 'shot-right', 'demo'], true), 404);

        return view('panel.scoreboard.display', ['session' => $service->read($request, $session),
            'community' => $service->community($request), 'viewMode' => $viewMode]);
    }

    public function store(ScoreboardRequest $request, ScoreboardService $service)
    {
        return (new ScoreboardResource($service->snapshot($service->create($request, $request->validated()))))->response()->setStatusCode(201);
    }

    public function snapshot(Request $request, string $session, ScoreboardService $service): ScoreboardResource
    {
        return new ScoreboardResource($service->snapshot($service->read($request, $session)));
    }

    public function command(ScoreboardRequest $request, string $session, ScoreboardService $service): ScoreboardResource
    {
        return new ScoreboardResource($service->command($request, $session, $request->validated()));
    }

    public function receipt(Request $request, string $session, string $command, ScoreboardService $service): ScoreboardResource
    {
        return new ScoreboardResource($service->getReceipt($request, $session, $command));
    }

    public function finish(ScoreboardRequest $request, string $session, ScoreboardService $service): ScoreboardResource
    {
        return new ScoreboardResource($service->finish($request, $session, $request->validated()));
    }
}
