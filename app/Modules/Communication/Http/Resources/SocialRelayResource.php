<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SocialRelayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_intersect_key($this->resource, array_flip(['delivery_id', 'status', 'duplicate', 'channel', 'account_ref', 'claim_token', 'claim_expires_at', 'payload']));
    }
}
