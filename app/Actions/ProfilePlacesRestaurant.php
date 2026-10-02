<?php

namespace App\Actions;

use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Enums\PrimaryCuisine;
use App\Enums\RestaurantSource;
use App\Enums\ServiceLevel;
use App\Models\Restaurant;
use App\Services\JevService;
use BackedEnum;
use Illuminate\Support\Facades\Cache;

class ProfilePlacesRestaurant
{
    private const WEATHER_DEPENDENT_THRESHOLD = 0.5;

    public function __construct(public JevService $jev) {}

    /**
     * Ask Jev about a Places restaurant and write each confident answer to its column.
     * Fields without a usable answer keep their current value.
     */
    public function execute(Restaurant $restaurant): void
    {
        $restaurant->refresh();

        if ($restaurant->source !== RestaurantSource::Places || $restaurant->profiled_at !== null) {
            return;
        }

        $lockKey = "jev_profiling:{$restaurant->id}";

        if (! Cache::add($lockKey, true, 60)) {
            return;
        }

        try {
            $this->profile($restaurant);
        } finally {
            Cache::forget($lockKey);
        }
    }

    private function profile(Restaurant $restaurant): void
    {
        $answers = $this->jev->ask([
            'name' => $restaurant->name,
            'address' => $restaurant->address,
            'cuisine_tags' => $restaurant->cuisine_tags ?? [],
            'price_level' => $restaurant->price_level,
        ], $this->questions());

        if ($answers === null) {
            return;
        }

        $updates = ['profiled_at' => now()];

        foreach ($this->enumColumns() as $id => $enum) {
            $value = $enum::tryFrom((string) ($answers[$id]['value'] ?? ''));

            if ($value !== null) {
                $updates[$id] = $value;
            }
        }

        $weather = $answers['weather_dependent']['value'] ?? null;
        $tags = $restaurant->vibe_tags ?? [];

        if ($weather !== null && (float) $weather >= self::WEATHER_DEPENDENT_THRESHOLD && ! in_array('weather_dependent', $tags, true)) {
            $updates['vibe_tags'] = [...$tags, 'weather_dependent'];
        }

        $restaurant->update($updates);
    }

    /**
     * @return array<string, class-string<BackedEnum>>
     */
    private function enumColumns(): array
    {
        return [
            'primary_cuisine' => PrimaryCuisine::class,
            'patio_quality' => PatioQuality::class,
            'indoor_vibe_when_cold' => IndoorVibe::class,
            'service_level' => ServiceLevel::class,
        ];
    }

    /**
     * @return array<string, array{type: string, instructions: string, criteria?: array<int|string, string|null>}>
     */
    private function questions(): array
    {
        return [
            'primary_cuisine' => [
                'type' => 'choice',
                'instructions' => 'Which cuisine best describes this restaurant?',
                'criteria' => $this->labels(PrimaryCuisine::cases()),
            ],
            'patio_quality' => [
                'type' => 'choice',
                'instructions' => 'How good is the outdoor seating at this restaurant?',
                'criteria' => [
                    'none' => 'No outdoor seating',
                    'decent' => 'Some outdoor tables',
                    'destination' => 'A patio people go there for',
                ],
            ],
            'indoor_vibe_when_cold' => [
                'type' => 'choice',
                'instructions' => 'How does the indoor atmosphere feel on a cold day?',
                'criteria' => ['cozy' => 'Warm and cozy', 'neutral' => 'Neither cozy nor cold', 'sterile' => 'Bright, cold or impersonal'],
            ],
            'service_level' => [
                'type' => 'choice',
                'instructions' => 'What level of service does this restaurant offer?',
                'criteria' => $this->labels(ServiceLevel::cases()),
            ],
            'weather_dependent' => [
                'type' => 'noul',
                'instructions' => 'Is this place mainly outdoors or seasonal (food truck, rooftop, beer garden, patio-only), so bad weather makes it a poor choice?',
            ],
        ];
    }

    /**
     * @param  array<int, PrimaryCuisine|ServiceLevel>  $cases
     * @return array<string, string>
     */
    private function labels(array $cases): array
    {
        return array_column(
            array_map(fn (PrimaryCuisine|ServiceLevel $case): array => [$case->value, $case->label()], $cases),
            1,
            0,
        );
    }
}
