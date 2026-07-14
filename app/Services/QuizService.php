<?php

namespace App\Services;

use App\Enums\PatioQuality;
use App\Enums\ServiceLevel;
use App\Models\HouseholdState;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Collection;

class QuizService
{
    // -------------------------------------------------------------------------
    // Scoring weights
    // -------------------------------------------------------------------------

    /** Bonus when a restaurant's vibe_tags match the energy answer. */
    private const int ENERGY_MATCH_BONUS = 30;

    /** Bonus/penalty scaling for avg_duration_minutes vs hunger answer. */
    private const int HUNGER_MATCH_BONUS = 25;

    /** Bonus when familiarity=familiar and restaurant has high visit_count. */
    private const int FAMILIAR_BONUS = 30;

    /** Bonus when familiarity=new and restaurant has zero visits. */
    private const int NEW_BONUS = 30;

    /** Penalty when familiarity=new and restaurant has been visited before. */
    private const int VISITED_PENALTY = 20;

    /** Bonus for destination-patio restaurants in ideal weather. */
    private const int PATIO_DESTINATION_BONUS = 40;

    /** Bonus for decent-patio restaurants in ideal weather. */
    private const int PATIO_DECENT_BONUS = 20;

    /** Penalty for weather_dependent restaurants in bad weather. */
    private const int WEATHER_DEPENDENT_PENALTY = 50;

    /** Bonus applied when a restaurant matches the partner's preferred vibe tags. */
    private const int PARTNER_PREF_BOOST = 25;

    /** Ideal patio lower bound (°F). */
    private const float PATIO_BOOST_MIN_F = 65.0;

    /** Ideal patio upper bound (°F). */
    private const float PATIO_BOOST_MAX_F = 85.0;

    /**
     * Distance bucket boundaries in miles, keyed by the QuizAnswers::$distance value.
     * Each range is (min, max] except the first bucket, which starts at 0.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private const array DISTANCE_BUCKETS = [
        'under_2_miles' => [0.0, 2.0],
        '2_to_5_miles' => [2.0, 5.0],
        '5_to_15_miles' => [5.0, 15.0],
    ];

    public function __construct(
        private readonly WeatherService $weather,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Return the highest-scoring favorite restaurant for the given answers.
     * Returns null when the user has no eligible favorites.
     */
    public function topMatch(User $user, QuizAnswers $answers, ?WeatherData $weather = null): ?Restaurant
    {
        $pool = $this->buildPool($user, $answers);

        if ($pool->isEmpty()) {
            return null;
        }

        $resolvedWeather = $weather ?? $this->resolveWeather($answers);
        $scored = $this->scoreAll($pool, $answers, $resolvedWeather, $user);

        return $scored->sortByDesc('score')->first()['restaurant'];
    }

    /**
     * Return the second-best match, excluding the given winner.
     * Returns null when no other favorites are eligible.
     */
    public function runnerUp(User $user, QuizAnswers $answers, Restaurant $winner, ?WeatherData $weather = null): ?Restaurant
    {
        $pool = $this->buildPool($user, $answers)->filter(
            fn (Restaurant $r) => $r->id !== $winner->id
        )->values();

        if ($pool->isEmpty()) {
            return null;
        }

        $resolvedWeather = $weather ?? $this->resolveWeather($answers);
        $scored = $this->scoreAll($pool, $answers, $resolvedWeather, $user);

        return $scored->sortByDesc('score')->first()['restaurant'];
    }

    // -------------------------------------------------------------------------
    // Pool building
    // -------------------------------------------------------------------------

