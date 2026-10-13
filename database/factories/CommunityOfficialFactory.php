<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityOfficial>
 */
class CommunityOfficialFactory extends Factory
{
    protected $model = CommunityOfficial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'user_id' => User::factory(),
            'position' => 'Ketua',
            'status' => 'active',
            'started_at' => now()->toDateString(),
            'ended_at' => null,
        ];
    }
}
