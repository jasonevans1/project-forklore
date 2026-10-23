<?php

use App\Services\PlacesService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
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
    Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function pmQuotaKey(): string
{
    return 'google_places_quota:'.now()->toDateString();
}

function pmSeedRow(string $hash, array $payload, DateTimeInterface $fetchedAt): void
{
    DB::table('places_cache')->insert([
        'query_hash' => $hash,
        'response_json' => json_encode($payload),
        'fetched_at' => $fetchedAt->format('Y-m-d H:i:s'),
    ]);
}

it('sends api key, field mask and query in the text search request', function () {
    Http::fake(['*' => Http::response(['places' => []])]);

    (new PlacesService)->textSearch('pizza');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://places.googleapis.com/v1/places:searchText'
            && $request->header('X-Goog-Api-Key') === ['test-places-key']
            && $request->header('X-Goog-FieldMask') === ['places.id,places.displayName,places.formattedAddress,places.types,places.rating,places.location,places.priceLevel']
            && $request->data() === ['textQuery' => 'pizza'];
    });
});

it('sends the full nearby search request with default radius', function () {
    Http::fake(['*' => Http::response(['places' => []])]);

    (new PlacesService)->nearbySearch(41.5, -93.6);

    Http::assertSent(function (Request $request) {
        $data = $request->data();

        return $request->url() === 'https://places.googleapis.com/v1/places:searchNearby'
            && $request->header('X-Goog-Api-Key') === ['test-places-key']
            && $request->header('X-Goog-FieldMask') === ['places.id,places.displayName,places.formattedAddress,places.types,places.rating']
            && $data['includedTypes'] === ['restaurant']
            && $data['maxResultCount'] === 20
            && $data['locationRestriction']['circle']['center'] === ['latitude' => 41.5, 'longitude' => -93.6]
            && is_float($data['locationRestriction']['circle']['radius'])
            && $data['locationRestriction']['circle']['radius'] === 1500.0;
    });
});

it('sends api key and field mask in the place details request', function () {
    Http::fake(['*' => Http::response(['id' => 'abc'])]);

    (new PlacesService)->placeDetails('abc');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://places.googleapis.com/v1/places/abc'
            && $request->header('X-Goog-Api-Key') === ['test-places-key']
            && $request->header('X-Goog-FieldMask') === ['id,displayName,formattedAddress,types,rating,regularOpeningHours,websiteUri'];
    });
});

it('returns null and caches nothing on failed responses', function () {
    Http::fake(['*' => Http::response([], 500)]);
    $service = new PlacesService;

    expect($service->textSearch('x'))->toBeNull()
        ->and($service->nearbySearch(1.0, 2.0))->toBeNull()
        ->and($service->placeDetails('x'))->toBeNull()
        ->and(DB::table('places_cache')->count())->toBe(0)
        ->and(Cache::get(pmQuotaKey()))->toBeNull();
});

it('returns null without http calls when the api key is missing for nearby and details', function () {
    config(['services.google_places.key' => null]);
    $service = new PlacesService;

    expect($service->nearbySearch(1.0, 2.0))->toBeNull()
        ->and($service->placeDetails('x'))->toBeNull();
});

it('returns null without http calls when quota is exceeded for nearby and details', function () {
    Cache::put(pmQuotaKey(), 100);
    $service = new PlacesService;

    expect($service->nearbySearch(1.0, 2.0))->toBeNull()
        ->and($service->placeDetails('x'))->toBeNull();
});

it('increments quota after nearby and details calls', function () {
    Http::fake(['*' => Http::response(['places' => [], 'id' => 'x'])]);
    $service = new PlacesService;

    $service->nearbySearch(1.0, 2.0);
    expect((int) Cache::get(pmQuotaKey()))->toBe(1);

    $service->placeDetails('x');
    expect((int) Cache::get(pmQuotaKey()))->toBe(2);
});

it('expires the quota counter after two days', function () {
    Http::fake(['*' => Http::response(['places' => []])]);
    (new PlacesService)->textSearch('q');

    Carbon::setTestNow(now()->addDays(2)->subSecond());
    expect(Cache::has('google_places_quota:2026-03-10'))->toBeTrue();

    Carbon::setTestNow(now()->addSeconds(2));
    expect(Cache::has('google_places_quota:2026-03-10'))->toBeFalse();
});

