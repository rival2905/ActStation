<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use App\Models\Concerns\Auditable;
use App\Modules\MasterData\Models\DevelopmentLevel;
use Database\Factories\EventCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCategory extends Model
{
    use Auditable, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'name',
        'slug',
        'development_level_id',
        'min_age',
        'max_age',
        'registration_fee',
        'registration_start',
        'registration_end',
        'payment_start',
        'payment_end',
        'gender',
        'min_roster',
        'max_roster',
        'max_teams',
        'is_roster_public',
        'match_type',
        'registration_flow',
        'enable_waiting_list',
        'payment_time_limit_hours',
        'allow_club',
        'allow_community',
        'community_subtype',
        'required_documents',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_age' => 'integer',
            'max_age' => 'integer',
            'registration_fee' => 'decimal:2',
            'registration_start' => 'datetime',
            'registration_end' => 'datetime',
            'payment_start' => 'datetime',
            'payment_end' => 'datetime',
            'min_roster' => 'integer',
            'max_roster' => 'integer',
            'max_teams' => 'integer',
            'is_roster_public' => 'boolean',
            'enable_waiting_list' => 'boolean',
            'payment_time_limit_hours' => 'integer',
            'allow_club' => 'boolean',
            'allow_community' => 'boolean',
            'required_documents' => 'array',
        ];
    }

    protected static function newFactory(): EventCategoryFactory
    {
        return EventCategoryFactory::new();
    }

    public function registrationPriceLabel(): string
    {
        $fee = (float) $this->registration_fee;

        return $fee === 0.0 ? 'Gratis' : 'Rp '.number_format($fee, 0, ',', '.');
    }

    public function ageRangeLabel(): string
    {
        if ($this->min_age === null && $this->max_age === null) {
            return 'Semua usia';
        }

        if ($this->min_age === null) {
            return 'Maks. '.$this->max_age.' tahun';
        }

        if ($this->max_age === null) {
            return 'Min. '.$this->min_age.' tahun';
        }

        return $this->min_age.'–'.$this->max_age.' tahun';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function developmentLevel(): BelongsTo
    {
        return $this->belongsTo(DevelopmentLevel::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function tournamentPhases(): HasMany
    {
        return $this->hasMany(TournamentPhase::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function bracketNodes(): HasMany
    {
        return $this->hasMany(BracketNode::class);
    }
}
