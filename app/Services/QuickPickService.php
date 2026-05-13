<?php

namespace App\Services;

use App\Enums\PatioQuality;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Collection;

class QuickPickService
{
    /** Days within which a visited restaurant is excluded from the pool. */
    private const int RECENCY_DAYS = 14;

    /** Lower bound (°F) of the patio-boost temperature window. */
    private const float PATIO_BOOST_MIN_F = 65.0;

    /** Upper bound (°F) of the patio-boost temperature window. */
    private const float PATIO_BOOST_MAX_F = 85.0;

    /** Temperature (°F) below which weather_dependent restaurants are penalised. */
    private const float COLD_THRESHOLD_F = 40.0;

    /** Score bonus applied to patio restaurants in ideal weather. */
    private const int PATIO_BOOST_DECENT = 20;

    /** Score bonus applied to destination-patio restaurants in ideal weather. */
    private const int PATIO_BOOST_DESTINATION = 40;

    /** Score penalty applied to weather_dependent restaurants in bad weather. */
    private const int WEATHER_DEPENDENT_PENALTY = 50;

    /**
     * Maximum score gap from the best candidate to still be considered "top".
     * Candidates within this many points of the highest scorer are eligible.
     */
    private const int TOP_CANDIDATE_WINDOW = 10;

    public function __construct(private readonly WeatherService $weather) {}

    /**
     * Pick one restaurant for the given user, or return null if the pool is empty.
     */
    public function pick(User $user, QuickPickFilters $filters = new QuickPickFilters): ?Restaurant
    {
        $pool = $this->buildPool($user, $filters);

        if ($pool->isEmpty()) {
            return null;
        }

        $weather = $this->resolveWeather($filters);
        $scored = $this->scoreAll($pool, $weather);

        return $this->pickFromTop($scored);
    }

    // -------------------------------------------------------------------------
    // Pool building
    // -------------------------------------------------------------------------

    /**
     * Load the candidate restaurants for the user, applying recency exclusion and filters.
     *
     * @return Collection<int, Restaurant>
     */
    private function buildPool(User $user, QuickPickFilters $filters): Collection
    {
        $recentlyVisitedIds = Visit::query()
            ->where('user_id', $user->id)
            ->where('visited_at', '>=', now()->subDays(self::RECENCY_DAYS))
            ->pluck('restaurant_id')
            ->all();

        $allExcludedIds = array_unique(array_merge($recentlyVisitedIds, $filters->excludedIds));

        $query = Restaurant::ownedBy($user)
            ->whereNotIn('id', $allExcludedIds);

        if ($filters->budget_max !== null) {
            $query->where('price_level', '<=', $filters->budget_max);
        }

        if ($filters->time_window !== null) {
            $query->where(function ($q) use ($filters): void {
                $q->whereNull('avg_duration_minutes')
                    ->orWhere('avg_duration_minutes', '<=', $filters->time_window);
            });
        }

        $restaurants = $query->get();

        // Distance filtering is done in PHP using the Haversine formula because
        // SQLite does not have built-in spatial functions.
        if (
            $filters->max_distance_miles !== null
            && $filters->lat !== null
            && $filters->lng !== null
        ) {
            $restaurants = $restaurants->filter(
                fn (Restaurant $r) => $this->distanceMiles(
                    (float) $r->lat,
                    (float) $r->lng,
                    $filters->lat,
                    $filters->lng,
                ) <= $filters->max_distance_miles
            )->values();
        }

        return $restaurants;
    }

    // -------------------------------------------------------------------------
    // Scoring
    // -------------------------------------------------------------------------

    /**
     * Score every restaurant in the pool against the current weather conditions.
     *
     * @param  Collection<int, Restaurant>  $restaurants
     * @return Collection<int, array{restaurant: Restaurant, score: int}>
     */
    private function scoreAll(Collection $restaurants, ?WeatherData $weather): Collection
    {
        return $restaurants->map(function (Restaurant $restaurant) use ($weather): array {
            $score = 100;

            if ($weather !== null) {
                $tempF = $this->toFahrenheit($weather->temperature);
                $isBadWeather = $this->isBadWeather($weather);

                // Boost patio restaurants during ideal outdoor conditions.
                if (! $isBadWeather && $tempF >= self::PATIO_BOOST_MIN_F && $tempF <= self::PATIO_BOOST_MAX_F) {
                    $score += match ($restaurant->patio_quality) {
                        PatioQuality::Destination => self::PATIO_BOOST_DESTINATION,
                        PatioQuality::Decent => self::PATIO_BOOST_DECENT,
                        PatioQuality::None => 0,
                    };
                }

                // Penalise weather-dependent restaurants when conditions are poor.
                if ($isBadWeather || $tempF < self::COLD_THRESHOLD_F) {
                    if (in_array('weather_dependent', $restaurant->vibe_tags ?? [], true)) {
                        $score -= self::WEATHER_DEPENDENT_PENALTY;
                    }
                }
            }

            return ['restaurant' => $restaurant, 'score' => $score];
        });
    }

    /**
     * Randomly select one restaurant from the top-scoring candidates.
     *
     * @param  Collection<int, array{restaurant: Restaurant, score: int}>  $scored
     */
    private function pickFromTop(Collection $scored): ?Restaurant
    {
        $maxScore = $scored->max('score');

        $topCandidates = $scored
            ->filter(fn (array $entry): bool => $entry['score'] >= $maxScore - self::TOP_CANDIDATE_WINDOW)
            ->values();

        /** @var array{restaurant: Restaurant, score: int} $winner */
        $winner = $topCandidates->random();

        return $winner['restaurant'];
    }

    // -------------------------------------------------------------------------
    // Weather helpers
    // -------------------------------------------------------------------------

    private function resolveWeather(QuickPickFilters $filters): ?WeatherData
    {
        if ($filters->lat === null || $filters->lng === null) {
            return null;
        }

        return $this->weather->fetch($filters->lat, $filters->lng);
    }

    /**
     * Returns true when conditions are rainy or precipitation is measurable.
     */
    private function isBadWeather(WeatherData $weather): bool
    {
        return $weather->precipitation > 0
            || str_contains(strtolower($weather->conditions), 'rain');
    }

    /**
     * Convert Celsius to Fahrenheit.
     */
    private function toFahrenheit(float $celsius): float
    {
        return ($celsius * 9.0 / 5.0) + 32.0;
    }

    // -------------------------------------------------------------------------
    // Geometry helpers
    // -------------------------------------------------------------------------

    /**
     * Calculate the great-circle distance in miles between two coordinates
     * using the Haversine formula.
     */
    private function distanceMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMiles = 3_958.8;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadiusMiles * 2.0 * atan2(sqrt($a), sqrt(1.0 - $a));
    }
}