    /**
     * Load user favorites and apply hard filters from the quiz answers
     * (service level, dine-in/takeout, and distance).
     *
     * @return Collection<int, Restaurant>
     */
    private function buildPool(User $user, QuizAnswers $answers): Collection
    {
        $query = Restaurant::ownedBy($user)->favorites();

        match ($answers->serviceLevel) {
            'quick_easy' => $query->whereIn('service_level', [ServiceLevel::FastFood->value, ServiceLevel::FastCasual->value]),
            'casual_sit_down' => $query->where('service_level', ServiceLevel::Casual->value),
            'nicer_night_out' => $query->where('service_level', ServiceLevel::UpscaleCasual->value),
            'special_occasion' => $query->where('service_level', ServiceLevel::FineDining->value),
            default => null,
        };

        if ($answers->cuisine !== null) {
            $query->where('primary_cuisine', $answers->cuisine);
        }

        $restaurants = $query->get();

        // Apply dine-in/takeout filter in PHP — service_options is a JSON array column,
        // and SQLite (the default/test connection) does not support whereJsonContains.
        if ($answers->dineInTakeout !== 'either') {
            $restaurants = $restaurants->filter(
                fn (Restaurant $r) => in_array($answers->dineInTakeout, $r->service_options ?? [], true)
            )->values();
        }

        // Apply distance filter when the user chose a bucket and coordinates are available.
        if (isset(self::DISTANCE_BUCKETS[$answers->distance]) && $answers->lat !== null && $answers->lng !== null) {
            [$minMiles, $maxMiles] = self::DISTANCE_BUCKETS[$answers->distance];

            $restaurants = $restaurants->filter(function (Restaurant $r) use ($minMiles, $maxMiles, $answers): bool {
                if ($r->lat === null || $r->lng === null) {
                    return false;
                }

                $distance = $this->distanceMiles((float) $r->lat, (float) $r->lng, $answers->lat, $answers->lng);
                $passesMin = $minMiles === 0.0 ? $distance >= $minMiles : $distance > $minMiles;

                return $passesMin && $distance <= $maxMiles;
            })->values();
        }

        return $restaurants;
    }

    // -------------------------------------------------------------------------
    // Scoring
    // -------------------------------------------------------------------------

    /**
     * Score every restaurant in the pool against the quiz answers and weather.
     *
     * @param  Collection<int, Restaurant>  $restaurants
     * @return Collection<int, array{restaurant: Restaurant, score: int}>
     */
    private function scoreAll(Collection $restaurants, QuizAnswers $answers, ?WeatherData $weather, User $user): Collection
    {
        $partnerTags = $this->resolvePartnerPreferredTags($user);

        return $restaurants->map(function (Restaurant $r) use ($answers, $weather, $partnerTags): array {
            $score = 100;

            // Partner turn bias.
            if (! empty($partnerTags)) {
                if (! empty(array_intersect($partnerTags, $r->vibe_tags ?? []))) {
                    $score += self::PARTNER_PREF_BOOST;
                }
            }

            // Energy
            if (in_array($answers->energy, $r->vibe_tags ?? [], true)) {
                $score += self::ENERGY_MATCH_BONUS;
            }

            // Hunger — ideal avg_duration_minutes per answer: quick_bite=45, full_meal=75, feast=120
            $idealDuration = match ($answers->hunger) {
                'quick_bite' => 45,
                'feast' => 120,
                default => 75,
            };

            if ($r->avg_duration_minutes !== null) {
                $gap = abs($r->avg_duration_minutes - $idealDuration);
                $score += self::HUNGER_MATCH_BONUS - intdiv($gap * 2, 5);
            }

            // Familiarity
            $score += match ($answers->familiarity) {
                'new' => $r->visit_count === 0 ? self::NEW_BONUS : -self::VISITED_PENALTY,
                'familiar' => (int) min($r->visit_count, 1) * self::FAMILIAR_BONUS,
                default => 0,
            };

            // Weather
            if ($weather !== null) {
                $tempF = ($weather->temperature * 9.0 / 5.0) + 32.0;
                $isBad = $weather->precipitation > 0
                    || str_contains(strtolower($weather->conditions), 'rain');
                $isIdealPatio = ! $isBad
                    && $tempF >= self::PATIO_BOOST_MIN_F
                    && $tempF <= self::PATIO_BOOST_MAX_F;

                if ($isIdealPatio) {
                    $score += match ($r->patio_quality) {
                        PatioQuality::Destination => self::PATIO_DESTINATION_BONUS,
                        PatioQuality::Decent => self::PATIO_DECENT_BONUS,
                        PatioQuality::None => 0,
                    };
                }

                if ($isBad && in_array('weather_dependent', $r->vibe_tags ?? [], true)) {
                    $score -= self::WEATHER_DEPENDENT_PENALTY;
                }
            }

            return ['restaurant' => $r, 'score' => $score];
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return list<string>
     */
    private function resolvePartnerPreferredTags(User $user): array
    {
        if (! HouseholdState::isPartnersTurn($user)) {
            return [];
        }

        return $user->partner?->preferred_vibe_tags ?? [];
    }

    private function resolveWeather(QuizAnswers $answers): ?WeatherData
    {
        if ($answers->lat === null || $answers->lng === null) {
            return null;
        }

        return $this->weather->fetch($answers->lat, $answers->lng);
    }

    private function distanceMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 3_958.8;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2.0 * atan2(sqrt($a), sqrt(1.0 - $a));
    }
}
