<?php

use App\Enums\PatioQuality;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Visit;
use App\Services\QuickPickFilters;
use App\Services\QuickPickService;
use App\Services\WeatherData;
use App\Services\WeatherService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a WeatherData stub in metric units.
 *
 * @param  float  $tempC  Temperature in Celsius
 * @param  float  $precip  Precipitation mm/hr
 * @param  string  $conditions  e.g. 'Clear', 'Rain'
 */
function makeWeather(float $tempC = 22.0, float $precip = 0.0, string $conditions = 'Clear'): WeatherData
{
    return new WeatherData(
        temperature: $tempC,
        conditions: $conditions,
        precipitation: $precip,
        windSpeed: 2.0,
        sunset: CarbonImmutable::now()->addHours(4),
        units: 'metric',
    );
}

function makeService(?WeatherData $weather = null): QuickPickService
{
    $mock = Mockery::mock(WeatherService::class);
    $mock->allows('fetch')->andReturn($weather);

    return new QuickPickService($mock);
}

// ---------------------------------------------------------------------------
// Empty-pool handling
// ---------------------------------------------------------------------------

it('returns null when the user has no restaurants', function () {
    $user = User::factory()->create();

    expect(makeService()->pick($user))->toBeNull();
});

it('returns null when every restaurant was visited within the last 14 days', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->for($user, 'user')->create();
    Visit::factory()->create([
        'user_id' => $user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now()->subDays(3),
    ]);

    expect(makeService()->pick($user))->toBeNull();
});

it('returns null when the only restaurant is filtered out by budget_max', function () {
    $user = User::factory()->create();
    Restaurant::factory()->for($user, 'user')->create(['price_level' => 4]);

    $filters = new QuickPickFilters(budget_max: 2);

    expect(makeService()->pick($user, $filters))->toBeNull();
});

it('returns null when the only restaurant is filtered out by time_window', function () {
    $user = User::factory()->create();
    Restaurant::factory()->for($user, 'user')->create(['avg_duration_minutes' => 120]);

    $filters = new QuickPickFilters(time_window: 45);

    expect(makeService()->pick($user, $filters))->toBeNull();
});

it('returns null when the only restaurant is filtered out by max_distance_miles', function () {
    $user = User::factory()->create();
    // Restaurant is ~50 miles away from the filter origin (41.58, -93.62)
    Restaurant::factory()->for($user, 'user')->create(['lat' => 42.32, 'lng' => -93.62]);

    $filters = new QuickPickFilters(
        max_distance_miles: 10,
        lat: 41.58,
        lng: -93.62,
    );

    expect(makeService()->pick($user, $filters))->toBeNull();
});

// ---------------------------------------------------------------------------
// Recency exclusion
// ---------------------------------------------------------------------------

it('excludes restaurants visited within the last 14 days', function () {
    $user = User::factory()->create();
    $recent = Restaurant::factory()->for($user, 'user')->create(['name' => 'Recent']);
    $eligible = Restaurant::factory()->for($user, 'user')->create(['name' => 'Eligible']);

    Visit::factory()->create([
        'user_id' => $user->id,
        'restaurant_id' => $recent->id,
        'visited_at' => now()->subDays(10),
    ]);

    $result = makeService()->pick($user);

    expect($result?->id)->toBe($eligible->id);
});

it('includes restaurants whose last visit was exactly 15 days ago', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->for($user, 'user')->create();

    Visit::factory()->create([
        'user_id' => $user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now()->subDays(15),
    ]);

    expect(makeService()->pick($user))->not->toBeNull();
});

