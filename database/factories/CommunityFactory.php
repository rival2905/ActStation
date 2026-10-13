<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Community\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Community>
 */
class CommunityFactory extends Factory
{
    protected $model = Community::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'subtype' => 'club',
            'province_id' => '32',
            'city_id' => '3273',
            'status' => 'draft',
            'is_active' => true,
        ];
    }
}
