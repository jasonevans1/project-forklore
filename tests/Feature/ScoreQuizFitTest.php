<?php

use App\Actions\ScoreQuizFit;
use App\Enums\PrimaryCuisine;
use App\Models\Restaurant;
use App\Services\JevService;
use App\Services\QuizAnswers;
use App\Services\WeatherData;
use Carbon\CarbonImmutable;

function quizFitWeather(): WeatherData
{
    return new WeatherData(20.0, 'Clear', 0.0, 3.0, CarbonImmutable::now(), 'metric');
}

it('sends one Jev request with the quiz answers weather and candidates as state', function () {
    $a = Restaurant::factory()->create(['cuisine_tags' => [], 'vibe_tags' => []]);
    $b = Restaurant::factory()->create(['visit_count' => 2, 'primary_cuisine' => PrimaryCuisine::Thai]);

    $this->mock(JevService::class)
        ->shouldReceive('ask')
        ->once()
        ->withArgs(function (mixed $state) use ($a, $b): bool {
            return array_keys($state) === ['answers', 'weather', 'restaurants']
                && $state['answers'] === [
                    'energy' => 'lively',
                    'hunger' => 'feast',
                    'familiarity' => 'new',
                    'service_level' => 'casual_sit_down',
                    'dine_in_takeout' => 'dine_in',
                    'cuisine' => 'surprise me',
                ]
                && $state['weather'] === ['temperature_f' => 68, 'conditions' => 'Clear', 'precipitation' => false]
                && array_column($state['restaurants'], 'id') === [$a->id, $b->id]
                && $state['restaurants'][0]['cuisine_tags'] === []
                && $state['restaurants'][0]['vibe_tags'] === []
                && $state['restaurants'][1]['primary_cuisine'] === 'thai'
                && $state['restaurants'][1]['visit_count'] === 2
                && array_keys($state['restaurants'][1]) === [
                    'id',
                    'name',
                    'primary_cuisine',
                    'cuisine_tags',
                    'vibe_tags',
                    'service_level',
                    'patio_quality',
                    'avg_duration_minutes',
                    'visit_count',
                ];
        })
        ->andReturn([]);

    app(ScoreQuizFit::class)->execute(
        new QuizAnswers(energy: 'lively', hunger: 'feast', familiarity: 'new', dineInTakeout: 'dine_in'),
        collect([$b, $a]),
        quizFitWeather(),
    );
});

it('asks one four-level score question per restaurant keyed by its id', function () {
    $restaurant = Restaurant::factory()->create(['name' => 'Taco Place']);

    $this->mock(JevService::class)
        ->shouldReceive('ask')
        ->once()
        ->withArgs(function (mixed $state, array $questions) use ($restaurant): bool {
            $question = $questions["fit_{$restaurant->id}"] ?? null;

            return array_keys($questions) === ["fit_{$restaurant->id}"]
                && $question['type'] === 'score'
                && $question['instructions'] === "How well does the restaurant with id {$restaurant->id} (Taco Place) fit what this couple wants tonight, given their answers and the weather?"
                && $question['criteria'] === ['Poor fit', 'Okay fit', 'Good fit', 'Great fit'];
        })
        ->andReturn([]);

    app(ScoreQuizFit::class)->execute(new QuizAnswers, collect([$restaurant]), null);
});

it('returns a fit between 0 and 1 per restaurant from confident answers', function () {
    [$a, $b, $c] = Restaurant::factory()->count(3)->create();

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        "fit_{$a->id}" => ['type' => 'score', 'value' => 3.0, 'confidence' => 0.9],
        "fit_{$b->id}" => ['type' => 'score', 'value' => 1.5, 'confidence' => 0.9],
        "fit_{$c->id}" => ['type' => 'score', 'value' => 9.0, 'confidence' => 0.9],
    ]);

    $fits = app(ScoreQuizFit::class)->execute(new QuizAnswers, collect([$a, $b, $c]), null);

    expect($fits)->toBe([$a->id => 1.0, $b->id => 0.5, $c->id => 1.0]);
});

it('omits restaurants without a confident answer or with a non-numeric value', function () {
    [$a, $b, $c] = Restaurant::factory()->count(3)->create();

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        "fit_{$a->id}" => ['type' => 'score', 'value' => 'great', 'confidence' => 0.9],
        "fit_{$b->id}" => ['type' => 'score', 'value' => 3.0, 'confidence' => 0.9],
        'fit_99999' => ['type' => 'score', 'value' => 3.0, 'confidence' => 0.9],
    ]);

    $fits = app(ScoreQuizFit::class)->execute(new QuizAnswers, collect([$a, $b, $c]), null);

    expect($fits)->toBe([$b->id => 1.0]);
});

it('returns an empty array when Jev returns null', function () {
    $restaurant = Restaurant::factory()->create();

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn(null);

    expect(app(ScoreQuizFit::class)->execute(new QuizAnswers, collect([$restaurant]), null))->toBe([]);
});

it('returns an empty array without calling Jev when there are no candidates', function () {
    $this->mock(JevService::class)->shouldReceive('ask')->never();

    expect(app(ScoreQuizFit::class)->execute(new QuizAnswers, collect(), null))->toBe([]);
});

it('leaves skipped or invalid quiz answers out of the Jev state', function () {
    $restaurant = Restaurant::factory()->create();

    $this->mock(JevService::class)
        ->shouldReceive('ask')
        ->once()
        ->withArgs(fn (mixed $state): bool => $state['answers'] === [
            'service_level' => 'quick_easy',
            'cuisine' => 'thai',
        ] && $state['weather'] === null)
        ->andReturn([]);

    app(ScoreQuizFit::class)->execute(
        new QuizAnswers(
            energy: 'lively',
            hunger: 'moderate',
            familiarity: 'new',
            cuisine: 'thai',
            serviceLevel: 'quick_easy',
            dineInTakeout: 'either',
        ),
        collect([$restaurant]),
        null,
    );
});
