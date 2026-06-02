<?php

use App\Services\PlacesService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'services.google_places.key' => 'test-places-key',
        'services.google_places.daily_quota' => 100,
    ]);
    Cache::flush();
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function textSearchResponse(array $places = []): array
{
    return [
        'places' => $places ?: [
            [
                'id' => 'place_abc123',
                'displayName' => ['text' => 'The Rusty Fork'],
                'formattedAddress' => '123 Main St, Des Moines, IA',
                'types' => ['restaurant', 'food'],
                'rating' => 4.3,
                'location' => ['latitude' => 41.5868, 'longitude' => -93.6250],
                'priceLevel' => 'PRICE_LEVEL_MODERATE',
            ],
        ],
    ];
}

function nearbySearchResponse(array $places = []): array
{
    return [
        'places' => $places ?: [
            [
                'id' => 'place_nearby99',
                'displayName' => ['text' => 'Corner Bistro'],
                'formattedAddress' => '9 Oak Ave, Des Moines, IA',
                'types' => ['restaurant'],
                'rating' => 4.1,
            ],
        ],
    ];
}

function placeDetailsResponse(string $id = 'place_abc123'): array
{
    return [
        'id' => $id,
        'displayName' => ['text' => 'The Rusty Fork'],
        'formattedAddress' => '123 Main St, Des Moines, IA',
        'types' => ['restaurant', 'food'],
        'rating' => 4.3,
        'regularOpeningHours' => [
            'weekdayDescriptions' => ['Monday: 11:00 AM – 10:00 PM'],
        ],
        'websiteUri' => 'https://rustyfork.example.com',
    ];
}

// ---------------------------------------------------------------------------
// Text Search — cache miss (live HTTP call)
// ---------------------------------------------------------------------------

it('calls Google Places Text Search API and returns parsed results on a cache miss', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response(textSearchResponse(), 200),
    ]);

    $service = app(PlacesService::class);
    $results = $service->textSearch('pizza downtown');

    expect($results)->toBeArray()
        ->and($results)->not->toBeEmpty()
        ->and($results[0]['id'])->toBe('place_abc123')
        ->and($results[0]['name'])->toBe('The Rusty Fork');

    Http::assertSentCount(1);
});

// ---------------------------------------------------------------------------
// Text Search — cache hit (no HTTP call)
// ---------------------------------------------------------------------------

it('returns cached text search results and makes no HTTP request on a cache hit', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response(textSearchResponse(), 200),
    ]);

    $service = app(PlacesService::class);
    $service->textSearch('pizza downtown');   // primes cache
    $service->textSearch('pizza downtown');   // should hit cache

    Http::assertSentCount(1);
});

// ---------------------------------------------------------------------------
// Text Search — cache expires after 7 days
// ---------------------------------------------------------------------------

it('re-fetches text search results after 7 days cache expiry', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response(textSearchResponse(), 200),
    ]);

    $service = app(PlacesService::class);
    $service->textSearch('tacos');   // primes cache

    // Expire both the in-process cache and the DB row
    $hash = hash('sha256', 'text_search:tacos');
    Cache::forget("places:{$hash}");
    DB::table('places_cache')
        ->where('query_hash', $hash)
        ->update(['fetched_at' => now()->subDays(8)->toDateTimeString()]);

    $service->textSearch('tacos');   // should re-fetch

    Http::assertSentCount(2);
});

// ---------------------------------------------------------------------------
// Nearby Search — cache miss
// ---------------------------------------------------------------------------

it('calls Google Places Nearby Search API and returns parsed results on a cache miss', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchNearby*' => Http::response(nearbySearchResponse(), 200),
    ]);

    $service = app(PlacesService::class);
    $results = $service->nearbySearch(41.5868, -93.6250, 1000);

    expect($results)->toBeArray()
        ->and($results)->not->toBeEmpty()
        ->and($results[0]['id'])->toBe('place_nearby99')
        ->and($results[0]['name'])->toBe('Corner Bistro');

    Http::assertSentCount(1);
});

// ---------------------------------------------------------------------------
// Nearby Search — cache hit
// ---------------------------------------------------------------------------

it('returns cached nearby search results and makes no HTTP request on a cache hit', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchNearby*' => Http::response(nearbySearchResponse(), 200),
    ]);

    $service = app(PlacesService::class);
    $service->nearbySearch(41.5868, -93.6250, 1000);
    $service->nearbySearch(41.5868, -93.6250, 1000);

    Http::assertSentCount(1);
});

// ---------------------------------------------------------------------------
// Place Details — cache miss
// ---------------------------------------------------------------------------

