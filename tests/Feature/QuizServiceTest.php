<?php

use App\Enums\PatioQuality;
use App\Enums\RestaurantSource;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\QuizAnswers;
use App\Services\QuizService;
use App\Services\WeatherData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Helper: a clear 72°F evening — ideal patio weather, no rain.
function idealWeather(): WeatherData
{
    return new WeatherData(
        temperature: 22.2, // ~72°F
        conditions: 'Clear',
        precipitation: 0.0,
        windSpeed: 2.0,
        sunset: CarbonImmutable::now()->addHours(4),
        units: 'metric',
    );
}

// Helper: rainy, cold night.
function badWeather(): WeatherData
{
    return new WeatherData(
        temperature: 2.0, // ~35°F
        conditions: 'Rain',
        precipitation: 1.5,
        windSpeed: 8.0,
        sunset: CarbonImmutable::now()->addHours(1),
        units: 'metric',
    );
}

// Helper: default neutral answers — no filters applied.
function neutralAnswers(array $overrides = []): QuizAnswers
{
    return new QuizAnswers(
        energy: $overrides['energy'] ?? 'moderate',
        hunger: $overrides['hunger'] ?? 'moderate',
        familiarity: $overrides['familiarity'] ?? 'either',
        distance: $overrides['distance'] ?? 'anywhere',
        cuisine: $overrides['cuisine'] ?? null,
    );
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->service = app(QuizService::class);
});

// ---------------------------------------------------------------------------
// topMatch — basic return contract
// ---------------------------------------------------------------------------

it('returns null when the user has no favorites', function () {
    $result = $this->service->topMatch($this->user, neutralAnswers());

    expect($result)->toBeNull();
});

it('returns the only restaurant when there is exactly one favorite', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $result = $this->service->topMatch($this->user, neutralAnswers());

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($restaurant->id);
});

it('returns a Restaurant model instance', function () {
    Restaurant::factory()->for($this->user, 'user')->create();

    $result = $this->service->topMatch($this->user, neutralAnswers());

    expect($result)->toBeInstanceOf(Restaurant::class);
});

// ---------------------------------------------------------------------------
// runnerUp
// ---------------------------------------------------------------------------

it('returns null for runner-up when there is only one favorite', function () {
    $winner = Restaurant::factory()->for($this->user, 'user')->create();

    $runnerUp = $this->service->runnerUp($this->user, neutralAnswers(), $winner);

    expect($runnerUp)->toBeNull();
});

it('returns a different restaurant from the winner as runner-up', function () {
    $winner = Restaurant::factory()->for($this->user, 'user')->create();
    Restaurant::factory()->for($this->user, 'user')->count(2)->create();

    $runnerUp = $this->service->runnerUp($this->user, neutralAnswers(), $winner);

    expect($runnerUp)->not->toBeNull()
        ->and($runnerUp->id)->not->toBe($winner->id);
});

it('never returns the winner as the runner-up', function () {
    $winner = Restaurant::factory()->for($this->user, 'user')->create();
    Restaurant::factory()->for($this->user, 'user')->count(4)->create();

    // Run multiple times to rule out lucky exclusion.
    foreach (range(1, 10) as $_) {
        $runnerUp = $this->service->runnerUp($this->user, neutralAnswers(), $winner);
        expect($runnerUp?->id)->not->toBe($winner->id);
    }
});

// ---------------------------------------------------------------------------
// Energy scoring
// ---------------------------------------------------------------------------

