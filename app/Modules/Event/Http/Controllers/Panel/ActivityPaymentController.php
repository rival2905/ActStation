<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\ManualActivityPaymentRequest;
use App\Modules\Event\Models\ActivityRegistration;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Services\ActivityPaymentService;
use Illuminate\Http\Request;

class ActivityPaymentController extends Controller
{
    public function __construct(private readonly ActivityPaymentService $payments) {}

    public function show(Request $request, Event $event, ActivityRegistration $registration)
    {
        return view('events.activities.checkout', $this->payments->show($event, $registration, $request->user()));
    }

    public function upload(ManualActivityPaymentRequest $request, Event $event, ActivityRegistration $registration)
    {
        $this->payments->upload($event, $registration, $request->user(), $request->validated(), (string) $request->ip(), (string) $request->userAgent());

        return back()->with('success', 'Bukti disimpan, menunggu pemeriksaan panitia.');
    }

    public function confirm(Request $request, Event $event, ActivityRegistration $registration)
    {
        $this->payments->confirm($event, $registration, $request->user());

        return back()->with('success', 'Pembayaran dikonfirmasi.');
    }

    public function download(Request $request, Event $event, ActivityRegistration $registration)
    {
        return $this->payments->download($event, $registration, $request->user());
    }
}
