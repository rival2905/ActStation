<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailMessage extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNKNOWN = 'unknown';

    public const TYPE_INFORMATION = 'information';

    public const TYPE_READINESS = 'readiness';

    public const TYPE_ASSIGNMENT = 'assignment';

    public const TYPE_VERIFICATION = 'verification';

    public const VALID_STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_PROCESSING,
        self::STATUS_SUBMITTED,
        self::STATUS_FAILED,
        self::STATUS_UNKNOWN,
    ];

    public const VALID_TYPES = [
        self::TYPE_INFORMATION,
        self::TYPE_READINESS,
        self::TYPE_ASSIGNMENT,
        self::TYPE_VERIFICATION,
    ];

    protected $table = 'email_messages';

    protected $guarded = ['id'];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'payload',
        'recipient',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recipient' => 'encrypted:array',
            'payload' => 'encrypted:array',
            'submitted_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function getRecipientEmail(): string
    {
        return (string) ($this->recipient['email'] ?? '');
    }

    public function getRecipientEmailAttribute(): string
    {
        return $this->getRecipientEmail();
    }

    public function getRecipientName(): string
    {
        return (string) ($this->recipient['name'] ?? $this->recipient['recipient_name'] ?? '');
    }

    public function getRecipientNameAttribute(): string
    {
        return $this->getRecipientName();
    }

    public function getAttachmentsAttribute(): array
    {
        return $this->payload['attachments'] ?? [];
    }

    public function getAttachmentCountAttribute(): int
    {
        return count($this->getAttachmentsAttribute());
    }

    public function getMaskedRecipient(): string
    {
        $email = $this->getRecipientEmail();
        if ($email === '' || ! str_contains($email, '@')) {
            return '***';
        }

        [$name, $domain] = explode('@', $email, 2);
        $len = mb_strlen($name);
        $maskedName = $len <= 2
            ? mb_substr($name, 0, 1).'*'
            : mb_substr($name, 0, 1).str_repeat('*', $len - 2).mb_substr($name, -1);

        return $maskedName.'@'.$domain;
    }
}
