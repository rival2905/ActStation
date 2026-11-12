<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Http\Requests\SocialRelayRequest;
use App\Modules\Communication\Http\Resources\SocialRelayResource;
use App\Modules\Communication\Services\SocialRelay;

final class SocialRelayController extends Controller
{
    public function publish(SocialRelayRequest $request, SocialRelay $relay)
    {
        $result = $relay->publish($request->validated(), (int) $request->user()->id);

        return (new SocialRelayResource($result))->response()->setStatusCode($result['duplicate'] ? 200 : 201);
    }

    public function pull(SocialRelayRequest $request, SocialRelay $relay)
    {
        $result = $relay->pull($request->validated(), (int) $request->user()->id);

        return $result === null ? response()->noContent() : new SocialRelayResource($result);
    }

    public function ack(SocialRelayRequest $request, SocialRelay $relay)
    {
        return new SocialRelayResource($relay->ack($request->validated(), (int) $request->user()->id));
    }
}
