<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentOfficeHealthResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource['status'],
            'checked_at' => $this->resource['checked_at'],
            'database' => $this->resource['database'],
            'latency_ms' => $this->resource['latency_ms'],
            'n8n_status' => $this->resource['n8n_status'],
        ];
    }

    public function with(Request $request): array
    {
        return ['meta' => ['contract_version' => 'agent-office.v1', 'request_id' => $request->attributes->get('gateway_request_id')]];
    }
}
