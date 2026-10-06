<?php

namespace App\Actions;

use App\Enums\QuizQuestion;
use App\Models\Restaurant;
use App\Services\JevService;
use App\Services\QuizAnswers;
use App\Services\WeatherData;
use Illuminate\Support\Collection;

class ScoreQuizFit
{
    private const HUNGER_ANSWERS = ['quick_bite', 'full_meal', 'feast'];

    private const MAX_SCORE = 3;

    public function __construct(public JevService $jev) {}

    /**
     * Ask Jev, in one request, how well each candidate fits the quiz answers and weather.
     *
     * @param  Collection<int, Restaurant>  $restaurants
     * @return array<int, float> Confidence-weighted fit in [0, 1] (fit × Jev confidence) keyed by restaurant id; restaurants without a numeric answer are omitted
     */
    public function execute(QuizAnswers $answers, Collection $restaurants, ?WeatherData $weather): array
    {
        if ($restaurants->isEmpty()) {
            return [];
        }

        $restaurants = $restaurants->sortBy('id')->values();

        $result = $this->jev->ask([
            'answers' => $this->answerState($answers),
            'weather' => $this->weatherState($weather),
            'restaurants' => $restaurants
                ->map(fn (Restaurant $restaurant): array => $this->restaurantState($restaurant))
                ->all(),
        ], $this->questions($restaurants), minConfidence: 0.0);

        $fits = [];

        foreach ($restaurants as $restaurant) {
            $answer = $result["fit_{$restaurant->id}"] ?? null;

            if ($answer !== null && is_numeric($answer['value'])) {
                $fit = min(1.0, max(0.0, (float) $answer['value'] / self::MAX_SCORE));
                $fits[$restaurant->id] = round($fit * $answer['confidence'], 4);
            }
        }

        return $fits;
    }

    /**
     * @return array<string, string>
     */
    private function answerState(QuizAnswers $answers): array
    {
        $state = [];

        if (! QuizQuestion::Energy->shouldSkip($answers)) {
            $state['energy'] = $answers->energy;
        }

        if (in_array($answers->hunger, self::HUNGER_ANSWERS, true)) {
            $state['hunger'] = $answers->hunger;
        }

        if (! QuizQuestion::Familiarity->shouldSkip($answers)) {
            $state['familiarity'] = $answers->familiarity;
        }

        $state['service_level'] = $answers->serviceLevel;

        if ($answers->dineInTakeout !== 'either') {
            $state['dine_in_takeout'] = $answers->dineInTakeout;
        }

        $state['cuisine'] = $answers->cuisine ?? 'surprise me';

        return $state;
    }

    /**
     * @return array{temperature_f: int, conditions: string, precipitation: bool}|null
     */
    private function weatherState(?WeatherData $weather): ?array
    {
        if ($weather === null) {
            return null;
        }

        return [
            'temperature_f' => (int) round($weather->temperature * 9 / 5 + 32),
            'conditions' => $weather->conditions,
            'precipitation' => $weather->precipitation > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function restaurantState(Restaurant $restaurant): array
    {
        return [
            'id' => (int) $restaurant->id,
            'name' => $restaurant->name,
            'primary_cuisine' => $restaurant->primary_cuisine?->value,
            'cuisine_tags' => $restaurant->cuisine_tags ?? [],
            'vibe_tags' => $restaurant->vibe_tags ?? [],
            'service_level' => $restaurant->service_level?->value,
            'patio_quality' => $restaurant->patio_quality->value,
            'avg_duration_minutes' => $restaurant->avg_duration_minutes === null ? null : (int) $restaurant->avg_duration_minutes,
            'visit_count' => (int) $restaurant->visit_count,
        ];
    }

    /**
     * @param  Collection<int, Restaurant>  $restaurants
     * @return array<string, array{type: string, instructions: string, criteria: list<string>}>
     */
    private function questions(Collection $restaurants): array
    {
        $questions = [];

        foreach ($restaurants as $restaurant) {
            $questions["fit_{$restaurant->id}"] = [
                'type' => 'score',
                'instructions' => "How well does the restaurant with id {$restaurant->id} ({$restaurant->name}) fit what this couple wants tonight, given their answers and the weather?",
                'criteria' => ['Poor fit', 'Okay fit', 'Good fit', 'Great fit'],
            ];
        }

        return $questions;
    }
}
