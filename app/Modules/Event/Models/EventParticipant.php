<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use App\Contracts\Payable;
use App\Models\Concerns\Auditable;
use App\Models\User;
use App\Modules\Event\Observers\EventParticipantObserver;
use Database\Factories\EventParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventParticipant extends Model implements Payable
{
    use Auditable, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'event_category_id',
        'registrant_type',
        'community_id',
        'user_id',
        'team_name',
        'logo',
        'contact_person',
        'contact_phone',
        'status',
        'registered_at',
        'notes',
        'payment_channel',
        'payment_proof',
        'payment_verified_at',
        'payment_verified_by',
        'is_escrow_waived',
        'waiver_signed_at',
        'waiver_ip',
        'waiver_user_agent',
        'revision_notes',
        'waiting_list_order',
        'payment_deadline_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'payment_verified_at' => 'datetime',
            'waiver_signed_at' => 'datetime',
            'payment_deadline_at' => 'datetime',
            'is_escrow_waived' => 'boolean',
            'waiting_list_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        parent::booted();

        static::observe(EventParticipantObserver::class);
    }

    protected static function newFactory(): EventParticipantFactory
    {
        return EventParticipantFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_verified_by');
    }

    public function getPayableType(): string
    {
        return 'event_participant';
    }

    public function getPayableId(): int
    {
        return (int) $this->id;
    }

    public function getAmount(): string
    {
        return (string) ($this->category?->registration_fee ?? '0.00');
    }

    public function getDescription(): string
    {
        $team = $this->team_name;
        $category = $this->category?->name ?? 'Umum';
        $eventTitle = $this->event?->title ?? 'Event';

        return "Biaya Pendaftaran Turnamen: {$eventTitle} - Kategori {$category} (Tim: {$team})";
    }

    public function members(): HasMany
    {
        return $this->hasMany(EventParticipantMember::class);
    }

    public function poolTeams(): HasMany
    {
        return $this->hasMany(PoolTeam::class);
    }

    public function homeGames(): HasMany
    {
        return $this->hasMany(Game::class, 'team_a_id');
    }

    public function awayGames(): HasMany
    {
        return $this->hasMany(Game::class, 'team_b_id');
    }
}
