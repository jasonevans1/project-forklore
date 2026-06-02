<?php

use App\Enums\PatioQuality;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Visit;
use App\Services\PlacesService;
use App\Services\QuickPickFilters;
use App\Services\QuickPickService;
use App\Services\WeatherData;
use App\Services\WeatherService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// A reference location used by weather-scoring tests so the service fetches weather.
const QUICK_PICK_LAT = 41.58;
const QUICK_PICK_LNG = -93.62;

beforeEach(function () {
    $this->weatherMock = Mockery::mock(WeatherService::class);
    $this->weatherMock->allows('fetch')->andReturnNull()->byDefault();

    // Places service stub — always returns null so it never influences existing tests.
    $this->placesMock = Mockery::mock(PlacesService::class);
    $this->placesMock->allows('nearbySearch')->andReturnNull()->byDefault();

    $this->service = new QuickPickService($this->weatherMock, $this->placesMock);

    $this->user = User::factory()->create();

    // Filters pre-seeded with a reference location so weather scoring is active.
    $this->weatherFilters = new QuickPickFilters(lat: QUICK_PICK_LAT, lng: QUICK_PICK_LNG);
});

/**
 * Build a WeatherData stub in metric units.
 *
 * @param  float  $tempC  Temperature in Celsius
 * @param  float  $precip  Precipitation mm/hr
 * @param  string  $conditions  e.g. 'Clear', 'Rain'
 */
function quickPickWeather(float $tempC = 22.0, float $precip = 0.0, string $conditions = 'Clear'): WeatherData
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

// ---------------------------------------------------------------------------
// Empty-pool handling
// ---------------------------------------------------------------------------

it('returns null when the user has no restaurants', function () {
    expect($this->service->pick($this->user))->toBeNull();
});

it('returns null when every restaurant was visited within the last 14 days', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();
    Visit::factory()->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now()->subDays(3),
    ]);

    expect($this->service->pick($this->user))->toBeNull();
});

it('returns null when the only restaurant is filtered out by budget_max', function () {
    Restaurant::factory()->for($this->user, 'user')->create(['price_level' => 4]);

    $filters = new QuickPickFilters(budget_max: 2);

    expect($this->service->pick($this->user, $filters))->toBeNull();
});

it('returns null when the only restaurant is filtered out by time_window', function () {
    Restaurant::factory()->for($this->user, 'user')->create(['avg_duration_minutes' => 120]);

    $filters = new QuickPickFilters(time_window: 45);

    expect($this->service->pick($this->user, $filters))->toBeNull();
});

it('returns null when the only restaurant is filtered out by max_distance_miles', function () {
    // Restaurant is ~50 miles north of the filter origin
    Restaurant::factory()->for($this->user, 'user')->create(['lat' => 42.32, 'lng' => -93.62]);

    $filters = new QuickPickFilters(max_distance_miles: 10, lat: 41.58, lng: -93.62);

    expect($this->service->pick($this->user, $filters))->toBeNull();
});

// ---------------------------------------------------------------------------
// Recency exclusion
// ---------------------------------------------------------------------------

it('excludes restaurants visited within the last 14 days', function () {
    $recent = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'Recent']);
    $eligible = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'Eligible']);

    Visit::factory()->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $recent->id,
        'visited_at' => now()->subDays(10),
    ]);

    expect($this->service->pick($this->user)?->id)->toBe($eligible->id);
});

it('includes restaurants whose last visit was exactly 22 days ago', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    Visit::factory()->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now()->subDays(22),
    ]);

    expect($this->service->pick($this->user))->not->toBeNull();
});

