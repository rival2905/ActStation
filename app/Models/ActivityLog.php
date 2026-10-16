<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

class ActivityLog extends Model
{
    public const REDACTED = '[REDACTED]';

    public const SENSITIVE_ATTRIBUTES = [
        'password',
        'remember_token',
        'identity_number',
        'nisn',
        'nim',
        'identity_image',
        'birth_certificate',
        'token',
        'qr_code',
        'identity_name',
        'identity_occupation',
        'identity_address',
        'npwp_number',
        'npwp_name',
        'birth_place',
        'birth_date',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'bank_branch',
        'emergency_contact',
        'address',
        'province_id',
        'city_id',
        'subdistrict_id',
        'village_id',
        'postal_code',
        'rt',
        'rw',
        'rt_rw',
        'kyc_rejected_reason',
        'notes',
        'rejection_notes',
        'organizer_community_id',
        'file_path',
        'document_path',
        'path',
        'authorization_letter',
        'responsibility_statement',
        'identity_selfie',
        'bank_proof',
    ];

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'role_context',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Redact sensitive attributes from an array.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function redact(array $attributes, ?string $group = null): array
    {
        $redacted = [];

        $resolvedGroup = $group ?? (isset($attributes['group']) && is_string($attributes['group']) ? $attributes['group'] : null);
        $isSensitiveSettingsGroup = in_array($resolvedGroup, ['payment', 'email'], true);

        foreach ($attributes as $key => $value) {
            if (in_array($key, self::SENSITIVE_ATTRIBUTES, true)) {
                $redacted[$key] = self::REDACTED;
            } elseif ($key === 'value' && $isSensitiveSettingsGroup) {
                $redacted[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $redacted[$key] = self::redact($value, $resolvedGroup);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Filter activity logs by user_id, event, auditable_type, date_from, and date_to.
     *
     * @param  Builder  $query
     * @param  array<string, mixed>|Request  $filters
     * @return Builder
     */
    public function scopeFilter($query, array|Request $filters)
    {
        $filters = $filters instanceof Request ? $filters->all() : $filters;

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }
        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('event', 'like', "%{$search}%")
                    ->orWhere('auditable_type', 'like', "%{$search}%")
                    ->orWhere('auditable_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search): void {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }
}