it('calls Google Places Details API and returns parsed result on a cache miss', function () {
    Http::fake([
        'places.googleapis.com/v1/places/place_abc123*' => Http::response(placeDetailsResponse(), 200),
    ]);

    $service = app(PlacesService::class);
    $result = $service->placeDetails('place_abc123');

    expect($result)->toBeArray()
        ->and($result['id'])->toBe('place_abc123')
        ->and($result['name'])->toBe('The Rusty Fork')
        ->and($result['website'])->toBe('https://rustyfork.example.com');

    Http::assertSentCount(1);
});

// ---------------------------------------------------------------------------
// Place Details — cache hit
// ---------------------------------------------------------------------------

it('returns cached place details and makes no HTTP request on a cache hit', function () {
    Http::fake([
        'places.googleapis.com/v1/places/place_abc123*' => Http::response(placeDetailsResponse(), 200),
    ]);

    $service = app(PlacesService::class);
    $service->placeDetails('place_abc123');
    $service->placeDetails('place_abc123');

    Http::assertSentCount(1);
});

// ---------------------------------------------------------------------------
// places_cache database table is used
// ---------------------------------------------------------------------------

it('persists raw API response JSON to the places_cache table on a cache miss', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response(textSearchResponse(), 200),
    ]);

    $service = app(PlacesService::class);
    $service->textSearch('burgers');

    $hash = hash('sha256', 'text_search:burgers');

    $this->assertDatabaseHas('places_cache', [
        'query_hash' => $hash,
    ]);
});

it('updates fetched_at when stale cache is refreshed', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response(textSearchResponse(), 200),
    ]);

    $service = app(PlacesService::class);
    $service->textSearch('sushi');

    $hash = hash('sha256', 'text_search:sushi');

    // Force the record to appear older than 7 days
    DB::table('places_cache')
        ->where('query_hash', $hash)
        ->update(['fetched_at' => now()->subDays(8)->toDateTimeString()]);

    Cache::forget("places:{$hash}");

    $service->textSearch('sushi');  // should re-fetch

    Http::assertSentCount(2);

    $record = DB::table('places_cache')
        ->where('query_hash', $hash)
        ->first();

    expect($record->fetched_at)->not->toBeNull()
        ->and(now()->diffInHours($record->fetched_at))->toBeLessThan(1);
});

// ---------------------------------------------------------------------------
// Quota enforcement
// ---------------------------------------------------------------------------

it('short-circuits and returns null when the daily quota cap is exceeded', function () {
    config(['services.google_places.daily_quota' => 5]);

    // Exhaust the quota in the cache counter
    $quotaKey = 'google_places_quota:'.now()->toDateString();
    Cache::put($quotaKey, 5);

    $service = app(PlacesService::class);
    $result = $service->textSearch('sushi');

    expect($result)->toBeNull();
    Http::assertNothingSent();
});

it('increments the quota counter after each successful API call', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response(textSearchResponse(), 200),
    ]);

    config(['services.google_places.daily_quota' => 100]);

    $service = app(PlacesService::class);
    $service->textSearch('ramen');
    $service->textSearch('tacos');   // different query, separate cache keys

    $quotaKey = 'google_places_quota:'.now()->toDateString();
    expect(Cache::get($quotaKey))->toBe(2);
});

it('does not increment quota counter on a cache hit', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response(textSearchResponse(), 200),
    ]);

    $service = app(PlacesService::class);
    $service->textSearch('ramen');
    $service->textSearch('ramen');   // cache hit

    $quotaKey = 'google_places_quota:'.now()->toDateString();
    expect(Cache::get($quotaKey))->toBe(1);
});

it('returns null without hitting the API when the google_places key is not configured', function () {
    config(['services.google_places.key' => null]);

    $service = app(PlacesService::class);
    $result = $service->textSearch('burgers');

    expect($result)->toBeNull();
    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// parsePlaces — lat / lng / price_level
// ---------------------------------------------------------------------------

it('includes latitude in parsed text search results', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response(textSearchResponse(), 200),
    ]);

    $service = app(PlacesService::class);
    $results = $service->textSearch('pizza');

    expect($results[0]['lat'])->toBe(41.5868);
});

it('includes longitude in parsed text search results', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response(textSearchResponse(), 200),
    ]);

    $service = app(PlacesService::class);
    $results = $service->textSearch('pizza');

    expect($results[0]['lng'])->toBe(-93.6250);
});

it('includes price_level as an integer in parsed text search results', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response(textSearchResponse(), 200),
    ]);

    $service = app(PlacesService::class);
    $results = $service->textSearch('pizza');

    expect($results[0]['price_level'])->toBe(2);
});

