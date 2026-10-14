<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Contracts\EmailDelivery;
use App\Modules\Communication\Models\EmailMessage;
use finfo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class CommunicationEmailDelivery implements EmailDelivery
{
    public const ALLOWED_MIMES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
    ];

    public const MAX_ATTACHMENTS = 3;

    public const MAX_ATTACHMENT_SIZE = 5242880; // 5MB

    public function __construct(protected N8nEmailSender $sender) {}

    /**
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
     */
    public function send(array $message): int|string
    {
        if (! isset($message['recipient_email']) || ! is_string($message['recipient_email'])) {
            throw new InvalidArgumentException('Recipient email must be a string');
        }

        $recipientEmail = trim($message['recipient_email']);
        if ($recipientEmail === '' || strlen($recipientEmail) > 255 || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid recipient email address');
        }

        $rawName = $message['recipient_name'] ?? $message['name'] ?? '';
        if (! is_string($rawName)) {
            throw new InvalidArgumentException('Recipient name must be a string');
        }
        $recipientName = trim($rawName);
        if (strlen($recipientName) > 255) {
            throw new InvalidArgumentException('Recipient name exceeds maximum length of 255 characters');
        }

        if (! isset($message['subject']) || ! is_string($message['subject'])) {
            throw new InvalidArgumentException('Subject must be a string');
        }
        $subject = trim($message['subject']);
        if ($subject === '') {
            throw new InvalidArgumentException('Subject cannot be empty');
        }
        if (mb_strlen($subject) > 255) {
            throw new InvalidArgumentException('Subject exceeds maximum length of 255 characters');
        }

        if (isset($message['body']) && ! is_string($message['body'])) {
            throw new InvalidArgumentException('Body must be a string');
        }
        $body = (string) ($message['body'] ?? '');

        $type = (string) ($message['type'] ?? EmailMessage::TYPE_INFORMATION);
        if (! in_array($type, EmailMessage::VALID_TYPES, true)) {
            throw new InvalidArgumentException("Invalid email message type: {$type}");
        }

        $actionUrl = null;
        if (isset($message['action_url']) && $message['action_url'] !== null && $message['action_url'] !== '') {
            if (! is_string($message['action_url'])) {
                throw new InvalidArgumentException('Action URL must be a string');
            }
            $actionUrl = trim($message['action_url']);
            if (strlen($actionUrl) > 2048) {
                throw new InvalidArgumentException('Action URL exceeds maximum length of 2048 characters');
            }
            $scheme = parse_url($actionUrl, PHP_URL_SCHEME);
            if (! in_array(strtolower((string) $scheme), ['http', 'https'], true) || ! filter_var($actionUrl, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('Action URL must use valid http or https scheme');
            }
            $lowered = strtolower($actionUrl);
            if (str_starts_with($lowered, 'javascript:') || str_starts_with($lowered, 'data:') || str_starts_with($lowered, 'vbscript:')) {
                throw new InvalidArgumentException('Action URL contains unsafe protocol');
            }
        }

        $actionLabel = null;
        if (isset($message['action_label']) && $message['action_label'] !== null && $message['action_label'] !== '') {
            if (! is_string($message['action_label'])) {
                throw new InvalidArgumentException('Action label must be a string');
            }
            $actionLabel = trim($message['action_label']);
            if (mb_strlen($actionLabel) > 100) {
                throw new InvalidArgumentException('Action label exceeds maximum length of 100 characters');
            }
        }

        $trustedAttachments = [];
        if (isset($message['attachments'])) {
            if (! is_array($message['attachments'])) {
                throw new InvalidArgumentException('Attachments must be an array');
            }

            if (count($message['attachments']) > self::MAX_ATTACHMENTS) {
                throw new InvalidArgumentException('Maximum '.self::MAX_ATTACHMENTS.' attachments allowed');
            }

            $disk = Storage::disk('local');
            $allowedDir = realpath($disk->path('email-attachments'));
            if (! $allowedDir) {
                $disk->makeDirectory('email-attachments');
                $allowedDir = realpath($disk->path('email-attachments'));
            }
            $allowedPrefix = $allowedDir ? rtrim($allowedDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR : null;

            foreach ($message['attachments'] as $att) {
                if (! is_array($att) && ! is_string($att)) {
                    throw new InvalidArgumentException('Invalid attachment specification');
                }

                $path = is_array($att) ? (string) ($att['path'] ?? '') : (string) $att;
                if ($path === '') {
                    throw new InvalidArgumentException('Attachment path cannot be empty');
                }

                $name = is_array($att) ? (string) ($att['name'] ?? basename($path)) : basename($path);

                $fullPath = (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $path))
                    ? $disk->path($path)
                    : $path;

                $realPath = realpath($fullPath);
                if (! $realPath || ! file_exists($realPath) || ! is_file($realPath)) {
                    throw new InvalidArgumentException("Attachment file not found: {$path}");
                }

                if (! $allowedPrefix || ! str_starts_with($realPath, $allowedPrefix)) {
                    throw new InvalidArgumentException('Attachment path outside allowed storage rejected');
                }

                $size = filesize($realPath);
                if ($size > self::MAX_ATTACHMENT_SIZE) {
                    throw new InvalidArgumentException("Attachment {$name} exceeds 5MB size limit");
                }

                $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $fileInfo->file($realPath) ?: (mime_content_type($realPath) ?: '');
                if (! in_array($mime, self::ALLOWED_MIMES, true)) {
                    throw new InvalidArgumentException("Attachment mime type not allowed: {$mime}");
                }

                $relativeSub = str_replace('\\', '/', substr($realPath, strlen($allowedPrefix)));
                $canonicalRelativePath = 'email-attachments/'.$relativeSub;

                $trustedAttachments[] = [
                    'path' => $canonicalRelativePath,
                    'name' => $name,
                    'mime' => $mime,
                    'size' => $size,
                ];
            }
        }

        $recipientUserId = isset($message['recipient_user_id']) && $message['recipient_user_id'] !== '' && $message['recipient_user_id'] !== null
            ? (int) $message['recipient_user_id']
            : null;

        $emailRecord = EmailMessage::create([
            'recipient_user_id' => $recipientUserId,
            'recipient' => [
                'email' => $recipientEmail,
                'name' => $recipientName,
            ],
            'subject' => $subject,
            'type' => $type,
            'status' => EmailMessage::STATUS_QUEUED,
            'payload' => [
                'body' => $body,
                'action_url' => $actionUrl,
                'action_label' => $actionLabel,
                'attachments' => $trustedAttachments,
            ],
        ]);

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(function () use ($emailRecord): void {
                $this->sender->send($emailRecord->id);
            });
        } else {
            $this->sender->send($emailRecord->id);
        }

        return $emailRecord->id;
    }
}
