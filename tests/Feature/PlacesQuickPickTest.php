<?php

use App\Actions\ProfilePlacesRestaurant;
use App\Enums\PatioQuality;
use App\Enums\RestaurantSource;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\PlacesService;
use App\Services\QuickPickFilters;
use App\Services\QuickPickService;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Reference location used throughout — lat/lng must be present to trigger Places fallback.
const PLACES_QP_LAT = 41.58;
const PLACES_QP_LNG = -93.62;

/**
 * A minimal Places API result representing a single restaurant.
 *
 * @return array{id: string, name: string, address: string, types: list<string>, rating: float, lat: float, lng: float}
 */
function makePlacesResult(string $placeId = 'gplace_001', string $name = 'Places Bistro'): array
{
    return [
        'id' => $placeId,
        'name' => $name,
        'address' => '1 Places Ave, Des Moines, IA',
        'types' => ['restaurant'],
        'rating' => 4.0,
        'lat' => 41.581,
        'lng' => -93.621,
    ];
}

beforeEach(function () {
    // Weather service: always return null (no weather scoring noise in these tests).
    $this->weatherMock = $this->mock(WeatherService::class);
    $this->weatherMock->allows('fetch')->andReturnNull()->byDefault();

    // Places service: default to returning null (not called / quota empty).
    $this->placesMock = $this->mock(PlacesService::class);
    $this->placesMock->allows('nearbySearch')->andReturnNull()->byDefault();

    $this->service = app(QuickPickService::class);
    $this->user = User::factory()->create();

    $this->filtersWithLocation = new QuickPickFilters(
        lat: PLACES_QP_LAT,
        lng: PLACES_QP_LNG,
    );
});

// ---------------------------------------------------------------------------
// Threshold: when NOT to call Places (pool ≥ 3)
// ---------------------------------------------------------------------------

it('does not call Places when the favorites pool has exactly 3 candidates', function () {
    Restaurant::factory()->for($this->user, 'user')->count(3)->create();

    $this->placesMock->expects('nearbySearch')->never();

    $this->service->pick($this->user, $this->filtersWithLocation);
});

it('does not call Places when the favorites pool has more than 3 candidates', function () {
    Restaurant::factory()->for($this->user, 'user')->count(5)->create();

    $this->placesMock->expects('nearbySearch')->never();

    $this->service->pick($this->user, $this->filtersWithLocation);
});

// ---------------------------------------------------------------------------
// Threshold: when TO call Places (pool < 3)
// ---------------------------------------------------------------------------

it('calls Places nearbySearch when the favorites pool has exactly 2 candidates', function () {
    Restaurant::factory()->for($this->user, 'user')->count(2)->create();

    $this->placesMock->expects('nearbySearch')
        ->once()
        ->andReturn([makePlacesResult()]);

    $this->service->pick($this->user, $this->filtersWithLocation);
});

it('calls Places nearbySearch when the favorites pool has exactly 1 candidate', function () {
    Restaurant::factory()->for($this->user, 'user')->count(1)->create();

    $this->placesMock->expects('nearbySearch')
        ->once()
        ->andReturn([makePlacesResult()]);

    $this->service->pick($this->user, $this->filtersWithLocation);
});

it('calls Places nearbySearch when the favorites pool is empty', function () {
    $this->placesMock->expects('nearbySearch')
        ->once()
        ->andReturn([makePlacesResult()]);

    $this->service->pick($this->user, $this->filtersWithLocation);
});

it('calls Places with the lat/lng from the filters', function () {
    $this->placesMock->expects('nearbySearch')
        ->once()
        ->withArgs(fn (mixed $lat, mixed $lng): bool => $lat === PLACES_QP_LAT && $lng === PLACES_QP_LNG)
        ->andReturn([]);

    $this->service->pick($this->user, $this->filtersWithLocation);
});

