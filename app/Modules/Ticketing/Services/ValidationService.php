<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Modules\Ticketing\Exceptions\TicketValidationException;
use App\Modules\Ticketing\Models\Ticket;
use App\Modules\Ticketing\Models\TicketValidation;
use Illuminate\Support\Facades\DB;

class ValidationService
{
    /**
     * Validate and scan ticket using token (QR).
     */
    public function validate(
        string $token,
        int $validatedByUserId,
        ?string $gate = null,
        ?string $notes = null,
    ): TicketValidation {
        $clean = trim($token);
        $hashedToken = hash('sha256', $clean);

        return DB::transaction(function () use ($hashedToken, $validatedByUserId, $gate, $notes): TicketValidation {
            /** @var Ticket|null $ticket */
            $ticket = Ticket::where('qr_code', $hashedToken)
                ->lockForUpdate()
                ->first();
            if (! $ticket) {
                throw new TicketValidationException('Tiket tidak ditemukan atau kode tidak valid.');
            }

            if ($ticket->status === 'used') {
                $usedAt = $ticket->used_at ? $ticket->used_at->format('d/m/Y H:i') : 'sebelumnya';
                throw new TicketValidationException("Tiket sudah digunakan pada {$usedAt}.");
            }

            if ($ticket->status === 'cancelled') {
                throw new TicketValidationException('Tiket telah dibatalkan.');
            }

            if ($ticket->status === 'expired') {
                throw new TicketValidationException('Tiket telah kedaluwarsa.');
            }

            if ($ticket->status !== 'active') {
                throw new TicketValidationException("Tiket tidak aktif (status: {$ticket->status}).");
            }

            $ticket->update([
                'status' => 'used',
                'used_at' => now(),
            ]);

            return TicketValidation::create([
                'ticket_id' => $ticket->id,
                'validated_by' => $validatedByUserId,
                'validated_at' => now(),
                'gate' => $gate,
                'notes' => $notes,
            ]);
        });
    }
}
