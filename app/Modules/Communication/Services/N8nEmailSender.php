<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Models\EmailMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Throwable;

class N8nEmailSender
{
    public function send(int|string $messageId): ?EmailMessage
    {
        $message = EmailMessage::find($messageId);
        if (! $message || $message->status === EmailMessage::STATUS_SUBMITTED) {
            return $message;
        }

        $claimed = EmailMessage::query()->whereKey($messageId)
            ->where('status', EmailMessage::STATUS_QUEUED)
            ->update(['status' => EmailMessage::STATUS_PROCESSING]);
        if (! $claimed) {
            return $message->fresh();
        }

        try {
            $enabled = (bool) config('services.n8n.email_enabled', false);
            $webhookUrl = (string) config('services.n8n.webhook_url', '');
            $webhookSecret = (string) config('services.n8n.webhook_secret', '');
            $allowedHost = (string) config('services.n8n.allowed_host', 'n8.fasekita.my.id');

            $parsed = parse_url($webhookUrl);
            $isHttps = ($parsed['scheme'] ?? '') === 'https';
            $host = $parsed['host'] ?? '';
            $isHostAllowed = empty($allowedHost) || strcasecmp($host, $allowedHost) === 0;

            if (! $enabled || empty($webhookUrl) || empty($webhookSecret) || ! $isHttps || ! $isHostAllowed) {
                $message->update([
                    'status' => EmailMessage::STATUS_FAILED,
                    'failed_at' => now(),
                    'error_message' => 'n8n email service is unconfigured or disabled',
                ]);

                return $message->fresh();
            }

            $payloadData = $message->payload ?? [];
            $recipientName = (string) ($message->recipient['name'] ?? $message->recipient['recipient_name'] ?? '');
            $recipientEmail = (string) ($message->recipient['email'] ?? '');
            $body = (string) ($payloadData['body'] ?? '');
            $actionUrl = $payloadData['action_url'] ?? null;
            $actionLabel = $payloadData['action_label'] ?? null;
            $title = (string) $message->subject;
            $type = (string) $message->type;

            $attachmentsData = $payloadData['attachments'] ?? [];
            $viewData = [
                'type' => $type,
                'title' => $title,
                'subject' => $title,
                'body' => $body,
                'recipientName' => $recipientName,
                'recipient_name' => $recipientName,
                'actionURL' => $actionUrl,
                'action_url' => $actionUrl,
                'actionLabel' => $actionLabel,
                'action_label' => $actionLabel,
                'attachments' => $attachmentsData,
                'message_id' => (string) $message->id,
            ];

            $html = View::exists('emails.actstation')
                ? View::make('emails.actstation', $viewData)->render()
                : '<!DOCTYPE html><html><body><h2>'.e($title).'</h2><p>Halo '.e($recipientName).',</p><p>'.nl2br(e($body)).'</p>'.($actionUrl ? '<p><a href="'.e((string) $actionUrl).'">'.e((string) ($actionLabel ?? 'Klik di sini')).'</a></p>' : '').'</body></html>';

            $attachmentsPayload = [];
            $rawAttachments = $payloadData['attachments'] ?? [];
            if (is_array($rawAttachments)) {
                foreach (array_values($rawAttachments) as $i => $att) {
                    $attPath = is_array($att) ? ($att['path'] ?? '') : (string) $att;
                    $attName = is_array($att) ? ($att['name'] ?? basename($attPath)) : basename($attPath);
                    $attMime = is_array($att) ? ($att['mime'] ?? 'application/octet-stream') : 'application/octet-stream';

                    $downloadUrl = URL::temporarySignedRoute(
                        'email.attachments.download',
                        now()->addMinutes(20),
                        ['message' => $message->id, 'index' => $i]
                    );

                    $attachmentsPayload[] = [
                        'name' => $attName,
                        'url' => $downloadUrl,
                        'mime' => $attMime,
                    ];
                }
            }

            $postPayload = [
                'event' => 'notification.email',
                'message_id' => (string) $message->id,
                'timestamp' => now()->toIso8601String(),
                'data' => [
                    'type' => $type,
                    'email' => $recipientEmail,
                    'name' => $recipientName,
                    'title' => $title,
                    'body' => $body,
                    'html' => $html,
                    'url' => $actionUrl,
                    'attachments' => $attachmentsPayload,
                ],
            ];

            $response = Http::timeout(15)
                ->withOptions(['verify' => true, 'allow_redirects' => false])
                ->withHeaders([
                    'X-Webhook-Token' => $webhookSecret,
                    'X-Webhook-Source' => 'laravel-actstation',
                ])
                ->post($webhookUrl, $postPayload);

            if ($response->successful()) {
                $message->update([
                    'status' => EmailMessage::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                    'error_message' => null,
                ]);
            } else {
                $statusCode = $response->status();
                $message->update([
                    'status' => EmailMessage::STATUS_FAILED,
                    'failed_at' => now(),
                    'error_message' => "HTTP {$statusCode}: webhook request failed",
                ]);
            }
        } catch (ConnectionException $e) {
            $message->update([
                'status' => EmailMessage::STATUS_UNKNOWN,
                'failed_at' => now(),
                'error_message' => 'Connection interrupted: delivery unknown, manual retry required',
            ]);
        } catch (Throwable $e) {
            $message->update([
                'status' => EmailMessage::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => 'Unexpected error dispatching to webhook',
            ]);
        }

        return $message->fresh();
    }
}