it('does not exclude a restaurant visited by a different user within 14 days', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $restaurant = Restaurant::factory()->for($owner, 'user')->create();

    Visit::factory()->create([
        'user_id' => $other->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now()->subDays(3),
    ]);

    expect(makeService()->pick($owner))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Filter application
// ---------------------------------------------------------------------------

it('filters by budget_max, keeping only restaurants at or below the price_level', function () {
    $user = User::factory()->create();
    $cheap = Restaurant::factory()->for($user, 'user')->create(['price_level' => 2]);
    Restaurant::factory()->for($user, 'user')->create(['price_level' => 4]);

    $filters = new QuickPickFilters(budget_max: 2);
    $result = makeService()->pick($user, $filters);

    expect($result?->id)->toBe($cheap->id);
});

it('filters by time_window, keeping restaurants with avg_duration_minutes at or below the limit', function () {
    $user = User::factory()->create();
    $quick = Restaurant::factory()->for($user, 'user')->create(['avg_duration_minutes' => 45]);
    Restaurant::factory()->for($user, 'user')->create(['avg_duration_minutes' => 120]);

    $filters = new QuickPickFilters(time_window: 60);
    $result = makeService()->pick($user, $filters);

    expect($result?->id)->toBe($quick->id);
});

it('includes restaurants with null avg_duration_minutes when a time_window filter is active', function () {
    $user = User::factory()->create();
    $unknown = Restaurant::factory()->for($user, 'user')->create(['avg_duration_minutes' => null]);

    $filters = new QuickPickFilters(time_window: 45);

    expect(makeService()->pick($user, $filters)?->id)->toBe($unknown->id);
});

it('filters by max_distance_miles, excluding restaurants beyond the radius', function () {
    $user = User::factory()->create();
    // Near (~0 miles from origin)
    $near = Restaurant::factory()->for($user, 'user')->create(['lat' => 41.58, 'lng' => -93.62]);
    // Far (~50 miles from origin)
    Restaurant::factory()->for($user, 'user')->create(['lat' => 42.32, 'lng' => -93.62]);

    $filters = new QuickPickFilters(
        max_distance_miles: 10,
        lat: 41.58,
        lng: -93.62,
    );

    $result = makeService()->pick($user, $filters);

    expect($result?->id)->toBe($near->id);
});

it('ignores max_distance_miles when no reference lat/lng is provided', function () {
    $user = User::factory()->create();
    // Two restaurants - both should be eligible since we have no reference point
    Restaurant::factory()->for($user, 'user')->count(2)->create();

    $filters = new QuickPickFilters(max_distance_miles: 1);

    expect(makeService()->pick($user, $filters))->not->toBeNull();
});

// A reference location used by all weather-scoring tests so the service can call the weather API.
const WEATHER_LAT = 41.58;
const WEATHER_LNG = -93.62;

/**
 * Filters pre-seeded with a reference location so weather scoring is active.
 */
function weatherFilters(): QuickPickFilters
{
    return new QuickPickFilters(lat: WEATHER_LAT, lng: WEATHER_LNG);
}

// ---------------------------------------------------------------------------
// Weather-based scoring — patio boost (65–85 °F / 18.33–29.44 °C, no rain)
// ---------------------------------------------------------------------------

it('boosts a Destination patio restaurant into the top pool under ideal weather, always selecting it over non-patio options', function () {
    $user = User::factory()->create();
    $patio = Restaurant::factory()->for($user, 'user')->create([
        'patio_quality' => PatioQuality::Destination,
        'vibe_tags' => [],
    ]);
    // Five non-patio restaurants at base score; patio gets +40 → always in top window
    Restaurant::factory()->for($user, 'user')->count(5)->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
    ]);

    // 22 °C = 71.6 °F — ideal patio weather, no rain
    $service = makeService(makeWeather(tempC: 22.0, precip: 0.0));

    $ids = collect(range(1, 20))->map(fn () => $service->pick($user, weatherFilters())->id)->unique();

    // The patio restaurant should always be selected (it's the only top candidate)
    expect($ids->count())->toBe(1)
        ->and($ids->first())->toBe($patio->id);
});

it('does not boost patio restaurants when it is raining', function () {
    $user = User::factory()->create();
    Restaurant::factory()->for($user, 'user')->create([
        'patio_quality' => PatioQuality::Destination,
        'vibe_tags' => [],
    ]);
    $indoor = Restaurant::factory()->for($user, 'user')->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
    ]);

    // Raining — patio gets no boost; both score equally, both should appear
    $service = makeService(makeWeather(tempC: 22.0, precip: 2.5, conditions: 'Rain'));

    $ids = collect(range(1, 40))->map(fn () => $service->pick($user, weatherFilters())->id)->unique();

    // Indoor restaurant must appear (proves patio wasn't the sole top candidate)
    expect($ids)->toContain($indoor->id);
});

it('does not boost patio restaurants when temperature is below 65 °F (18.33 °C)', function () {
    $user = User::factory()->create();
    Restaurant::factory()->for($user, 'user')->create([
        'patio_quality' => PatioQuality::Destination,
        'vibe_tags' => [],
    ]);
    $indoor = Restaurant::factory()->for($user, 'user')->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
    ]);

    // 15 °C = 59 °F — below the 65 °F threshold, no boost applied
    $service = makeService(makeWeather(tempC: 15.0, precip: 0.0));

    $ids = collect(range(1, 40))->map(fn () => $service->pick($user, weatherFilters())->id)->unique();

    expect($ids)->toContain($indoor->id);
});

