<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use App\Models\Concerns\Auditable;
use App\Models\User;
use App\Modules\Community\Models\Document;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\MasterData\Models\Venue;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sport_category_id',
        'venue_id',
        'user_id',
        'title',
        'slug',
        'description',
        'banner_image',
        'status',
        'registration_open',
        'max_participants',
        'starts_at',
        'ends_at',
        'registration_deadline',
        'cancel_reason',
        'venue_approval_doc',
        'kyc_notes',
        'payout_status',
        'allow_manual_payment',
        'manual_bank_name',
        'manual_bank_account_number',
        'manual_bank_account_holder',
        'manual_payment_instructions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registration_open' => 'boolean',
            'allow_manual_payment' => 'boolean',
            'max_participants' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_deadline' => 'datetime',
        ];
    }

    protected static function newFactory(): EventFactory
    {
        return EventFactory::new();
    }

    public function registrationPriceLabel(): string
    {
        $categories = $this->relationLoaded('categories')
            ? $this->categories
            : $this->categories()->get(['registration_fee']);

        if ($categories->isEmpty()) {
            return 'Belum tersedia';
        }

        $minimum = null;
        $maximum = null;

        foreach ($categories as $category) {
            $fee = (float) $category->registration_fee;
            $minimum = $minimum === null ? $fee : min($minimum, $fee);
            $maximum = $maximum === null ? $fee : max($maximum, $fee);
        }

        if ($minimum === $maximum) {
            return $minimum === 0.0 ? 'Gratis' : 'Rp '.number_format($minimum, 0, ',', '.');
        }

        return $minimum === 0.0
            ? 'Mulai Gratis'
            : 'Mulai Rp '.number_format($minimum, 0, ',', '.');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sportCategory(): BelongsTo
    {
        return $this->belongsTo(SportCategory::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(EventPayout::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(EventCategory::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function tournamentPhases(): HasMany
    {
        return $this->hasMany(TournamentPhase::class);
    }

    public function phases(): HasMany
    {
        return $this->tournamentPhases();
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(EventSchedule::class);
    }

    public function eventCourts(): HasMany
    {
        return $this->hasMany(EventCourt::class);
    }

    public function scheduleSetting(): HasOne
    {
        return $this->hasOne(EventScheduleSetting::class);
    }

    public function scheduleBreaks(): HasMany
    {
        return $this->hasMany(EventScheduleBreak::class);
    }

    public function breaks(): HasMany
    {
        return $this->scheduleBreaks();
    }
}
