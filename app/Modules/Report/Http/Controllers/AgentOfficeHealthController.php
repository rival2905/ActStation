<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Report\Http\Resources\AgentOfficeHealthResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AgentOfficeHealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $start = hrtime(true);
        try {
            DB::select('SELECT 1');
            $connected = true;
        } catch (Throwable) {
            $connected = false;
        }

        return (new AgentOfficeHealthResource([
            'status' => $connected ? 'healthy' : 'degraded',
            'checked_at' => now()->utc()->toIso8601String(),
            'database' => $connected ? 'connected' : 'unavailable',
            'latency_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
            'n8n_status' => 'not_checked',
        ]))->response($request)->setStatusCode($connected ? 200 : 503);
    }
}
