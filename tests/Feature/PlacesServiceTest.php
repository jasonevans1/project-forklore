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
