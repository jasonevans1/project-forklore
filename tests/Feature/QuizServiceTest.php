<?php

use App\Enums\PatioQuality;
use App\Enums\PrimaryCuisine;
use App\Enums\RestaurantSource;
use App\Enums\ServiceLevel;
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
        hunger: $overrides['hunger'] ?? 'full_meal',
        familiarity: $overrides['familiarity'] ?? 'either',
        distance: $overrides['distance'] ?? 'anywhere',
        cuisine: $overrides['cuisine'] ?? null,
        serviceLevel: $overrides['serviceLevel'] ?? 'casual_sit_down',
        dineInTakeout: $overrides['dineInTakeout'] ?? 'either',
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

it('scores a long avg_duration_minutes restaurant higher when hunger=feast', function () {
    $long = Restaurant::factory()->for($this->user, 'user')->create(['avg_duration_minutes' => 120, 'vibe_tags' => ['casual']]);
    $short = Restaurant::factory()->for($this->user, 'user')->create(['avg_duration_minutes' => 45, 'vibe_tags' => ['casual']]);

    $answers = neutralAnswers(['hunger' => 'feast']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($long->id);
});

it('scores a short avg_duration_minutes restaurant higher when hunger=quick_bite', function () {
    $short = Restaurant::factory()->for($this->user, 'user')->create(['avg_duration_minutes' => 45, 'vibe_tags' => ['casual']]);
    $long = Restaurant::factory()->for($this->user, 'user')->create(['avg_duration_minutes' => 120, 'vibe_tags' => ['casual']]);

    $answers = neutralAnswers(['hunger' => 'quick_bite']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($short->id);
});

it('does not apply a hunger bonus when avg_duration_minutes is null', function () {
    $noDuration = Restaurant::factory()->for($this->user, 'user')->create(['avg_duration_minutes' => null, 'vibe_tags' => ['casual']]);
    $farFromIdeal = Restaurant::factory()->for($this->user, 'user')->create(['avg_duration_minutes' => 45, 'vibe_tags' => ['casual']]);

    $answers = neutralAnswers(['hunger' => 'feast']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($noDuration->id);
});

it('falls back to the full_meal ideal duration for an unrecognized hunger value', function () {
    $near75 = Restaurant::factory()->for($this->user, 'user')->create(['avg_duration_minutes' => 75, 'vibe_tags' => ['casual']]);
    $far = Restaurant::factory()->for($this->user, 'user')->create(['avg_duration_minutes' => 45, 'vibe_tags' => ['casual']]);

    $answers = neutralAnswers(['hunger' => 'bogus_value']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($near75->id);
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

it('excludes restaurants beyond 2 miles when distance is under_2_miles', function () {
    // ~1 mile from Des Moines centre.
    $near = Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 41.5868,
        'lng' => -93.6250,
    ]);
    // ~20 miles away.
    Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 41.8000,
        'lng' => -93.6250,
    ]);

    $answers = new QuizAnswers(
        distance: 'under_2_miles',
        lat: 41.5868,
        lng: -93.6250,
    );

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($near->id);
});

it('excludes restaurants outside the 2 to 5 mile range when distance is 2_to_5_miles', function () {
    // ~1 mile away — inside 2mi, should be excluded from this bucket.
    Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 41.5868,
        'lng' => -93.6250,
    ]);
    // ~3 miles away — inside the 2-5mi bucket.
    $midRange = Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 41.63,
        'lng' => -93.6250,
    ]);
    // ~20 miles away — outside the 2-5mi bucket.
    Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 41.8000,
        'lng' => -93.6250,
    ]);

    $answers = new QuizAnswers(
        distance: '2_to_5_miles',
        lat: 41.5868,
        lng: -93.6250,
    );

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($midRange->id);
});

it('excludes restaurants outside the 5 to 15 mile range when distance is 5_to_15_miles', function () {
    // ~1 mile away — inside 5mi, should be excluded from this bucket.
    Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 41.5868,
        'lng' => -93.6250,
    ]);
    // ~10 miles away — inside the 5-15mi bucket.
    $midRange = Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 41.73,
        'lng' => -93.6250,
    ]);
    // ~50 miles away — outside the 5-15mi bucket.
    Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 42.30,
        'lng' => -93.6250,
    ]);

    $answers = new QuizAnswers(
        distance: '5_to_15_miles',
        lat: 41.5868,
        lng: -93.6250,
    );

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($midRange->id);
});