it('maps PRICE_LEVEL_INEXPENSIVE to 1', function () {
    $response = textSearchResponse([[
        'id' => 'p1',
        'displayName' => ['text' => 'Cheap Eats'],
        'formattedAddress' => '1 A St',
        'types' => ['restaurant'],
        'rating' => 4.0,
        'location' => ['latitude' => 41.0, 'longitude' => -93.0],
        'priceLevel' => 'PRICE_LEVEL_INEXPENSIVE',
    ]]);

    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response($response, 200),
    ]);

    $service = app(PlacesService::class);
    $results = $service->textSearch('cheap');

    expect($results[0]['price_level'])->toBe(1);
});

it('maps PRICE_LEVEL_MODERATE to 2', function () {
    $response = textSearchResponse([[
        'id' => 'p2',
        'displayName' => ['text' => 'Mid Range'],
        'formattedAddress' => '2 B St',
        'types' => ['restaurant'],
        'rating' => 4.0,
        'location' => ['latitude' => 41.0, 'longitude' => -93.0],
        'priceLevel' => 'PRICE_LEVEL_MODERATE',
    ]]);

    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response($response, 200),
    ]);

    $service = app(PlacesService::class);
    $results = $service->textSearch('mid');

    expect($results[0]['price_level'])->toBe(2);
});

it('maps PRICE_LEVEL_EXPENSIVE to 3', function () {
    $response = textSearchResponse([[
        'id' => 'p3',
        'displayName' => ['text' => 'Fancy Place'],
        'formattedAddress' => '3 C St',
        'types' => ['restaurant'],
        'rating' => 4.5,
        'location' => ['latitude' => 41.0, 'longitude' => -93.0],
        'priceLevel' => 'PRICE_LEVEL_EXPENSIVE',
    ]]);

    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response($response, 200),
    ]);

    $service = app(PlacesService::class);
    $results = $service->textSearch('fancy');

    expect($results[0]['price_level'])->toBe(3);
});

it('maps PRICE_LEVEL_VERY_EXPENSIVE to 4', function () {
    $response = textSearchResponse([[
        'id' => 'p4',
        'displayName' => ['text' => 'Ultra Fine'],
        'formattedAddress' => '4 D St',
        'types' => ['restaurant'],
        'rating' => 4.8,
        'location' => ['latitude' => 41.0, 'longitude' => -93.0],
        'priceLevel' => 'PRICE_LEVEL_VERY_EXPENSIVE',
    ]]);

    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response($response, 200),
    ]);

    $service = app(PlacesService::class);
    $results = $service->textSearch('ultra');

    expect($results[0]['price_level'])->toBe(4);
});

it('returns null price_level when priceLevel is absent', function () {
    $response = textSearchResponse([[
        'id' => 'p5',
        'displayName' => ['text' => 'No Price'],
        'formattedAddress' => '5 E St',
        'types' => ['restaurant'],
        'rating' => 3.5,
        'location' => ['latitude' => 41.0, 'longitude' => -93.0],
    ]]);

    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response($response, 200),
    ]);

    $service = app(PlacesService::class);
    $results = $service->textSearch('no price');

    expect($results[0]['price_level'])->toBeNull();
});

it('returns null lat and lng when location is absent', function () {
    $response = textSearchResponse([[
        'id' => 'p6',
        'displayName' => ['text' => 'No Location'],
        'formattedAddress' => '6 F St',
        'types' => ['restaurant'],
        'rating' => 3.5,
        'priceLevel' => 'PRICE_LEVEL_MODERATE',
    ]]);

    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response($response, 200),
    ]);

    $service = app(PlacesService::class);
    $results = $service->textSearch('no location');

    expect($results[0]['lat'])->toBeNull()
        ->and($results[0]['lng'])->toBeNull();
});

it('maps PRICE_LEVEL_FREE to null price_level', function () {
    $response = textSearchResponse([[
        'id' => 'p7',
        'displayName' => ['text' => 'Free Place'],
        'formattedAddress' => '7 G St',
        'types' => ['restaurant'],
        'rating' => 3.0,
        'location' => ['latitude' => 41.0, 'longitude' => -93.0],
        'priceLevel' => 'PRICE_LEVEL_FREE',
    ]]);

    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response($response, 200),
    ]);

    $service = app(PlacesService::class);
    $results = $service->textSearch('free');

    expect($results[0]['price_level'])->toBeNull();
});

it('still returns id, name, address, types, and rating', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText*' => Http::response(textSearchResponse(), 200),
    ]);

    $service = app(PlacesService::class);
    $results = $service->textSearch('pizza');

    expect($results[0])
        ->toHaveKey('id', 'place_abc123')
        ->toHaveKey('name', 'The Rusty Fork')
        ->toHaveKey('address', '123 Main St, Des Moines, IA')
        ->toHaveKey('types', ['restaurant', 'food'])
        ->toHaveKey('rating', 4.3);
});
