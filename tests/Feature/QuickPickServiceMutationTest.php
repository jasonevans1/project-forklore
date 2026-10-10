<?php

use App\Actions\ProfilePlacesRestaurant;
use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Enums\RestaurantSource;
use App\Models\HouseholdState;
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
use Illuminate\Support\Facades\Exceptions;

uses(RefreshDatabase::class);

const MUT_LAT = 41.58;
const MUT_LNG = -93.62;

beforeEach(function () {
    $this->weatherMock = Mockery::mock(WeatherService::class);
    $this->weatherMock->allows('fetch')->andReturnNull()->byDefault();
    $this->placesMock = Mockery::mock(PlacesService::class);
    $this->placesMock->allows('nearbySearch')->andReturn([])->byDefault();
    $this->service = new QuickPickService($this->weatherMock, $this->placesMock);
    $this->user = User::factory()->create();
    $this->filters = new QuickPickFilters(lat: MUT_LAT, lng: MUT_LNG);
});

function mutWeather(float $tempC = 22.0, float $precip = 0.0, string $conditions = 'Clear'): WeatherData
{
    return new WeatherData($tempC, $conditions, $precip, 2.0, CarbonImmutable::now()->addHours(4), 'metric');
}

function mutCelsius(float $fahrenheit): float
{
    return ($fahrenheit - 32) * 5 / 9;
}

/** @return list<int> sorted unique ids picked across many runs */
function mutPickedIds(object $test, ?QuickPickFilters $filters = null, int $runs = 40): array
{
    return collect(range(1, $runs))
        ->map(fn () => $test->service->pick($test->user, $filters ?? $test->filters)->id)
        ->unique()->sort()->values()->all();
}

function mutFav(User $user, array $attrs = []): Restaurant
{
    return Restaurant::factory()->for($user, 'user')->create($attrs + [
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
    ]);
}

function mutPlaces(User $user, array $attrs = []): Restaurant
{
    return Restaurant::factory()->for($user, 'user')->create($attrs + [
        'source' => RestaurantSource::Places,
        'places_id' => 'gp_'.uniqid(),
        'patio_quality' => PatioQuality::None,
        'vibe_tags' => [],
        'profiled_at' => now(),
    ]);
}

// --- Recency window ---------------------------------------------------------

it('excludes a restaurant visited 20.5 days ago but includes one visited 21.5 days ago', function () {
    $recent = mutFav($this->user);
    $old = mutFav($this->user);
    Visit::factory()->create(['user_id' => $this->user->id, 'restaurant_id' => $recent->id, 'visited_at' => now()->subDays(20)->subHours(12)]);
    Visit::factory()->create(['user_id' => $this->user->id, 'restaurant_id' => $old->id, 'visited_at' => now()->subDays(21)->subHours(12)]);

    expect(mutPickedIds($this, new QuickPickFilters, 15))->toBe([$old->id]);
});

it('excludes both visited and session-rejected restaurants', function () {
    $visited = mutFav($this->user);
    $rejected = mutFav($this->user);
    $kept = mutFav($this->user);
    Visit::factory()->create(['user_id' => $this->user->id, 'restaurant_id' => $visited->id, 'visited_at' => now()->subDays(2)]);

    $filters = new QuickPickFilters(excludedIds: [$rejected->id, $visited->id]);

    expect(mutPickedIds($this, $filters, 15))->toBe([$kept->id]);
});

// --- Distance ---------------------------------------------------------------

it('keeps a restaurant exactly at the reference point with a zero-mile radius', function () {
    $here = mutFav($this->user, ['lat' => MUT_LAT, 'lng' => MUT_LNG]);
    mutFav($this->user, ['lat' => 41.9, 'lng' => MUT_LNG]);

    $result = $this->service->pick($this->user, new QuickPickFilters(max_distance_miles: 0.0, lat: MUT_LAT, lng: MUT_LNG));

    expect($result->id)->toBe($here->id);
});

it('applies the distance radius precisely for a diagonal offset', function (float $dLat, float $dLng) {
    $lat = MUT_LAT + $dLat;
    $lng = MUT_LNG + $dLng;
    $storedLat = round($lat, 7);
    $storedLng = round($lng, 7);
    $r = 3958.8;
    $p1 = deg2rad(MUT_LAT);
    $p2 = deg2rad($storedLat);
    $miles = $r * acos(sin($p1) * sin($p2) + cos($p1) * cos($p2) * cos(deg2rad($storedLng - MUT_LNG)));

    $restaurant = mutFav($this->user, ['lat' => $storedLat, 'lng' => $storedLng]);
    $other = mutFav($this->user, ['lat' => $storedLat, 'lng' => $storedLng]);
    $other->delete();

    $inside = new QuickPickFilters(max_distance_miles: $miles * 1.0001, lat: MUT_LAT, lng: MUT_LNG);
    $outside = new QuickPickFilters(max_distance_miles: $miles * 0.9999, lat: MUT_LAT, lng: MUT_LNG);

    expect($this->service->pick($this->user, $inside)?->id)->toBe($restaurant->id)
        ->and($this->service->pick($this->user, $outside))->toBeNull();
})->with([
    'north-east ~13mi' => [0.15, 0.2],
    'south-west ~110mi' => [-1.2, -1.5],
    'east only ~35mi' => [0.0, 0.6],
    'transcontinental ~2500mi' => [-30.0, 20.0],
]);

