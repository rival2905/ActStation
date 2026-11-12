<?php

declare(strict_types=1);

namespace App\Modules\Scoreboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scoreboard\Http\Requests\PublicScoreboardRequest;
use App\Modules\Scoreboard\Http\Resources\ScoreboardResource;
use App\Modules\Scoreboard\Services\ScoreboardService;
use Illuminate\Http\Request;

class PublicScoreboardController extends Controller
{
    public function index(Request $request, ScoreboardService $service)
    {
        return response()->view('panel.scoreboard.public-index', $service->personalListing($request))->header('Cache-Control', 'private, no-store');
    }

    public function store(PublicScoreboardRequest $request, ScoreboardService $service)
    {
        $session = $service->createPersonal($request, $request->validated());

        return (new ScoreboardResource($service->snapshot($session)))->response()->setStatusCode($session->wasRecentlyCreated ? 201 : 200)->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $session, ScoreboardService $service)
    {
        $model = $service->readPersonal($request, $session);
        if ($request->user() && $model->created_by === null) {
            return response()->view('panel.scoreboard.personal-claim', ['session' => $model])->header('Cache-Control', 'private, no-store');
        }

        return response()->view($request->boolean('focus', true) ? 'panel.scoreboard.focus' : 'panel.scoreboard.console', [
            'session' => $model, 'community' => (object) ['id' => null, 'name' => 'Main Cepat pribadi'],
            'viewMode' => 'console', 'personal' => true,
        ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function snapshot(Request $request, string $session, ScoreboardService $service)
    {
        return (new ScoreboardResource($service->snapshot($service->readPersonal($request, $session))))->response()->header('Cache-Control', 'private, no-store');
    }

    public function command(PublicScoreboardRequest $request, string $session, ScoreboardService $service)
    {
        return (new ScoreboardResource($service->command($request, $session, $request->validated(), personal: true)))->response()->header('Cache-Control', 'private, no-store');
    }

    public function receipt(Request $request, string $session, string $command, ScoreboardService $service)
    {
        return (new ScoreboardResource($service->personalReceipt($request, $session, $command)))->response()->header('Cache-Control', 'private, no-store');
    }

    public function claim(PublicScoreboardRequest $request, string $session, ScoreboardService $service)
    {
        $model = $service->claimPersonal($request, $session);
        if ($request->expectsJson()) {
            return (new ScoreboardResource($service->snapshot($model)))->response()->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('scoreboard.public.show', $model->id);
    }

    public function authenticate(Request $request, string $session, string $action, ScoreboardService $service)
    {
        $service->readPersonal($request, $session);
        if ($request->user()) {
            return redirect()->route('scoreboard.public.show', $session);
        }
        $request->session()->put('scoreboard_registration_return', $session);
        $request->session()->put('url.intended', route('scoreboard.public.show', $session));

        return redirect()->route($action === 'register' ? 'register' : 'panel.login');
    }

    public function finish(PublicScoreboardRequest $request, string $session, ScoreboardService $service)
    {
        return (new ScoreboardResource($service->finish($request, $session, $request->validated(), personal: true)))->response()->header('Cache-Control', 'private, no-store');
    }
}