// ---------------------------------------------------------------------------
// Threshold: pool size is determined AFTER filters are applied
// ---------------------------------------------------------------------------

it('triggers Places fallback when budget filter reduces a 3-favorite pool to 2', function () {
    // Two cheap restaurants survive the budget filter; one expensive one is excluded.
    Restaurant::factory()->for($this->user, 'user')->count(2)->create(['price_level' => 2]);
    Restaurant::factory()->for($this->user, 'user')->create(['price_level' => 4]);

    $filters = new QuickPickFilters(budget_max: 2, lat: PLACES_QP_LAT, lng: PLACES_QP_LNG);

    $this->placesMock->expects('nearbySearch')->once()->andReturn([]);

    $this->service->pick($this->user, $filters);
});

// ---------------------------------------------------------------------------
// No location → Places never called
// ---------------------------------------------------------------------------

it('does not call Places when lat/lng are absent from the filters', function () {
    // Pool has only 1 favorite — would normally trigger fallback — but no location.
    Restaurant::factory()->for($this->user, 'user')->count(1)->create();

    $this->placesMock->expects('nearbySearch')->never();

    $this->service->pick($this->user, new QuickPickFilters);
});

// ---------------------------------------------------------------------------
// Return value when Places is the only source
// ---------------------------------------------------------------------------

it('returns a restaurant from Places when the favorites pool is empty and Places returns results', function () {
    $this->placesMock->allows('nearbySearch')->andReturn([makePlacesResult('gplace_001', 'Solo Bistro')]);

    $result = $this->service->pick($this->user, $this->filtersWithLocation);

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Solo Bistro');
});

it('returns null when both the favorites pool and Places return nothing', function () {
    $this->placesMock->allows('nearbySearch')->andReturn([]);

    $result = $this->service->pick($this->user, $this->filtersWithLocation);

    expect($result)->toBeNull();
});

it('returns null when Places returns null (quota exceeded or key missing)', function () {
    $this->placesMock->allows('nearbySearch')->andReturnNull();

    $result = $this->service->pick($this->user, $this->filtersWithLocation);

    expect($result)->toBeNull();
});

// ---------------------------------------------------------------------------
// Storage: Places results are persisted with source=places
// ---------------------------------------------------------------------------

it('stores a Places result in the restaurants table with source=places', function () {
    $this->placesMock->allows('nearbySearch')->andReturn([makePlacesResult('gplace_abc', 'Stored Bistro')]);

    $this->service->pick($this->user, $this->filtersWithLocation);

    $this->assertDatabaseHas('restaurants', [
        'name' => 'Stored Bistro',
        'source' => RestaurantSource::Places->value,
        'owner_user_id' => $this->user->id,
    ]);
});

it('stores Places results with the places_id from the API response', function () {
    $this->placesMock->allows('nearbySearch')->andReturn([makePlacesResult('gplace_xyz')]);

    $this->service->pick($this->user, $this->filtersWithLocation);

    $this->assertDatabaseHas('restaurants', [
        'places_id' => 'gplace_xyz',
        'source' => RestaurantSource::Places->value,
    ]);
});

it('does not create a duplicate restaurant when the same places_id is returned a second time', function () {
    $this->placesMock->allows('nearbySearch')->andReturn([makePlacesResult('gplace_dup', 'Dup Bistro')]);

    $this->service->pick($this->user, $this->filtersWithLocation);
    $this->service->pick($this->user, $this->filtersWithLocation);

    expect(
        Restaurant::where('places_id', 'gplace_dup')->where('owner_user_id', $this->user->id)->count()
    )->toBe(1);
});

// ---------------------------------------------------------------------------
// Favorites list isolation: source=places restaurants are excluded
// ---------------------------------------------------------------------------