it('does not exclude a restaurant visited by a different user within 14 days', function () {
    $other = User::factory()->create();
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    Visit::factory()->create([
        'user_id' => $other->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now()->subDays(3),
    ]);

    expect($this->service->pick($this->user))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Filter application
// ---------------------------------------------------------------------------

it('filters by budget_max, keeping only restaurants at or below the price_level', function () {
    $cheap = Restaurant::factory()->for($this->user, 'user')->create(['price_level' => 2]);
    Restaurant::factory()->for($this->user, 'user')->create(['price_level' => 4]);

    $result = $this->service->pick($this->user, new QuickPickFilters(budget_max: 2));

    expect($result?->id)->toBe($cheap->id);
});

it('filters by time_window, keeping restaurants with avg_duration_minutes at or below the limit', function () {
    $quick = Restaurant::factory()->for($this->user, 'user')->create(['avg_duration_minutes' => 45]);
    Restaurant::factory()->for($this->user, 'user')->create(['avg_duration_minutes' => 120]);

    $result = $this->service->pick($this->user, new QuickPickFilters(time_window: 60));

    expect($result?->id)->toBe($quick->id);
});

it('includes restaurants with null avg_duration_minutes when a time_window filter is active', function () {
    $unknown = Restaurant::factory()->for($this->user, 'user')->create(['avg_duration_minutes' => null]);

    $result = $this->service->pick($this->user, new QuickPickFilters(time_window: 45));

    expect($result?->id)->toBe($unknown->id);
});

it('filters by max_distance_miles, excluding restaurants beyond the radius', function () {
    $near = Restaurant::factory()->for($this->user, 'user')->create(['lat' => 41.58, 'lng' => -93.62]);
    Restaurant::factory()->for($this->user, 'user')->create(['lat' => 42.32, 'lng' => -93.62]);

    $result = $this->service->pick($this->user, new QuickPickFilters(
        max_distance_miles: 10,
        lat: 41.58,
        lng: -93.62,
    ));

    expect($result?->id)->toBe($near->id);
});

it('ignores max_distance_miles when no reference lat/lng is provided', function () {
    Restaurant::factory()->for($this->user, 'user')->count(2)->create();

    $result = $this->service->pick($this->user, new QuickPickFilters(max_distance_miles: 1));

    expect($result)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Weather scoring — patio boost (65–85 °F / 18.33–29.44 °C, no rain)
// ---------------------------------------------------------------------------

it('boosts a Destination patio restaurant into the top pool under ideal weather, always selecting it over non-patio options', function () {
    $patio = Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::Destination,
        'vibe_tags' => [],
    ]);
    // Five non-patio restaurants at base score; patio gets +40 and is the sole top candidate.
    Restaurant::factory()->for($this->user, 'user')->count(5)->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
    ]);

    // 22 °C = 71.6 °F — ideal patio weather, no rain
    $this->weatherMock->allows('fetch')->andReturn(quickPickWeather(tempC: 22.0, precip: 0.0));

    $ids = collect(range(1, 20))->map(fn () => $this->service->pick($this->user, $this->weatherFilters)->id)->unique();

    expect($ids->count())->toBe(1)
        ->and($ids->first())->toBe($patio->id);
});

it('does not boost patio restaurants when it is raining', function () {
    Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::Destination,
        'vibe_tags' => [],
    ]);
    $indoor = Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
    ]);

    $this->weatherMock->allows('fetch')->andReturn(quickPickWeather(tempC: 22.0, precip: 2.5, conditions: 'Rain'));

    $ids = collect(range(1, 40))->map(fn () => $this->service->pick($this->user, $this->weatherFilters)->id)->unique();

    // Indoor restaurant must appear — proves patio wasn't the sole top candidate.
    expect($ids)->toContain($indoor->id);
});

it('does not boost patio restaurants when temperature is below 65 °F (18.33 °C)', function () {
    Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::Destination,
        'vibe_tags' => [],
    ]);
    $indoor = Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
    ]);

    // 15 °C = 59 °F — below the 65 °F threshold
    $this->weatherMock->allows('fetch')->andReturn(quickPickWeather(tempC: 15.0, precip: 0.0));

    $ids = collect(range(1, 40))->map(fn () => $this->service->pick($this->user, $this->weatherFilters)->id)->unique();

    expect($ids)->toContain($indoor->id);
});

