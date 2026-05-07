<?php

use App\Services\WeatherData;
use App\Services\WeatherService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.openweather.key' => 'test-api-key']);
    Cache::flush();
});

function makeWeatherResponse(array $overrides = []): array
{
    return array_merge([
        'main' => ['temp' => 18.5],
        'weather' => [['main' => 'Clear']],
        'rain' => ['1h' => 0.5],
        'wind' => ['speed' => 3.2],
        'sys' => ['sunset' => 1746561600],
    ], $overrides);
}

it('returns a WeatherData instance with correct temperature from a mocked API response', function () {
    Http::fake([
        'api.openweathermap.org/*' => Http::response(makeWeatherResponse(['main' => ['temp' => 18.5]]), 200),
    ]);

    $service = app(WeatherService::class);
    $result = $service->fetch(37.77, -122.42);

    expect($result)->toBeInstanceOf(WeatherData::class)
        ->and($result->temperature)->toBe(18.5);
});

it('falls back to snow.1h when rain.1h is missing, and to 0.0 when both are missing', function () {
    $service = app(WeatherService::class);

    $snowResponse = [
        'main' => ['temp' => 2.0],
        'weather' => [['main' => 'Snow']],
        'snow' => ['1h' => 1.2],
        'wind' => ['speed' => 1.0],
        'sys' => ['sunset' => 1746561600],
    ];
    $noPercipResponse = [
        'main' => ['temp' => 10.0],
        'weather' => [['main' => 'Clear']],
        'wind' => ['speed' => 2.0],
        'sys' => ['sunset' => 1746561600],
    ];

    Http::fake([
        'api.openweathermap.org/*' => Http::sequence()
            ->push($snowResponse, 200)
            ->push($noPercipResponse, 200),
    ]);

    // First call: snow fallback (no rain key)
    expect($service->fetch(37.77, -122.42)->precipitation)->toBe(1.2);

    // Second call: different coords to avoid cache, no rain or snow
    expect($service->fetch(40.71, -74.01)->precipitation)->toBe(0.0);
});

it('caches successful results for 60 minutes so a second call does not make another HTTP request', function () {
    Http::fake([
        'api.openweathermap.org/*' => Http::response(makeWeatherResponse(), 200),
    ]);

    $service = app(WeatherService::class);
    $service->fetch(37.77, -122.42);
    $service->fetch(37.77, -122.42);

    Http::assertSentCount(1);
});

it('does NOT cache null results — a failure followed by a success returns the success', function () {
    Http::fake([
        'api.openweathermap.org/*' => Http::sequence()
            ->push([], 500)
            ->push(makeWeatherResponse(), 200),
    ]);

    $service = app(WeatherService::class);
    $firstResult = $service->fetch(37.77, -122.42);
    expect($firstResult)->toBeNull();

    $secondResult = $service->fetch(37.77, -122.42);
    expect($secondResult)->toBeInstanceOf(WeatherData::class);
});

it('uses a cache key derived from lat and lng formatted to 2 decimal places (e.g. weather:37.77:-122.42)', function () {
    Http::fake([
        'api.openweathermap.org/*' => Http::response(makeWeatherResponse(), 200),
    ]);

    $service = app(WeatherService::class);
    $service->fetch(37.77, -122.42);

    expect(Cache::has('weather:37.77:-122.42'))->toBeTrue();
});

it('returns null when the API responds with a non-2xx status code', function () {
    Http::fake([
        'api.openweathermap.org/*' => Http::response([], 503),
    ]);

    $service = app(WeatherService::class);
    $result = $service->fetch(37.77, -122.42);

    expect($result)->toBeNull();
});

it('returns null when the HTTP request throws a connection exception', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $service = app(WeatherService::class);
    $result = $service->fetch(37.77, -122.42);

    expect($result)->toBeNull();
});

it('returns null without making an HTTP request when the OpenWeather API key is not configured', function () {
    config(['services.openweather.key' => null]);

    $service = app(WeatherService::class);
    $result = $service->fetch(37.77, -122.42);

    expect($result)->toBeNull();
    Http::assertNothingSent();
});

it('returns null when the API response contains an empty weather array', function () {
    Http::fake([
        'api.openweathermap.org/*' => Http::response(makeWeatherResponse(['weather' => []]), 200),
    ]);

    $service = app(WeatherService::class);
    $result = $service->fetch(37.77, -122.42);

    expect($result)->toBeNull();
});

it('returns null without making an HTTP request when the daily quota of 1000 calls has been reached', function () {
    $quotaKey = 'openweather_quota:'.now()->toDateString();
    Cache::put($quotaKey, 1000, 172800);

    $service = app(WeatherService::class);
    $result = $service->fetch(37.77, -122.42);

    expect($result)->toBeNull();
    Http::assertNothingSent();
});

it('increments the daily quota counter on each real API call', function () {
    Http::fake([
        'api.openweathermap.org/*' => Http::response(makeWeatherResponse(), 200),
    ]);

    $service = app(WeatherService::class);
    $service->fetch(37.77, -122.42);

    $quotaKey = 'openweather_quota:'.now()->toDateString();
    expect(Cache::get($quotaKey))->toBe(1);
});

it('returns a WeatherData instance with correct conditions, precipitation, wind, sunset, and units', function () {
    $sunsetTimestamp = 1746561600;
    Http::fake([
        'api.openweathermap.org/*' => Http::response(makeWeatherResponse([
            'weather' => [['main' => 'Rain']],
            'rain' => ['1h' => 2.5],
            'wind' => ['speed' => 5.0],
            'sys' => ['sunset' => $sunsetTimestamp],
        ]), 200),
    ]);

    $service = app(WeatherService::class);
    $result = $service->fetch(37.77, -122.42);

    expect($result->conditions)->toBe('Rain')
        ->and($result->precipitation)->toBe(2.5)
        ->and($result->windSpeed)->toBe(5.0)
        ->and($result->sunset)->toBeInstanceOf(CarbonImmutable::class)
        ->and($result->sunset->timestamp)->toBe($sunsetTimestamp)
        ->and($result->sunset->utcOffset())->toBe(0)
        ->and($result->units)->toBe('metric');
});