it('excludes source=places restaurants from the favorites scope', function () {
    // One favorite, one Places-sourced restaurant stored by a prior pick.
    $favorite = Restaurant::factory()->for($this->user, 'user')->create([
        'source' => RestaurantSource::Favorite,
    ]);
    Restaurant::factory()->for($this->user, 'user')->create([
        'source' => RestaurantSource::Places,
    ]);

    $favorites = Restaurant::ownedBy($this->user)->favorites()->get();

    expect($favorites)->toHaveCount(1)
        ->and($favorites->first()->id)->toBe($favorite->id);
});

it('only counts source=favorite restaurants when deciding whether to trigger Places fallback', function () {
    // Three Places-sourced rows exist — they must NOT count toward the pool threshold.
    Restaurant::factory()->for($this->user, 'user')->count(3)->create([
        'source' => RestaurantSource::Places,
    ]);
    // One real favorite — pool size is 1, so fallback must trigger.
    Restaurant::factory()->for($this->user, 'user')->count(1)->create([
        'source' => RestaurantSource::Favorite,
    ]);

    $this->placesMock->expects('nearbySearch')->once()->andReturn([]);

    $this->service->pick($this->user, $this->filtersWithLocation);
});

// ---------------------------------------------------------------------------
// Scoring: Places candidates receive a lower base score than favorites
// ---------------------------------------------------------------------------

it('always picks a favorite over a Places result when no weather modifiers apply', function () {
    // 2 favorites in the pool → Places fallback triggers.
    $fav1 = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'Fav One']);
    $fav2 = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'Fav Two']);

    $placesResult = makePlacesResult('gplace_score', 'Places Loser');
    $this->placesMock->allows('nearbySearch')->andReturn([$placesResult]);

    // Run 30 picks — Places restaurant should never win over the two favorites.
    $pickedIds = collect(range(1, 30))
        ->map(fn () => $this->service->pick($this->user, $this->filtersWithLocation)->id)
        ->unique();

    $placesRestaurant = Restaurant::where('places_id', 'gplace_score')->first();

    expect($pickedIds)->not->toContain($placesRestaurant?->id)
        ->and($pickedIds)->toContain($fav1->id)
        ->and($pickedIds)->toContain($fav2->id);
});

it('can return a Places result when it is the only candidate after the pool is empty', function () {
    $this->placesMock->allows('nearbySearch')->andReturn([makePlacesResult('gplace_only', 'Only Option')]);

    $result = $this->service->pick($this->user, $this->filtersWithLocation);

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Only Option');
});

// ---------------------------------------------------------------------------
// Deferred profiling
// ---------------------------------------------------------------------------

it("keeps an existing places restaurant's patio quality, vibe tags and cuisine on a later search", function () {
    $existing = Restaurant::factory()->for($this->user, 'user')->create([
        'source' => RestaurantSource::Places,
        'places_id' => 'gplace_keep',
        'name' => 'Old Name',
        'patio_quality' => PatioQuality::Destination,
        'vibe_tags' => ['cozy'],
        'cuisine_tags' => ['thai'],
        'profiled_at' => now(),
    ]);
    $this->placesMock->allows('nearbySearch')->andReturn([makePlacesResult('gplace_keep', 'New Name')]);

    $this->service->pick($this->user, $this->filtersWithLocation);

    $existing->refresh();
    expect($existing->name)->toBe('New Name')
        ->and($existing->patio_quality)->toBe(PatioQuality::Destination)
        ->and($existing->vibe_tags)->toBe(['cozy'])
        ->and($existing->cuisine_tags)->toBe(['thai']);
});

it('stores cuisine tags mapped from place types on a new places restaurant', function () {
    $place = ['types' => ['thai_restaurant', 'restaurant']] + makePlacesResult('gplace_cuisine');
    $this->placesMock->allows('nearbySearch')->andReturn([$place]);

    $this->service->pick($this->user, $this->filtersWithLocation);

    expect(Restaurant::where('places_id', 'gplace_cuisine')->first()->cuisine_tags)
        ->toBe(['thai']);
});

