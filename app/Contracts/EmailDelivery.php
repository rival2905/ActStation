<?php

declare(strict_types=1);

namespace App\Contracts;

interface EmailDelivery
{
    /**
     * Send an email message for delivery.
     *
     * @param array{
     *     recipient_user_id?: int|string|null,
     *     recipient_email: string,
     *     recipient_name?: string,
     *     name?: string,
     *     subject: string,
     *     body: string,
     *     action_url?: string|null,
     *     action_label?: string|null,
     *     type: string,
     *     attachments?: array<int, array{path: string, name?: string, mime?: string}|string>
     * } $message
     * @return int|string Notification ID
     */
    public function send(array $message): int|string;
}
