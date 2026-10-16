<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\EmailDelivery;
use App\Models\Concerns\Auditable;
use App\Modules\Community\Models\Document;
use App\Modules\MasterData\Models\Wilayah;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'phone_normalized',
        'identity_number',
        'identity_name',
        'identity_occupation',
        'identity_address',
        'npwp_number',
        'npwp_name',
        'identity_image',
        'birth_certificate',
        'birth_place',
        'birth_date',
        'gender',
        'image',
        'external_ref',
        'email_verified_at',
        'password',
        'is_active',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'bank_branch',
        'province_id',
        'city_id',
        'subdistrict_id',
        'village_id',
        'postal_code',
        'address',
        'rt',
        'rw',
        'emergency_contact',
        'kyc_status',
        'kyc_rejected_reason',
        'organizer_community_id',
        'kyc_submitted_at',
        'kyc_reviewed_at',
        'kyc_reviewed_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'identity_number',
        'identity_image',
        'birth_certificate',
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
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'is_active' => 'boolean',
            'kyc_submitted_at' => 'datetime',
            'kyc_reviewed_at' => 'datetime',
            'organizer_community_id' => 'integer',
            'kyc_reviewed_by' => 'integer',
        ];
    }

    public function educations(): HasMany
    {
        return $this->hasMany(UserEducation::class);
    }

    protected static function booted(): void
    {
        parent::booted();

        static::updating(function (User $user): void {
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
        });
    }

    public function sendEmailVerificationNotification(): void
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $this->getKey(),
                'hash' => sha1((string) $this->getEmailForVerification()),
            ]
        );

        $payload = [
            'recipient_user_id' => $this->getKey(),
            'recipient_email' => (string) $this->getEmailForVerification(),
            'recipient_name' => $this->name,
            'subject' => 'Verifikasi Alamat Email',
            'body' => "Halo {$this->name},\n\nSilakan klik tautan di bawah ini untuk memverifikasi alamat email akun ActStation Anda. Tautan ini berlaku selama 60 menit:\n{$verificationUrl}",
            'action_url' => $verificationUrl,
            'action_label' => 'Verifikasi Email',
            'type' => 'verification',
            'attachments' => [],
        ];

        if (app()->bound(EmailDelivery::class)) {
            app(EmailDelivery::class)->send($payload);
        }
    }

    public function isInternal(): bool
    {
        return $this->roles->contains(
            fn ($role) => in_array($role->panel_access, ['internal', 'both'], true)
        );
    }

    public function kycReviewedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'kyc_reviewed_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'province_id', 'kode');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'city_id', 'kode');
    }

    /**
     * Filter users by search, role, and is_active.
     *
     * @param  Builder  $query
     * @param  array<string, mixed>|Request  $filters
     * @return Builder
     */
    public function scopeFilter($query, array|Request $filters)
    {
        $filters = $filters instanceof Request ? $filters->all() : $filters;

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['role'])) {
            $role = (string) $filters['role'];
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query;
    }

    /**
     * Determine the default home/dashboard panel route based on user roles and permissions.
     */
    public function getPanelHomeRoute(): string
    {
        $hasInternalRole = $this->roles->contains(
            fn ($role) => in_array($role->panel_access, ['internal', 'both'], true)
        );

        if ($hasInternalRole) {
            if (! $this->can('dashboard.view')) {
                if ($this->can('region-oversight.view')) {
                    return route('admin.region-oversight.athletes.index');
                }
                if ($this->can('community.view')) {
                    return route('admin.communities.index');
                }
                if ($this->can('athlete.view')) {
                    return route('admin.athletes.index');
                }
            }

            return route('admin.dashboard');
        }

        if ($this->hasRole('organizer')) {
            return route('panel.organizer.events.index');
        }

        if ($this->hasRole('athlete') && ! $this->hasRole('community_manager')) {
            return route('panel.my-requests.index');
        }

        $hasActiveCommunity = DB::table('community_officials')
            ->where('user_id', $this->id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->exists();

        if ($hasActiveCommunity) {
            return route('panel.profile.show');
        }

        return route('panel.community.create');
    }
}