it('schedules profiling for a new unprofiled places restaurant', function () {
    $this->withoutDefer();
    $this->placesMock->allows('nearbySearch')->andReturn([makePlacesResult('gplace_new')]);
    $this->mock(ProfilePlacesRestaurant::class)
        ->expects('execute')->once()
        ->withArgs(fn (Restaurant $restaurant): bool => $restaurant->places_id === 'gplace_new');

    $this->service->pick($this->user, $this->filtersWithLocation);
});

it('does not schedule profiling for an already profiled places restaurant', function () {
    $this->withoutDefer();
    Restaurant::factory()->for($this->user, 'user')->create([
        'source' => RestaurantSource::Places,
        'places_id' => 'gplace_done',
        'profiled_at' => now(),
    ]);
    $this->placesMock->allows('nearbySearch')->andReturn([makePlacesResult('gplace_done')]);
    $this->mock(ProfilePlacesRestaurant::class)->expects('execute')->never();

    $this->service->pick($this->user, $this->filtersWithLocation);
});

it('returns a pick before deferred profiling runs', function () {
    $this->placesMock->allows('nearbySearch')->andReturn([makePlacesResult('gplace_late')]);
    $this->mock(ProfilePlacesRestaurant::class)->expects('execute')->never();

    $result = $this->service->pick($this->user, $this->filtersWithLocation);

    expect($result->places_id)->toBe('gplace_late');
});

it('still returns a pick when Jev is unavailable', function () {
    $this->withoutDefer();
    $this->placesMock->allows('nearbySearch')->andReturn([makePlacesResult('gplace_nojev')]);

    $result = $this->service->pick($this->user, $this->filtersWithLocation);

    expect($result->places_id)->toBe('gplace_nojev')
        ->and($result->profiled_at)->toBeNull();
});

it('does not modify or re-source a promoted favorite returned again by a nearby search', function () {
    $this->withoutDefer();
    $fav = Restaurant::factory()->for($this->user, 'user')->create([
        'source' => RestaurantSource::Favorite,
        'places_id' => 'gplace_fav',
        'name' => 'My Fav',
        'profiled_at' => null,
    ]);
    $this->placesMock->allows('nearbySearch')->andReturn([makePlacesResult('gplace_fav', 'Renamed')]);
    $this->mock(ProfilePlacesRestaurant::class)->expects('execute')->never();

    $this->service->pick($this->user, $this->filtersWithLocation);

    $fav->refresh();
    expect($fav->name)->toBe('My Fav')
        ->and($fav->source)->toBe(RestaurantSource::Favorite);
});

it('skips a place already stored for another user instead of failing', function () {
    $other = User::factory()->create();
    Restaurant::factory()->for($other, 'user')->create([
        'source' => RestaurantSource::Places,
        'places_id' => 'gplace_taken',
    ]);
    $this->placesMock->allows('nearbySearch')->andReturn([
        makePlacesResult('gplace_taken'),
        makePlacesResult('gplace_free', 'Free One'),
    ]);

    $result = $this->service->pick($this->user, $this->filtersWithLocation);

    expect($result->places_id)->toBe('gplace_free');
});

it('keeps profiling the remaining restaurants when one deferred profile throws', function () {
    $this->withoutDefer();
    $this->placesMock->allows('nearbySearch')->andReturn([
        makePlacesResult('gplace_a', 'A'),
        makePlacesResult('gplace_b', 'B'),
    ]);
    $profiled = [];
    $this->mock(ProfilePlacesRestaurant::class)
        ->allows('execute')
        ->andReturnUsing(function (Restaurant $restaurant) use (&$profiled): void {
            $profiled[] = $restaurant->places_id;
            if ($restaurant->places_id === 'gplace_a') {
                throw new RuntimeException('database is locked');
            }
        });

    $this->service->pick($this->user, $this->filtersWithLocation);

    expect($profiled)->toBe(['gplace_a', 'gplace_b']);
});