it('scores a lively-tagged restaurant higher when energy=lively', function () {
    $lively = Restaurant::factory()->for($this->user, 'user')->create(['vibe_tags' => ['lively']]);
    $quiet = Restaurant::factory()->for($this->user, 'user')->create(['vibe_tags' => ['quiet']]);

    $answers = neutralAnswers(['energy' => 'lively']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($lively->id);
});

it('scores a quiet-tagged restaurant higher when energy=quiet', function () {
    $quiet = Restaurant::factory()->for($this->user, 'user')->create(['vibe_tags' => ['quiet']]);
    $lively = Restaurant::factory()->for($this->user, 'user')->create(['vibe_tags' => ['lively']]);

    $answers = neutralAnswers(['energy' => 'quiet']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($quiet->id);
});

// ---------------------------------------------------------------------------
// Hunger scoring
// ---------------------------------------------------------------------------

it('scores a high price_level restaurant higher when hunger=hungry', function () {
    $expensive = Restaurant::factory()->for($this->user, 'user')->create(['price_level' => 4, 'vibe_tags' => ['casual']]);
    $cheap = Restaurant::factory()->for($this->user, 'user')->create(['price_level' => 1, 'vibe_tags' => ['casual']]);

    $answers = neutralAnswers(['hunger' => 'hungry']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($expensive->id);
});

it('scores a low price_level restaurant higher when hunger=light', function () {
    $cheap = Restaurant::factory()->for($this->user, 'user')->create(['price_level' => 1, 'vibe_tags' => ['casual']]);
    $expensive = Restaurant::factory()->for($this->user, 'user')->create(['price_level' => 4, 'vibe_tags' => ['casual']]);

    $answers = neutralAnswers(['hunger' => 'light']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($cheap->id);
});

// ---------------------------------------------------------------------------
// Familiarity scoring
// ---------------------------------------------------------------------------

it('scores a previously-unvisited restaurant higher when familiarity=new', function () {
    $fresh = Restaurant::factory()->for($this->user, 'user')->create(['visit_count' => 0]);
    $visited = Restaurant::factory()->for($this->user, 'user')->create(['visit_count' => 10]);

    $answers = neutralAnswers(['familiarity' => 'new']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($fresh->id);
});

it('scores a frequently-visited restaurant higher when familiarity=familiar', function () {
    $frequented = Restaurant::factory()->for($this->user, 'user')->create(['visit_count' => 15]);
    $fresh = Restaurant::factory()->for($this->user, 'user')->create(['visit_count' => 0]);

    $answers = neutralAnswers(['familiarity' => 'familiar']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($frequented->id);
});

// ---------------------------------------------------------------------------
// Distance filtering
// ---------------------------------------------------------------------------

it('excludes restaurants beyond the max distance when distance=nearby', function () {
    // Within ~1 mile of Des Moines centre.
    $near = Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 41.5868,
        'lng' => -93.6250,
    ]);
    // ~20 miles away.
    $far = Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 41.8000,
        'lng' => -93.6250,
    ]);

    $answers = new QuizAnswers(
        energy: 'moderate',
        hunger: 'moderate',
        familiarity: 'either',
        distance: 'nearby',
        cuisine: null,
        lat: 41.5868,
        lng: -93.6250,
    );

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($near->id);
});

it('includes all restaurants when distance=anywhere', function () {
    Restaurant::factory()->for($this->user, 'user')->count(3)->create([
        'lat' => 41.8000,
        'lng' => -93.6250,
    ]);

    $answers = new QuizAnswers(
        energy: 'moderate',
        hunger: 'moderate',
        familiarity: 'either',
        distance: 'anywhere',
        cuisine: null,
        lat: 41.5868,
        lng: -93.6250,
    );

    $result = $this->service->topMatch($this->user, $answers);

    expect($result)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Cuisine scoring
// ---------------------------------------------------------------------------

it('scores a restaurant with matching cuisine higher when a cuisine is specified', function () {
    $italian = Restaurant::factory()->for($this->user, 'user')->create(['cuisine_tags' => ['Italian']]);
    $mexican = Restaurant::factory()->for($this->user, 'user')->create(['cuisine_tags' => ['Mexican']]);

    $answers = neutralAnswers(['cuisine' => 'Italian']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($italian->id);
});

it('does not bias by cuisine when cuisine is null (surprise me)', function () {
    // Both restaurants are equal on all dimensions — service must return one of them.
    Restaurant::factory()->for($this->user, 'user')->count(2)->create();

    $answers = neutralAnswers(['cuisine' => null]);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Weather context
// ---------------------------------------------------------------------------

it('boosts a destination-patio restaurant in ideal weather', function () {
    $patio = Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::Destination,
        'vibe_tags' => ['casual'],
    ]);
    $indoor = Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => ['casual'],
    ]);

    $result = $this->service->topMatch($this->user, neutralAnswers(), idealWeather());

    expect($result->id)->toBe($patio->id);
});

it('penalises a weather_dependent restaurant in bad weather', function () {
    $weatherDependent = Restaurant::factory()->for($this->user, 'user')->create([
        'vibe_tags' => ['weather_dependent'],
    ]);
    $cozy = Restaurant::factory()->for($this->user, 'user')->create([
        'vibe_tags' => ['cozy'],
    ]);

    $result = $this->service->topMatch($this->user, neutralAnswers(), badWeather());

    expect($result->id)->toBe($cozy->id);
});

it('applies no weather modifier when weather is null', function () {
    Restaurant::factory()->for($this->user, 'user')->count(3)->create();

    // Must not throw, must return a restaurant.
    $result = $this->service->topMatch($this->user, neutralAnswers(), null);

    expect($result)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// source=places restaurants are excluded from quiz results
// ---------------------------------------------------------------------------

it('excludes source=places restaurants from quiz results', function () {
    $favorite = Restaurant::factory()->for($this->user, 'user')->create([
        'source' => RestaurantSource::Favorite,
    ]);
    Restaurant::factory()->for($this->user, 'user')->create([
        'source' => RestaurantSource::Places,
    ]);

    $result = $this->service->topMatch($this->user, neutralAnswers());

    expect($result->id)->toBe($favorite->id);
});
