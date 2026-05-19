<?php

namespace Database\Factories;

use App\Enums\EventRecurrence;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->numberBetween(16, 21);

        return [
            'restaurant_id' => Restaurant::factory(),
            'owner_user_id' => User::factory(),
            'type' => fake()->randomElement(EventType::cases()),
            'recurrence' => EventRecurrence::Weekly,
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => sprintf('%02d:00:00', $start),
            'end_time' => sprintf('%02d:00:00', $start + 2),
            'specific_date' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'active' => true,
            'shared' => false,
        ];
    }

    /**
     * One-off event on a specific date.
     */
    public function oneOff(string $date): static
    {
        return $this->state([
            'recurrence' => EventRecurrence::OneOff,
            'day_of_week' => null,
            'specific_date' => $date,
        ]);
    }

    /**
     * Mark the event as inactive.
     */
    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