it('does not boost patio restaurants when temperature is above 85 °F (29.44 °C)', function () {
    Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::Destination,
        'vibe_tags' => [],
    ]);
    $indoor = Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
    ]);

    // 31 °C = 87.8 °F — above the 85 °F ceiling
    $this->weatherMock->allows('fetch')->andReturn(quickPickWeather(tempC: 31.0, precip: 0.0));

    $ids = collect(range(1, 40))->map(fn () => $this->service->pick($this->user, $this->weatherFilters)->id)->unique();

    expect($ids)->toContain($indoor->id);
});

// ---------------------------------------------------------------------------
// Weather scoring — weather_dependent penalty (rain or < 40 °F / 4.44 °C)
// ---------------------------------------------------------------------------

it('penalizes a weather_dependent restaurant when it is raining, so it is never selected over non-dependent options', function () {
    Restaurant::factory()->for($this->user, 'user')->count(3)->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
    ]);
    $dependent = Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => ['weather_dependent'],
    ]);

    $this->weatherMock->allows('fetch')->andReturn(quickPickWeather(tempC: 22.0, precip: 2.5, conditions: 'Rain'));

    $ids = collect(range(1, 20))->map(fn () => $this->service->pick($this->user, $this->weatherFilters)->id)->unique();

    expect($ids)->not->toContain($dependent->id);
});

it('penalizes a weather_dependent restaurant when temperature is below 40 °F (4.44 °C)', function () {
    Restaurant::factory()->for($this->user, 'user')->count(3)->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
    ]);
    $dependent = Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => ['weather_dependent'],
    ]);

    // 2 °C = 35.6 °F — below the 40 °F cold threshold
    $this->weatherMock->allows('fetch')->andReturn(quickPickWeather(tempC: 2.0, precip: 0.0, conditions: 'Clear'));

    $ids = collect(range(1, 20))->map(fn () => $this->service->pick($this->user, $this->weatherFilters)->id)->unique();

    expect($ids)->not->toContain($dependent->id);
});

it('does not penalize a weather_dependent restaurant in clear, warm weather', function () {
    $dependent = Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => ['weather_dependent'],
    ]);

    // 22 °C = 71.6 °F — nice weather, no penalty
    $this->weatherMock->allows('fetch')->andReturn(quickPickWeather(tempC: 22.0, precip: 0.0, conditions: 'Clear'));

    expect($this->service->pick($this->user, $this->weatherFilters)?->id)->toBe($dependent->id);
});

// ---------------------------------------------------------------------------
// No weather data
// ---------------------------------------------------------------------------

it('returns a restaurant without weather scoring when no weather data is available', function () {
    Restaurant::factory()->for($this->user, 'user')->create();

    // weatherMock returns null by default (set in beforeEach)
    expect($this->service->pick($this->user, $this->weatherFilters))->not->toBeNull();
});

it('applies no weather scoring when lat/lng are not provided in the filters', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::Destination,
        'vibe_tags' => ['weather_dependent'],
    ]);

    // No lat/lng → resolveWeather() returns null without calling the weather service.
    expect($this->service->pick($this->user)?->id)->toBe($restaurant->id);
});

// ---------------------------------------------------------------------------
// Randomisation among top candidates
// ---------------------------------------------------------------------------

it('randomises the pick among top-scored candidates', function () {
    Restaurant::factory()->for($this->user, 'user')->count(3)->create([
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
    ]);

    $ids = collect(range(1, 50))->map(fn () => $this->service->pick($this->user)->id)->unique();

    // With 50 picks across 3 equally-scored restaurants, expect more than 1 unique result.
    expect($ids->count())->toBeGreaterThan(1);
});

it('returns the sole remaining candidate when the pool has exactly one restaurant', function () {
    $only = Restaurant::factory()->for($this->user, 'user')->create();

    expect($this->service->pick($this->user)?->id)->toBe($only->id);
});
