<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityAthlete>
 */
class CommunityAthleteFactory extends Factory
{
    protected $model = CommunityAthlete::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'user_id' => User::factory(),
            'development_level_id' => null,
            'jersey_number' => fake()->numberBetween(1, 99),
            'position' => 'Point Guard',
            'initiated_by' => 'community',
            'status' => 'active',
            'region_status' => 'pending',
            'joined_at' => now()->toDateString(),
            'left_at' => null,
        ];
    }
}