it('includes all restaurants regardless of distance when distance is anywhere', function () {
    Restaurant::factory()->for($this->user, 'user')->count(3)->create([
        'lat' => 41.8000,
        'lng' => -93.6250,
    ]);

    $answers = new QuizAnswers(
        distance: 'anywhere',
        lat: 41.5868,
        lng: -93.6250,
    );

    $result = $this->service->topMatch($this->user, $answers);

    expect($result)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Cuisine filtering
// ---------------------------------------------------------------------------

it('excludes restaurants with a different primary_cuisine when a cuisine is specified', function () {
    $italian = Restaurant::factory()->for($this->user, 'user')->create(['primary_cuisine' => PrimaryCuisine::Italian]);
    Restaurant::factory()->for($this->user, 'user')->create(['primary_cuisine' => PrimaryCuisine::Mexican]);

    $answers = neutralAnswers(['cuisine' => PrimaryCuisine::Italian->value]);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($italian->id);
});

it('includes restaurants regardless of primary_cuisine when cuisine is null (surprise me)', function () {
    Restaurant::factory()->for($this->user, 'user')->create(['primary_cuisine' => PrimaryCuisine::Italian]);
    Restaurant::factory()->for($this->user, 'user')->create(['primary_cuisine' => PrimaryCuisine::Mexican]);

    $answers = neutralAnswers(['cuisine' => null]);

    $winner = $this->service->topMatch($this->user, $answers);
    $runnerUp = $this->service->runnerUp($this->user, $answers, $winner);

    expect($winner)->not->toBeNull()
        ->and($runnerUp)->not->toBeNull();
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

// ---------------------------------------------------------------------------
// Dine-in / takeout filtering
// ---------------------------------------------------------------------------

it('excludes restaurants without dine_in in service_options when dineInTakeout is dine_in', function () {
    $dineIn = Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::Casual)->create([
        'service_options' => ['dine_in', 'takeout'],
    ]);
    Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::Casual)->create([
        'service_options' => ['takeout'],
    ]);

    $answers = neutralAnswers(['dineInTakeout' => 'dine_in']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($dineIn->id);
});

it('excludes restaurants without takeout in service_options when dineInTakeout is takeout', function () {
    $takeout = Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::Casual)->create([
        'service_options' => ['takeout', 'dine_in'],
    ]);
    Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::Casual)->create([
        'service_options' => ['dine_in'],
    ]);

    $answers = neutralAnswers(['dineInTakeout' => 'takeout']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($takeout->id);
});

it('includes restaurants regardless of service_options when dineInTakeout is either', function () {
    Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::Casual)->create([
        'service_options' => ['dine_in'],
    ]);
    Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::Casual)->create([
        'service_options' => ['takeout'],
    ]);

    $answers = neutralAnswers(['dineInTakeout' => 'either']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Service level filtering
// ---------------------------------------------------------------------------

it('includes only fast_food and fast_casual restaurants when serviceLevel is quick_easy', function () {
    $fastFood = Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::FastFood)->create();
    $fastCasual = Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::FastCasual)->create();
    Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::FineDining)->create();

    $answers = neutralAnswers(['serviceLevel' => 'quick_easy']);

    $result = $this->service->topMatch($this->user, $answers);

    expect(in_array($result->id, [$fastFood->id, $fastCasual->id], true))->toBeTrue();
});

it('includes only casual restaurants when serviceLevel is casual_sit_down', function () {
    $casual = Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::Casual)->create();
    Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::FineDining)->create();

    $answers = neutralAnswers(['serviceLevel' => 'casual_sit_down']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($casual->id);
});

it('includes only fine_dining restaurants when serviceLevel is special_occasion', function () {
    $fineDining = Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::FineDining)->create();
    Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::Casual)->create();

    $answers = neutralAnswers(['serviceLevel' => 'special_occasion']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($fineDining->id);
});

