# Task 001: WeatherService — config, DTO, service, and tests

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `WeatherService` and `WeatherData` DTO, wire up the OpenWeather API key config, and verify all behavior with a Pest feature test using `Http::fake()`.

## Context
- Related files to create:
  - `app/Services/WeatherData.php` — readonly DTO
  - `app/Services/WeatherService.php` — service class
  - `tests/Feature/WeatherServiceTest.php` — Pest feature test
- Related files to modify:
  - `config/services.php` — add `openweather` key entry (`'openweather' => ['key' => env('OPENWEATHER_API_KEY')]`)
  - `.env` — add `OPENWEATHER_API_KEY=`
  - `.env.example` — add `OPENWEATHER_API_KEY=` (required; do not skip — keeps developer onboarding consistent)
- OpenWeather "Current weather data" endpoint (free tier):
  `https://api.openweathermap.org/data/2.5/weather?lat={lat}&lon={lng}&appid={key}&units=metric`
- Response fields to map:
  - `main.temp` → temperature (float, °C)
  - `weather[0].main` → conditions (string, e.g. "Rain"); guard against an empty `weather` array
  - Precipitation (float mm/h, default `0.0`): use `rain.1h` if present, else `snow.1h`, else `0.0`
  - `wind.speed` → wind speed (float m/s)
  - `sys.sunset` → sunset Unix timestamp; convert with `CarbonImmutable::createFromTimestampUTC($value)` so the timezone is explicit (UTC). Callers convert to a local zone as needed.
  - `units` field: hardcoded `'metric'` (matching the `units=metric` query param) — store it on the DTO so callers know how to interpret the raw numeric values (°C, m/s)
  - Empty `weather[]` array: treat as a malformed/invalid response and return `null`
- Service signature:
  - `public function fetch(float $lat, float $lng): ?WeatherData`
- Cache:
  - Result key pattern: `weather:{lat_str}:{lng_str}` where `lat_str` / `lng_str` are produced via `number_format($value, 2, '.', '')` (NOT raw `round()` — guarantees stable string like `"0.10"` regardless of float-to-string locale, and disambiguates `0.1` vs `0.10`).
  - Result TTL: 3600 seconds (60 minutes). Use explicit `Cache::get` then `Cache::put` on success; do NOT use `Cache::remember` as it always caches, including `null`.
  - On API failure, do NOT cache the null result — the next caller should be allowed to retry.
  - Daily quota counter key: `openweather_quota:{YYYY-MM-DD}` (e.g. `openweather_quota:2026-05-06`). Use `Cache::increment()` on each real HTTP call. On first write set TTL to 172800 (48 h) so the key auto-expires. If the counter is already ≥ 1000 before the call, return `null` without hitting the API.
- HTTP client requirements:
  - Apply an explicit timeout to avoid blocking the request lifecycle: `Http::timeout(5)->get(...)`.
  - If `config('services.openweather.key')` is empty/null, short-circuit and return `null` without making an HTTP call (defensive — prevents 401 spam against OpenWeather).
- Patterns to follow: service pattern per architecture.md; `Http` facade and `Cache` facade.

## Requirements (Test Descriptions)
- [ ] `it returns a WeatherData instance with correct temperature from a mocked API response`
- [ ] `it returns a WeatherData instance with correct conditions, precipitation, wind, sunset, and units`
- [ ] `it falls back to snow.1h when rain.1h is missing, and to 0.0 when both are missing`
- [ ] `it caches successful results for 60 minutes so a second call does not make another HTTP request`
- [ ] `it does NOT cache null results — a failure followed by a success returns the success`
- [ ] `it uses a cache key derived from lat and lng formatted to 2 decimal places (e.g. weather:37.77:-122.42)`
- [ ] `it returns null when the API responds with a non-2xx status code`
- [ ] `it returns null when the HTTP request throws a connection exception`
- [ ] `it returns null without making an HTTP request when the OpenWeather API key is not configured`
- [ ] `it returns null when the API response contains an empty weather array`
- [ ] `it returns null without making an HTTP request when the daily quota of 1000 calls has been reached`
- [ ] `it increments the daily quota counter on each real API call`

### Test setup notes
- Use `Http::preventStrayRequests()` in `beforeEach` so any unmocked call fails loudly rather than silently hitting the real API.
- For the "throws a connection exception" test, fake the request with a closure that throws `Illuminate\Http\Client\ConnectionException`, e.g. `Http::fake(fn () => throw new ConnectionException('timeout'));`.
- For the "key not configured" test, override config in the test: `config(['services.openweather.key' => null]);` and assert `Http::assertNothingSent()`.
- For cache-key assertions, prefer asserting on `Cache::has('weather:37.77:-122.42')` after a call rather than reflecting on internals.
- For quota tests: seed the counter with `Cache::put("openweather_quota:" . now()->toDateString(), 1000, 172800)` to simulate a full day, then assert `Http::assertNothingSent()`. For the increment test, assert `Cache::get("openweather_quota:" . now()->toDateString()) === 1` after a successful call.

## Acceptance Criteria
- All requirements above have passing tests (12 total)
- `ddev exec php artisan test --compact --filter WeatherService` exits green
- `ddev exec vendor/bin/pint --dirty --format agent` reports no issues
- No hardcoded API key; reads from `config('services.openweather.key')`
- `.env.example` contains the new `OPENWEATHER_API_KEY=` line
- `config/services.php` contains the `openweather` block
- `WeatherService::fetch()` declares the return type `?WeatherData`
- Sunset is exposed as a `CarbonImmutable` instance in UTC (callers can localize)
- All shell commands use the `ddev exec` prefix per project convention

## Implementation Notes
(Left blank — filled in by implementer)
