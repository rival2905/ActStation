<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Contracts\EventLocationLookup;
use App\Http\Controllers\Controller;
use App\Modules\Event\Models\Event;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrganizerGateScannerController extends Controller
{
    public function index(Event $event): View
    {
        $this->authorizeOrganizer($event);

        $workspace = request()->routeIs('admin.*') ? 'admin' : 'organizer';

        $locations = app(EventLocationLookup::class)->options($event->id);

        return view('panel.organizer.gate_scanner.index', compact('event', 'workspace', 'locations'));
    }

    public function verify(Request $request, Event $event): JsonResponse
    {
        $this->authorizeOrganizer($event);

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'venue_id' => ['nullable', 'integer'],
            'venue_court_id' => ['nullable', 'integer'],
        ]);

        $rawToken = trim($validated['token']);

        return DB::transaction(function () use ($validated, $rawToken, $event): JsonResponse {
            /** @var Ticket|null $ticket */
            $ticket = Ticket::query()
                ->where(function ($q) use ($rawToken): void {
                    $q->where('ticket_code', $rawToken)
                        ->orWhere('qr_code', $rawToken)->orWhere('qr_code', hash('sha256', $rawToken));
                })
                ->whereHas('category', fn ($cq) => $cq->where('event_id', $event->id))
                ->with(['category'])
                ->lockForUpdate()->first();

            if (! $ticket) {
                return response()->json([
                    'success' => false,
                    'status' => 'invalid',
                    'message' => 'DITOLAK! Tiket tidak ditemukan atau bukan milik turnamen ini.',
                ], 404);
            }

            // Cek apakah sudah pernah digunakan (PRD_EXTERNAL §4.9 C.2)
            if ($ticket->status === 'used') {
                return response()->json([
                    'success' => false,
                    'status' => 'already_used',
                    'message' => 'DITOLAK! Tiket sudah pernah dipakai pada jam '.($ticket->used_at?->format('H:i:s d M Y') ?? '-'),
                    'holder_name' => $ticket->holder_name,
                ], 422);
            }

            if ($ticket->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'status' => 'inactive',
                    'message' => "DITOLAK! Status tiket tidak aktif ({$ticket->status}).",
                ], 422);
            }

            if (! $ticket->category->allowsLocation(isset($validated['venue_id']) ? (int) $validated['venue_id'] : null, isset($validated['venue_court_id']) ? (int) $validated['venue_court_id'] : null)) {
                return response()->json(['success' => false, 'status' => 'wrong_location', 'message' => 'DITOLAK! Pilih lokasi pintu masuk yang sesuai cakupan tiket. '.$ticket->category->locationLabel()], 422);
            }

            $ticket->update([
                'status' => 'used',
                'used_at' => now(),
            ]);

            $zone = $ticket->seat_number ?? $ticket->category?->seating_zone ?? 'TRIBUN UTAMA';

            return response()->json([
                'success' => true,
                'status' => 'valid',
                'message' => "SAH! Arahkan penonton ke PINTU {$zone}",
                'holder_name' => $ticket->holder_name,
                'category_name' => $ticket->category?->name ?? 'Tiket',
                'seat' => $zone,
                'scanned_at' => now()->format('H:i:s'),
                'location' => $ticket->category->locationLabel(),
            ]);
        });
    }

    private function authorizeOrganizer(Event $event): void
    {
        if (! request()->routeIs('admin.*') && $event->user_id !== Auth::id() && ! Auth::user()?->hasRole('superadmin')) {
            abort(403, 'Akses ditolak.');
        }
    }
}
