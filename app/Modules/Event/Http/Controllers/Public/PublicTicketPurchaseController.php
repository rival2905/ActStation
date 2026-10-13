<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Payment\Models\Transaction;
use App\Modules\Ticketing\Models\Ticket;
use App\Modules\Ticketing\Models\TicketCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicTicketPurchaseController extends Controller
{
    public function index(string $slug): View
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->firstOrFail();

        $ticketCategories = TicketCategory::query()
            ->where('event_id', $event->id)
            ->where('is_active', true)
            ->with(['game.category', 'game.teamA', 'game.teamB'])
            ->get();

        return view('public.events.tickets', compact('event', 'ticketCategories'));
    }

    public function checkout(Request $request, string $slug): RedirectResponse
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->whereIn('status', ['published', 'ongoing'])
            ->firstOrFail();

        $validated = $request->validate([
            'ticket_category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:5'],
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        /** @var TicketCategory $category */
        $category = TicketCategory::query()
            ->where('id', $validated['ticket_category_id'])
            ->where('event_id', $event->id)
            ->firstOrFail();

        $quantity = (int) $validated['quantity'];
        if (($category->sold_count + $quantity) > $category->quota) {
            return back()->with('error', 'Mohon maaf, sisa kuota tiket tidak mencukupi untuk jumlah yang diminta.');
        }

        // Auto-Provisioning Real User jika penonton belum login (PRD_EXTERNAL §4.9 B.3)
        $user = Auth::user();
        if (! $user) {
            $rawPhone = preg_replace('/[^0-9]/', '', $validated['phone_number']);
            $phoneNormalized = str_starts_with((string) $rawPhone, '0')
                ? '+62'.substr((string) $rawPhone, 1)
                : '+'.$rawPhone;

            $user = User::query()->where('email', $validated['email'])
                ->orWhere('phone_normalized', $phoneNormalized)
                ->first();

            if (! $user) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone_number' => $validated['phone_number'],
                    'phone_normalized' => $phoneNormalized,
                    'password' => Hash::make(Str::random(16)),
                    'is_active' => true,
                ]);
                $user->assignRole('member');
            }

            Auth::login($user);
        }

        $totalPrice = (float) $category->price * $quantity;

        DB::transaction(function () use ($user, $category, $quantity, $validated, $totalPrice): void {
            $firstTicketId = null;

            for ($i = 0; $i < $quantity; $i++) {
                $ticketCode = 'TKT-'.strtoupper(Str::random(10));
                $qrToken = hash('sha256', $ticketCode.microtime());

                $ticket = Ticket::create([
                    'ticket_category_id' => $category->id,
                    'user_id' => $user->id,
                    'ticket_code' => $ticketCode,
                    'qr_code' => $qrToken,
                    'holder_name' => $validated['name'],
                    'holder_email' => $validated['email'],
                    'seat_number' => $category->seating_zone ? 'Zona '.$category->seating_zone : null,
                    'status' => 'active',
                ]);

                if ($firstTicketId === null) {
                    $firstTicketId = $ticket->id;
                }
            }

            $category->update(['sold_count' => $category->sold_count + $quantity]);

            // Escrow Transaction
            Transaction::create([
                'user_id' => $user->id,
                'invoice_number' => 'INV-TKT-'.strtoupper(Str::random(8)),
                'type' => 'ticket',
                'payable_type' => 'ticket',
                'payable_id' => $firstTicketId ?? 0,
                'amount' => $totalPrice,
                'fee' => 0.00,
                'total' => $totalPrice,
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        });

        return redirect()
            ->route('my-tickets.index')
            ->with('success', 'Pembelian '.$quantity.' tiket berhasil! E-Ticket digital dengan QR Code aktif di bawah ini.');
    }
}