it('does not boost patio restaurants when temperature is above 85 °F (29.44 °C)', function () {
    $user = User::factory()->create();
    Restaurant::factory()->for($user, 'user')->create([
        'patio_quality' => PatioQuality::Destination,
        'vibe_tags' => [],
    ]);
    $indoor = Restaurant::factory()->for($user, 'user')->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
    ]);

    // 31 °C = 87.8 °F — above the 85 °F ceiling, no boost applied
    $service = makeService(makeWeather(tempC: 31.0, precip: 0.0));

    $ids = collect(range(1, 40))->map(fn () => $service->pick($user, weatherFilters())->id)->unique();

    expect($ids)->toContain($indoor->id);
});

// ---------------------------------------------------------------------------
// Weather-based scoring — weather_dependent penalty (rain or < 40 °F / 4.44 °C)
// ---------------------------------------------------------------------------

it('penalizes a weather_dependent restaurant when it is raining, so it is never selected over non-dependent options', function () {
    $user = User::factory()->create();
    Restaurant::factory()->for($user, 'user')->count(3)->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
    ]);
    $dependent = Restaurant::factory()->for($user, 'user')->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => ['weather_dependent'],
    ]);

    $service = makeService(makeWeather(tempC: 22.0, precip: 2.5, conditions: 'Rain'));

    $ids = collect(range(1, 20))
        ->map(fn () => $service->pick($user, weatherFilters())->id)
        ->unique();

    expect($ids)->not->toContain($dependent->id);
});

it('penalizes a weather_dependent restaurant when temperature is below 40 °F (4.44 °C)', function () {
    $user = User::factory()->create();
    Restaurant::factory()->for($user, 'user')->count(3)->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
    ]);
    $dependent = Restaurant::factory()->for($user, 'user')->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => ['weather_dependent'],
    ]);

    // 2 °C = 35.6 °F — below the 40 °F cold threshold
    $service = makeService(makeWeather(tempC: 2.0, precip: 0.0, conditions: 'Clear'));

    $ids = collect(range(1, 20))
        ->map(fn () => $service->pick($user, weatherFilters())->id)
        ->unique();

    expect($ids)->not->toContain($dependent->id);
});

it('does not penalize a weather_dependent restaurant in clear, warm weather', function () {
    $user = User::factory()->create();
    $dependent = Restaurant::factory()->for($user, 'user')->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => ['weather_dependent'],
    ]);

    // 22 °C = 71.6 °F — nice weather, no penalty
    $service = makeService(makeWeather(tempC: 22.0, precip: 0.0, conditions: 'Clear'));

    // Only restaurant — must be returned despite the tag
    expect($service->pick($user, weatherFilters())?->id)->toBe($dependent->id);
});

// ---------------------------------------------------------------------------
// No weather data
// ---------------------------------------------------------------------------

it('returns a restaurant without weather scoring when no weather data is available', function () {
    $user = User::factory()->create();
    Restaurant::factory()->for($user, 'user')->create();

    // Service returns null weather
    $service = makeService(null);

    expect($service->pick($user))->not->toBeNull();
});

it('applies no weather scoring when lat/lng are not provided in the filters', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->for($user, 'user')->create([
        'patio_quality' => PatioQuality::Destination,
        'vibe_tags' => ['weather_dependent'],
    ]);

    // No lat/lng in filters → resolveWeather() returns null → no scoring applied
    $service = makeService();

    // Restaurant is still returned despite having both a boost tag and a penalty tag
    expect($service->pick($user)?->id)->toBe($restaurant->id);
});

// ---------------------------------------------------------------------------
// Randomisation among top candidates
// ---------------------------------------------------------------------------

it('randomises the pick among top-scored candidates', function () {
    $user = User::factory()->create();
    // Three identical restaurants — all equal score, all top candidates
    Restaurant::factory()->for($user, 'user')->count(3)->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
    ]);

    $service = makeService(null);

    $ids = collect(range(1, 50))->map(fn () => $service->pick($user)->id)->unique();

    // With 50 picks across 3 equally-scored restaurants, we expect > 1 unique result
    expect($ids->count())->toBeGreaterThan(1);
});

it('returns the sole remaining candidate when the pool has exactly one restaurant', function () {
    $user = User::factory()->create();
    $only = Restaurant::factory()->for($user, 'user')->create();

    expect(makeService()->pick($user)?->id)->toBe($only->id);
});
