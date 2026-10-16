<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventCategory>
 */
class EventCategoryFactory extends Factory
{
    protected $model = EventCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'event_id' => Event::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'development_level_id' => null,
            'min_age' => null,
            'max_age' => null,
            'registration_fee' => 0,
            'registration_flow' => 'instant_free',
            'payment_time_limit_hours' => null,
            'gender' => 'mixed',
            'min_roster' => 5,
            'max_roster' => 12,
            'max_teams' => 16,
            'max_teams_per_entity' => 1,
        ];
    }
}
