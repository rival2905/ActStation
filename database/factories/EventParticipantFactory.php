<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventParticipant>
 */
class EventParticipantFactory extends Factory
{
    protected $model = EventParticipant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'event_category_id' => EventCategory::factory(),
            'registrant_type' => 'community',
            'community_id' => null,
            'user_id' => null,
            'team_name' => fake()->unique()->company().' Team',
            'logo' => null,
            'contact_person' => fake()->name(),
            'contact_phone' => '081234567890',
            'status' => 'registered',
            'registered_at' => now(),
            'notes' => null,
        ];
    }
}
