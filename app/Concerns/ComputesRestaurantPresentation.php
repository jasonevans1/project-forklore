<?php

namespace App\Concerns;

use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Models\Restaurant;
use App\Services\WeatherService;

/**
 * Shared weather-aware tagline and distance-label logic for restaurant result cards.
 *
 * Classes using this trait must declare the following properties:
 *   - ?float $lat
 *   - ?float $lng
 */
trait ComputesRestaurantPresentation
{
    /**
     * Generate a weather-aware tagline for the result card.
     */
    private function resolveTagline(Restaurant $restaurant): string
    {
        $weather = ($this->lat !== null && $this->lng !== null)
            ? app(WeatherService::class)->fetch($this->lat, $this->lng)
            : null;

        if ($weather !== null) {
            $tempF = ($weather->temperature * 9.0 / 5.0) + 32.0;
            $isRaining = $weather->precipitation > 0
                || str_contains(strtolower($weather->conditions), 'rain');
            $isCold = $tempF < 40.0;
            $isIdealPatio = ! $isRaining && $tempF >= 65.0 && $tempF <= 85.0;

            if ($isIdealPatio && $restaurant->patio_quality !== PatioQuality::None) {
                return __('Perfect patio weather tonight 🌿');
            }

            if ($isRaining && $restaurant->indoor_vibe_when_cold === IndoorVibe::Cozy) {
                return __('Cozy escape on a rainy night ☔');
            }

            if ($isCold && $restaurant->indoor_vibe_when_cold === IndoorVibe::Cozy) {
                return __('Warm and cozy on a cold night ❄️');
            }
        }

        return match ($restaurant->patio_quality) {
            PatioQuality::Destination => __('Amazing patio spot 🌞'),
            PatioQuality::Decent => __('Great patio vibes 🌤️'),
            default => __('Your pick for tonight ✨'),
        };
    }

    /**
     * Return a human-readable distance label if both the user and restaurant have coordinates.
     */
    private function resolveDistanceLabel(Restaurant $restaurant): ?string
    {
        if ($this->lat === null || $this->lng === null) {
            return null;
        }

        if ($restaurant->lat === null || $restaurant->lng === null) {
            return null;
        }

        $miles = $this->haversineMiles(
            (float) $restaurant->lat,
            (float) $restaurant->lng,
            $this->lat,
            $this->lng,
        );

        return number_format($miles, 1).' mi';
    }

    /**
     * Calculate the great-circle distance in miles between two coordinates.
     */
    private function haversineMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 3_958.8;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2.0 * atan2(sqrt($a), sqrt(1.0 - $a));
    }
}
