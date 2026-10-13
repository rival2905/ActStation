<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityCoach;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityCoach>
 */
class CommunityCoachFactory extends Factory
{
    protected $model = CommunityCoach::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'user_id' => User::factory(),
            'development_level_id' => null,
            'gender_scope' => 'all',
            'license' => 'Lisensi C',
            'status' => 'active',
            'started_at' => now()->toDateString(),
            'ended_at' => null,
        ];
    }
}