// --- Places fallback gating -------------------------------------------------

it('does not search Places or fetch weather when only latitude is given', function () {
    $fav = mutFav($this->user);
    $this->placesMock->shouldNotReceive('nearbySearch');
    $this->weatherMock->shouldNotReceive('fetch');

    $result = $this->service->pick($this->user, new QuickPickFilters(lat: MUT_LAT));

    expect($result->id)->toBe($fav->id);
});

it('does not search Places or fetch weather when only longitude is given', function () {
    $fav = mutFav($this->user);
    $this->placesMock->shouldNotReceive('nearbySearch');
    $this->weatherMock->shouldNotReceive('fetch');

    $result = $this->service->pick($this->user, new QuickPickFilters(lng: MUT_LNG));

    expect($result->id)->toBe($fav->id);
});

it('ignores Places results that have no id', function () {
    $fav = mutFav($this->user);
    $this->placesMock->allows('nearbySearch')->andReturn([['name' => 'Ghost', 'id' => '']]);

    expect(mutPickedIds($this, null, 10))->toBe([$fav->id])
        ->and(Restaurant::where('name', 'Ghost')->exists())->toBeFalse();
});

// --- Places row creation ----------------------------------------------------

it('stores every mapped field of a new Places restaurant', function () {
    $this->withoutDefer();
    $this->mock(ProfilePlacesRestaurant::class)->allows('execute');
    $this->placesMock->allows('nearbySearch')->andReturn([[
        'id' => 'gp_full',
        'name' => 'Full Place',
        'address' => '9 Main St',
        'types' => ['thai_restaurant', 'restaurant'],
        'price_level' => 3,
        'lat' => 41.5,
        'lng' => -93.5,
    ]]);

    $this->service->pick($this->user, $this->filters);

    $row = Restaurant::where('places_id', 'gp_full')->firstOrFail();
    expect($row->owner_user_id)->toBe($this->user->id)
        ->and($row->source)->toBe(RestaurantSource::Places)
        ->and($row->name)->toBe('Full Place')
        ->and($row->address)->toBe('9 Main St')
        ->and($row->cuisine_tags)->toBe(['thai'])
        ->and($row->price_level)->toBe(3)
        ->and($row->vibe_tags)->toBe([])
        ->and($row->patio_quality)->toBe(PatioQuality::None)
        ->and($row->indoor_vibe_when_cold)->toBe(IndoorVibe::Neutral)
        ->and((float) $row->lat)->toBe(41.5)
        ->and((float) $row->lng)->toBe(-93.5);
});

it('falls back to defaults when a Places result has only an id', function () {
    $this->withoutDefer();
    $this->mock(ProfilePlacesRestaurant::class)->allows('execute');
    $this->placesMock->allows('nearbySearch')->andReturn([['id' => 'gp_min']]);

    $this->service->pick($this->user, $this->filters);

    $row = Restaurant::where('places_id', 'gp_min')->firstOrFail();
    expect($row->name)->toBe('Unknown')
        ->and($row->address)->toBeNull()
        ->and($row->lat)->toBeNull()
        ->and($row->lng)->toBeNull()
        ->and($row->price_level)->toBeNull()
        ->and($row->cuisine_tags)->toBe([]);
});

it('reports an exception thrown by deferred profiling', function () {
    Exceptions::fake();
    $this->withoutDefer();
    $this->placesMock->allows('nearbySearch')->andReturn([['id' => 'gp_boom', 'name' => 'Boom']]);
    $this->mock(ProfilePlacesRestaurant::class)->allows('execute')->andThrow(new RuntimeException('profile failed'));

    $this->service->pick($this->user, $this->filters);

    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'profile failed');
});

// --- Score arithmetic (candidates within 10 points both appear; further apart, only the leader) ---