it('combines dine-in/takeout and service level filters together', function () {
    $match = Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::Casual)->create([
        'service_options' => ['dine_in'],
    ]);
    Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::Casual)->create([
        'service_options' => ['takeout'],
    ]);
    Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::FineDining)->create([
        'service_options' => ['dine_in'],
    ]);

    $answers = neutralAnswers(['serviceLevel' => 'casual_sit_down', 'dineInTakeout' => 'dine_in']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($match->id);
});

it('narrows the final pick using all four filters combined (dine-in/takeout, service level, cuisine, distance)', function () {
    $baseLat = 41.5868;
    $baseLng = -93.6250;

    $match = Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::Casual)->create([
        'service_options' => ['dine_in'],
        'primary_cuisine' => PrimaryCuisine::Italian,
        'lat' => $baseLat,
        'lng' => $baseLng,
    ]);
    // Wrong service_options — no dine_in.
    Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::Casual)->create([
        'service_options' => ['takeout'],
        'primary_cuisine' => PrimaryCuisine::Italian,
        'lat' => $baseLat,
        'lng' => $baseLng,
    ]);
    // Wrong service_level.
    Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::FineDining)->create([
        'service_options' => ['dine_in'],
        'primary_cuisine' => PrimaryCuisine::Italian,
        'lat' => $baseLat,
        'lng' => $baseLng,
    ]);
    // Wrong cuisine.
    Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::Casual)->create([
        'service_options' => ['dine_in'],
        'primary_cuisine' => PrimaryCuisine::Mexican,
        'lat' => $baseLat,
        'lng' => $baseLng,
    ]);
    // Wrong distance — ~20 miles away.
    Restaurant::factory()->for($this->user, 'user')->withServiceLevel(ServiceLevel::Casual)->create([
        'service_options' => ['dine_in'],
        'primary_cuisine' => PrimaryCuisine::Italian,
        'lat' => 41.8000,
        'lng' => $baseLng,
    ]);

    $answers = new QuizAnswers(
        distance: 'under_2_miles',
        cuisine: PrimaryCuisine::Italian->value,
        lat: $baseLat,
        lng: $baseLng,
        serviceLevel: 'casual_sit_down',
        dineInTakeout: 'dine_in',
    );

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($match->id);
});

it('does not exclude any restaurant by cuisine when cuisine is null (surprise me)', function () {
    // The odd-cuisine-out restaurant also has the best-matching vibe tag, so it can
    // only win if the cuisine filter did not exclude it beforehand.
    $oddCuisineOut = Restaurant::factory()->for($this->user, 'user')->create([
        'primary_cuisine' => PrimaryCuisine::Thai,
        'vibe_tags' => ['lively'],
    ]);
    Restaurant::factory()->for($this->user, 'user')->create([
        'primary_cuisine' => PrimaryCuisine::Italian,
        'vibe_tags' => ['quiet'],
    ]);
    Restaurant::factory()->for($this->user, 'user')->create([
        'primary_cuisine' => PrimaryCuisine::Mexican,
        'vibe_tags' => ['quiet'],
    ]);

    $answers = neutralAnswers(['cuisine' => null, 'energy' => 'lively']);

    $result = $this->service->topMatch($this->user, $answers);

    expect($result->id)->toBe($oddCuisineOut->id);
});

it('has no remaining assertions against removed nearby/close distance or cuisine soft-scoring behavior', function () {
    $testFileContents = file_get_contents(__FILE__);
    $serviceFileContents = file_get_contents(app_path('Services/QuizService.php'));

    // Built via concatenation so this sweep test doesn't trip over its own source.
    $forbiddenTerms = [
        'CUISINE_MATCH'.'_BONUS',
        "'nea".'rby\'',
        "'clo".'se\'',
        'NEARBY'.'_MILES',
        'CLOSE'.'_MILES',
    ];

    foreach ($forbiddenTerms as $term) {
        expect($testFileContents)->not->toContain($term)
            ->and($serviceFileContents)->not->toContain($term);
    }
});

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
