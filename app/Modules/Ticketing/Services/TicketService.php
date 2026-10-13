<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Modules\Ticketing\Exceptions\TicketSoldOutException;
use App\Modules\Ticketing\Models\Ticket;
use App\Modules\Ticketing\Models\TicketCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketService
{
    /**
     * Issue a new ticket with pessimistic quota lock.
     */
    public function issue(
        int|TicketCategory $category,
        int $userId,
        string $holderName,
        ?string $holderEmail = null,
        ?int $transactionId = null,
        string $status = 'active',
    ): Ticket {
        return DB::transaction(function () use ($category, $userId, $holderName, $holderEmail, $transactionId, $status): Ticket {
            $categoryId = $category instanceof TicketCategory ? $category->id : $category;

            /** @var TicketCategory $cat */
            $cat = TicketCategory::where('id', $categoryId)->lockForUpdate()->firstOrFail();

            if ($cat->sold_count >= $cat->quota) {
                throw new TicketSoldOutException("Kategori tiket '{$cat->name}' telah habis.");
            }

            $rawToken = bin2hex(random_bytes(16));
            $hashedToken = hash('sha256', $rawToken);

            $ticketCode = 'TKT-'.date('Ymd').'-'.strtoupper(Str::random(6));
            while (Ticket::where('ticket_code', $ticketCode)->exists()) {
                $ticketCode = 'TKT-'.date('Ymd').'-'.strtoupper(Str::random(6));
            }

            // ponytail: upgrade to SVG/PNG QR image generation with chillerlan/php-qrcode when needed
            $ticket = Ticket::create([
                'ticket_category_id' => $cat->id,
                'transaction_id' => $transactionId,
                'user_id' => $userId,
                'ticket_code' => $ticketCode,
                'qr_code' => $hashedToken,
                'holder_name' => $holderName,
                'holder_email' => $holderEmail,
                'status' => $status,
            ]);

            $cat->increment('sold_count');
            $ticket->raw_token = $rawToken;

            return $ticket;
        });
    }
}