it('keeps a stored response in the cache for exactly seven days', function () {
    Http::fake(['*' => Http::response(['places' => []])]);
    (new PlacesService)->textSearch('q');
    $key = 'places:'.hash('sha256', 'text_search:q');

    Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00')->addDays(7)->subSecond());
    expect(Cache::has($key))->toBeTrue();

    Carbon::setTestNow(now()->addSeconds(2));
    expect(Cache::has($key))->toBeFalse();
});

it('updates response_json and fetched_at when a stale row is refreshed', function () {
    $hash = hash('sha256', 'text_search:q');
    pmSeedRow($hash, ['places' => [['id' => 'old']]], now()->subDays(10));
    Http::fake(['*' => Http::response(['places' => [['id' => 'new']]])]);

    $result = (new PlacesService)->textSearch('q');

    $row = DB::table('places_cache')->where('query_hash', $hash)->first();
    expect($result[0]['id'])->toBe('new')
        ->and(json_decode($row->response_json, true)['places'][0]['id'])->toBe('new')
        ->and($row->fetched_at)->toBe(now()->toDateTimeString());
});

it('serves a 6.5 day old db row and warms the cache', function () {
    $hash = hash('sha256', 'text_search:q');
    pmSeedRow($hash, ['places' => [['id' => 'db']]], now()->subDays(6)->subHours(12));

    $result = (new PlacesService)->textSearch('q');

    expect($result[0]['id'])->toBe('db')
        ->and(Cache::get("places:{$hash}"))->toBe(['places' => [['id' => 'db']]]);
});

it('ignores a 7.5 day old db row', function () {
    $hash = hash('sha256', 'text_search:q');
    pmSeedRow($hash, ['places' => [['id' => 'db']]], now()->subDays(7)->subHours(12));
    Http::fake(['*' => Http::response(['places' => [['id' => 'fresh']]])]);

    expect((new PlacesService)->textSearch('q')[0]['id'])->toBe('fresh');
});

it('serves a response from the laravel cache without touching the db', function () {
    $hash = hash('sha256', 'text_search:q');
    Cache::put("places:{$hash}", ['places' => [['id' => 'cached']]]);

    expect((new PlacesService)->textSearch('q')[0]['id'])->toBe('cached');
});

it('defaults the daily quota cap to 1000', function () {
    config(['services.google_places' => ['key' => 'test-places-key']]);
    Http::fake(['*' => Http::response(['places' => []])]);
    $service = new PlacesService;

    Cache::put(pmQuotaKey(), 999);
    expect($service->textSearch('a'))->toBe([]);

    Cache::put(pmQuotaKey(), 1000);
    expect($service->textSearch('b'))->toBeNull();
});

it('treats a missing usage counter as zero', function () {
    Http::fake(['*' => Http::response(['places' => []])]);

    config(['services.google_places.daily_quota' => 1]);
    expect((new PlacesService)->textSearch('a'))->toBe([]);

    Cache::flush();
    config(['services.google_places.daily_quota' => 0]);
    expect((new PlacesService)->textSearch('b'))->toBeNull();
});

it('casts non numeric quota values to integers', function () {
    Http::fake(['*' => Http::response(['places' => []])]);

    Cache::put(pmQuotaKey(), 'abc');
    expect((new PlacesService)->textSearch('a'))->toBe([]);

    Cache::flush();
    config(['services.google_places.daily_quota' => 'abc']);
    expect((new PlacesService)->textSearch('b'))->toBeNull();
});

it('parses every field of place details', function () {
    Http::fake(['*' => Http::response([
        'id' => 'p1',
        'displayName' => ['text' => 'Fork'],
        'formattedAddress' => '1 Main St',
        'types' => ['italian_restaurant'],
        'rating' => 4.5,
        'regularOpeningHours' => ['weekdayDescriptions' => ['Mon: 9-5']],
        'websiteUri' => 'https://fork.test',
    ])]);

    expect((new PlacesService)->placeDetails('p1'))->toBe([
        'id' => 'p1',
        'name' => 'Fork',
        'address' => '1 Main St',
        'types' => ['italian_restaurant'],
        'rating' => 4.5,
        'opening_hours' => ['Mon: 9-5'],
        'website' => 'https://fork.test',
    ]);
});