it('scores candidates exactly as documented', function (array $a, array $b, float $tempC, array $expected) {
    $this->weatherMock->allows('fetch')->andReturn(mutWeather($tempC));
    $alice = $this->user;
    $bob = User::factory()->create(['preferred_vibe_tags' => ['lively']]);

    $make = fn (array $spec): Restaurant => $spec['places']
        ? mutPlaces($alice, ['patio_quality' => $spec['patio']])
        : mutFav($alice, ['patio_quality' => $spec['patio']]);
    $first = $make($a);
    $second = $make($b);
    $both = [$first->id, $second->id];
    sort($both);

    $expectedIds = $expected === ['both'] ? $both : [$expected[0] === 'a' ? $first->id : $second->id];

    $this->placesMock->allows('nearbySearch')->andReturn(
        Restaurant::where('source', RestaurantSource::Places)->get()->map(fn (Restaurant $r): array => ['id' => $r->places_id, 'name' => $r->name])->all()
    );

    expect(mutPickedIds($this, null, 40))->toBe($expectedIds);
})->with([
    'places base 70 + destination 40 vs favorite 100 (diff 10)' => [
        ['places' => true, 'patio' => PatioQuality::Destination], ['places' => false, 'patio' => PatioQuality::None], 22.0, ['both'],
    ],
    'places base 70 + decent 20 vs favorite 100 (diff 10)' => [
        ['places' => true, 'patio' => PatioQuality::Decent], ['places' => false, 'patio' => PatioQuality::None], 22.0, ['both'],
    ],
    'favorite decent 120 vs places destination 110 (diff 10)' => [
        ['places' => false, 'patio' => PatioQuality::Decent], ['places' => true, 'patio' => PatioQuality::Destination], 22.0, ['both'],
    ],
    'favorite decent 120 vs places decent 90 (diff 30)' => [
        ['places' => false, 'patio' => PatioQuality::Decent], ['places' => true, 'patio' => PatioQuality::Decent], 22.0, ['a'],
    ],
    'favorite plain 100 vs places plain 70 (diff 30)' => [
        ['places' => false, 'patio' => PatioQuality::None], ['places' => true, 'patio' => PatioQuality::None], 22.0, ['a'],
    ],
]);

// --- Patio temperature window ----------------------------------------------

it('boosts a patio only inside the 65-85 °F window', function (float $fahrenheit, bool $boosted) {
    $patio = mutFav($this->user, ['patio_quality' => PatioQuality::Destination]);
    $plain = mutFav($this->user);
    $this->weatherMock->allows('fetch')->andReturn(mutWeather(mutCelsius($fahrenheit)));

    $ids = mutPickedIds($this, null, 40);

    expect($ids)->toBe($boosted ? [$patio->id] : collect([$patio->id, $plain->id])->sort()->values()->all());
})->with([
    'just below 65' => [64.5, false],
    'exactly 65' => [65.0, true],
    'just above 65' => [65.5, true],
    'just below 85' => [84.5, true],
    'exactly 85' => [85.0, true],
    'just above 85' => [85.5, false],
    'well inside' => [75.0, true],
]);

it('does not boost patios when it is only raining by condition text', function () {
    mutFav($this->user, ['patio_quality' => PatioQuality::Destination]);
    $plain = mutFav($this->user);
    $this->weatherMock->allows('fetch')->andReturn(mutWeather(22.0, 0.0, 'Light Rain'));

    expect(mutPickedIds($this))->toContain($plain->id);
});

it('does not boost patios when there is a trace of precipitation under clear conditions', function () {
    mutFav($this->user, ['patio_quality' => PatioQuality::Destination]);
    $plain = mutFav($this->user);
    $this->weatherMock->allows('fetch')->andReturn(mutWeather(22.0, 0.3, 'Clear'));

    expect(mutPickedIds($this))->toContain($plain->id);
});

// --- Weather-dependent penalty ---------------------------------------------

it('penalises weather_dependent restaurants for bad weather and cold only', function (float $tempC, float $precip, string $conditions, bool $penalised) {
    $dependent = mutFav($this->user, ['vibe_tags' => ['weather_dependent']]);
    $plain = mutFav($this->user);
    $this->weatherMock->allows('fetch')->andReturn(mutWeather($tempC, $precip, $conditions));

    $expected = $penalised ? [$plain->id] : collect([$dependent->id, $plain->id])->sort()->values()->all();

    expect(mutPickedIds($this))->toBe($expected);
})->with([
    'capitalised Rain text, no precipitation' => [22.0, 0.0, 'Rain', true],
    'precipitation, clear text' => [22.0, 0.4, 'Clear', true],
    'light drizzle under 1mm' => [22.0, 0.5, 'Drizzle', true],
    'just below 40F' => [mutCelsius(39.5), 0.0, 'Clear', true],
    'exactly 40F' => [mutCelsius(40.0), 0.0, 'Clear', false],
    'just above 40F' => [mutCelsius(40.5), 0.0, 'Clear', false],
    'dry mild' => [22.0, 0.0, 'Clouds', false],
]);

// --- Partner turn bias ------------------------------------------------------

it('only applies the partner vibe boost when the vibe matches and it is the partner turn', function (bool $partnersTurn, bool $matches, bool $boosted) {
    $bob = User::factory()->create(['preferred_vibe_tags' => ['lively']]);
    $this->user->update(['partner_id' => $bob->id]);
    $bob->update(['partner_id' => $this->user->id]);
    if ($partnersTurn) {
        HouseholdState::recordPick($this->user);
    } else {
        HouseholdState::recordPick($bob);
    }

    $tagged = mutFav($this->user, ['vibe_tags' => $matches ? ['lively'] : ['cozy']]);
    $plain = mutFav($this->user, ['vibe_tags' => ['quiet']]);

    $expected = $boosted ? [$tagged->id] : collect([$tagged->id, $plain->id])->sort()->values()->all();

    expect(mutPickedIds($this, new QuickPickFilters))->toBe($expected);
})->with([
    'partner turn, matching vibe' => [true, true, true],
    'partner turn, no match' => [true, false, false],
    'my turn, matching vibe' => [false, true, false],
]);
