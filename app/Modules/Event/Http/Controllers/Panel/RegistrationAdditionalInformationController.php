<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Contracts\CommunityLookup;
use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\SaveRegistrationAdditionalRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Services\RegistrationAdditionalInformationService;
use App\Modules\Event\Services\RegistrationDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationAdditionalInformationController extends Controller
{
    public function __construct(private readonly RegistrationAdditionalInformationService $service) {}

    private function context(Request $request, string $slug, string $draft): array
    {
        abort_unless($request->user()?->is_active, 403);
        $event = Event::where('slug', $slug)->where('status', 'published')->firstOrFail();
        $community = app(CommunityLookup::class)->findForUser((int) $request->user()->id);
        abort_unless($community && $community->is_active, 403);
        $model = app(RegistrationDraftService::class)->authorizeDraft($draft, $request->user(), $community, $event);

        return [$event, $community, $model];
    }

    public function show(Request $request, string $slug, string $draft): JsonResponse
    {
        [$event, $community, $model] = $this->context($request, $slug, $draft);

        return response()->json($this->service->read($event, $community, $model));
    }

    public function save(SaveRegistrationAdditionalRequest $request, string $slug, string $draft): JsonResponse
    {
        [$event, $community, $model] = $this->context($request, $slug, $draft);

        return response()->json($this->service->save($event, $request->user(), $community, $model, $request->validated()));
    }
}
