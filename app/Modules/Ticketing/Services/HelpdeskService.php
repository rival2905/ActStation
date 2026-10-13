<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Modules\Ticketing\Models\HelpdeskReply;
use App\Modules\Ticketing\Models\HelpdeskTicket;

class HelpdeskService
{
    public function createTicket(
        int $userId,
        string $subject,
        string $body,
        string $priority = 'medium',
    ): HelpdeskTicket {
        return HelpdeskTicket::create([
            'user_id' => $userId,
            'subject' => $subject,
            'body' => $body,
            'priority' => $priority,
            'status' => 'open',
        ]);
    }

    public function assign(HelpdeskTicket $ticket, ?int $assignedToUserId): HelpdeskTicket
    {
        $ticket->update([
            'assigned_to' => $assignedToUserId,
            'status' => ($assignedToUserId && $ticket->status === 'open') ? 'in_progress' : $ticket->status,
        ]);

        return $ticket->refresh();
    }

    public function reply(HelpdeskTicket $ticket, int $userId, string $body): HelpdeskReply
    {
        $reply = HelpdeskReply::create([
            'helpdesk_ticket_id' => $ticket->id,
            'user_id' => $userId,
            'body' => $body,
        ]);

        if ($ticket->status === 'resolved' || $ticket->status === 'closed') {
            $ticket->update(['status' => 'in_progress', 'resolved_at' => null]);
        }

        return $reply;
    }

    public function updateStatus(HelpdeskTicket $ticket, string $status): HelpdeskTicket
    {
        $data = ['status' => $status];

        if (in_array($status, ['resolved', 'closed'], true)) {
            $data['resolved_at'] = now();
        } elseif ($status === 'open' || $status === 'in_progress') {
            $data['resolved_at'] = null;
        }

        $ticket->update($data);

        return $ticket->refresh();
    }
}
