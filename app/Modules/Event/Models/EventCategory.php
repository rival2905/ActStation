<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use App\Contracts\EntityRequirements;
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
        'allow_individual',
        'community_subtype',
        'allowed_community_subtypes',
        'allowed_institution_types',
        'required_documents',
        'required_entity_documents',
        'document_instructions',
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
            'allow_individual' => 'boolean',
            'allowed_community_subtypes' => 'array',
            'allowed_institution_types' => 'array',
            'required_documents' => 'array',
            'required_entity_documents' => 'array',
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

    public function acceptsCommunityType(?string $subtype, ?string $institutionType = null, ?string $schoolLevel = null): bool
    {
        if ($subtype === 'club') {
            return (bool) $this->allow_club;
        }

        if (! $this->allow_community || $subtype === null) {
            return false;
        }

        if ($subtype === 'school' || str_starts_with($subtype, 'school_')) {
            return $this->acceptsSchoolCommunity($subtype, $schoolLevel);
        }

        if ($this->allowed_community_subtypes === null) {
            if ($this->community_subtype !== null && $this->community_subtype !== $subtype) {
                return false;
            }
        } else {
            if (! in_array($subtype, $this->allowed_community_subtypes, true)) {
                return false;
            }
        }

        if ($subtype === 'institution') {
            if (! empty($this->allowed_institution_types)) {
                if ($institutionType === null || $institutionType === '') {
                    return false;
                }

                if (! in_array($institutionType, $this->allowed_institution_types, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function acceptsSchoolCommunity(string $subtype, ?string $schoolLevel): bool
    {
        $token = $this->resolveEffectiveSchoolToken($subtype, $schoolLevel);

        if ($this->allowed_community_subtypes === null) {
            if ($this->community_subtype === null || $this->community_subtype === 'school') {
                return true;
            }

            if ($token !== null && ($this->community_subtype === $token)) {
                return true;
            }

            return false;
        }

        if (in_array('school', $this->allowed_community_subtypes, true)) {
            return true;
        }

        if ($token === null) {
            return false;
        }

        if (in_array($token, $this->allowed_community_subtypes, true)) {
            return true;
        }

        return false;
    }

    private function resolveEffectiveSchoolToken(string $subtype, ?string $schoolLevel): ?string
    {
        if ($schoolLevel !== null && trim($schoolLevel) !== '') {
            $raw = strtolower(trim($schoolLevel));

            return match ($raw) {
                'sd', 'mi' => 'school_sd',
                'smp', 'mts' => 'school_smp',
                'sma', 'smk', 'ma' => 'school_sma',
                default => null,
            };
        }

        if (str_starts_with($subtype, 'school_')) {
            return match ($subtype) {
                'school_sd' => 'school_sd',
                'school_smp' => 'school_smp',
                'school_sma' => 'school_sma',
                default => null,
            };
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function allowedEntityLabels(): array
    {
        $labels = [];

        if ($this->allow_club) {
            $labels[] = 'Klub';
        }

        if ($this->allow_community) {
            $categorySubtypeLabels = EntityRequirements::CATEGORY_SUBTYPES;
            $subtypeLabels = EntityRequirements::SUBTYPES;
            $institutionLabels = EntityRequirements::INSTITUTION_TYPES;

            if ($this->allowed_community_subtypes === null) {
                if ($this->community_subtype !== null) {
                    if ($this->community_subtype === 'institution' && ! empty($this->allowed_institution_types)) {
                        $instNames = array_map(fn ($it) => $institutionLabels[$it] ?? ucfirst($it), $this->allowed_institution_types);
                        $labels[] = 'Instansi ('.implode(', ', $instNames).')';
                    } elseif ($this->community_subtype === 'school') {
                        $labels[] = 'Sekolah (Semua Jenjang; aturan lama)';
                    } else {
                        $labels[] = $categorySubtypeLabels[$this->community_subtype] ?? ($subtypeLabels[$this->community_subtype] ?? ucfirst(str_replace('_', ' ', $this->community_subtype)));
                    }
                } else {
                    $labels[] = 'Komunitas (Semua Tipe)';
                }
            } else {
                foreach ($this->allowed_community_subtypes as $st) {
                    if ($st === 'institution') {
                        if (! empty($this->allowed_institution_types)) {
                            $instNames = array_map(fn ($it) => $institutionLabels[$it] ?? ucfirst($it), $this->allowed_institution_types);
                            $labels[] = 'Instansi ('.implode(', ', $instNames).')';
                        } else {
                            $labels[] = $categorySubtypeLabels['institution'] ?? ($subtypeLabels['institution'] ?? 'Instansi / Lembaga');
                        }
                    } elseif ($st === 'school') {
                        $labels[] = 'Sekolah (Semua Jenjang; aturan lama)';
                    } else {
                        $labels[] = $categorySubtypeLabels[$st] ?? ($subtypeLabels[$st] ?? ucfirst(str_replace('_', ' ', $st)));
                    }
                }
            }
        }

        if ($this->allow_individual) {
            $labels[] = 'Atlet Mandiri';
        }

        return $labels;
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
