<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\Public\StoreAthleteEventDocumentRequest;
use App\Modules\Event\Services\AthleteEventWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MyEventController extends Controller
{
    public function index(AthleteEventWorkspace $workspace): View
    {
        return view('public.my.events', $workspace->getWorkspaceData((int) Auth::id()));
    }

    public function upload(StoreAthleteEventDocumentRequest $request, AthleteEventWorkspace $workspace, string $kind, string $id, string $code): RedirectResponse
    {
        $workspace->uploadDocument((int) $request->user()->id, $kind, $id, $code, $request->file('document'));

        return redirect()->route('my-events.index')->with('success', 'Dokumen tersimpan. Menunggu pemeriksaan.');
    }
}
