<?php

namespace Database\Factories;

use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Enums\PrimaryCuisine;
use App\Enums\RestaurantSource;
use App\Enums\ServiceLevel;
use App\Enums\ServiceOption;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $serviceLevel = fake()->randomElement(ServiceLevel::cases());

        return [
            'owner_user_id' => User::factory(),
            'name' => fake()->company().' Restaurant',
            'address' => fake()->streetAddress().', Des Moines, IA',
            'source' => RestaurantSource::Favorite,
            'cuisine_tags' => [fake()->randomElement(['Italian', 'Mexican', 'American', 'Thai', 'Japanese'])],
            'vibe_tags' => [fake()->randomElement(Arr::flatten(config('vibes')))],
            'price_level' => fake()->numberBetween(1, 4),
            'patio_quality' => PatioQuality::None,
            'indoor_vibe_when_cold' => IndoorVibe::Neutral,
            'avg_duration_minutes' => fake()->randomElement([45, 60, 75, 90, 120]),
            'lat' => 41.58 + (fake()->randomFloat(4, -0.05, 0.05)),
            'lng' => -93.62 + (fake()->randomFloat(4, -0.05, 0.05)),
            'last_visited_at' => null,
            'visit_count' => 0,
            'service_level' => $serviceLevel,
            'service_options' => $this->serviceOptionsFor($serviceLevel),
            'primary_cuisine' => fake()->randomElement(PrimaryCuisine::cases()),
        ];
    }

    /**
     * Force a specific service level, regenerating service options to match.
     */
    public function withServiceLevel(ServiceLevel $serviceLevel): static
    {
        return $this->state(fn (): array => [
            'service_level' => $serviceLevel,
            'service_options' => $this->serviceOptionsFor($serviceLevel),
        ]);
    }

    /**
     * Generate a realistic set of service options for the given service level.
     *
     * @return array<int, string>
     */
    private function serviceOptionsFor(ServiceLevel $serviceLevel): array
    {
        $options = match ($serviceLevel) {
            ServiceLevel::FastFood => array_filter([
                ServiceOption::Takeout,
                fake()->boolean(70) ? ServiceOption::DriveThru : null,
                fake()->boolean(50) ? ServiceOption::Delivery : null,
                fake()->boolean(50) ? ServiceOption::DineIn : null,
            ]),
            ServiceLevel::FastCasual, ServiceLevel::Casual => array_filter([
                ServiceOption::DineIn,
                fake()->boolean(70) ? ServiceOption::Takeout : null,
                fake()->boolean(40) ? ServiceOption::Delivery : null,
                fake()->boolean(30) ? ServiceOption::Curbside : null,
            ]),
            ServiceLevel::UpscaleCasual => array_filter([
                ServiceOption::DineIn,
                fake()->boolean(30) ? ServiceOption::Takeout : null,
            ]),
            ServiceLevel::FineDining => [ServiceOption::DineIn],
        };

        return array_values(array_map(fn (ServiceOption $option): string => $option->value, $options));
    }
}
