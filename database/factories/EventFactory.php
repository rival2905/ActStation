<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'sport_category_id' => SportCategory::query()->firstOrCreate(
                ['slug' => 'basketball'],
                ['name' => 'Basketball', 'is_active' => true]
            )->id,
            'venue_id' => null,
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->randomNumber(4),
            'description' => fake()->paragraph(),
            'status' => 'draft',
            'registration_open' => false,
            'max_participants' => 16,
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(9),
            'registration_deadline' => now()->addDays(5),
        ];
    }
}
