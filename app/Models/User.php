<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\Auditable;
use App\Modules\Community\Models\Document;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
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
        'emergency_contact',
        'kyc_status',
        'kyc_rejected_reason',
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
        ];
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
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

        return route('panel.profile.show');
    }
}
