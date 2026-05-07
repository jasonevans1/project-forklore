# Plan: Weather Service

## Created
2026-05-06

## Status
completed

## Objective
Implement a `WeatherService` that fetches current conditions (temp, precipitation, wind, sunset) from OpenWeather's free tier for a given lat/lng, caches results for 60 minutes, and returns null weather data on any API failure.

## Related Issues
none

## Scope

### In Scope
- `WeatherService` class in `app/Services/` with signature `fetch(float $lat, float $lng): ?WeatherData`
- A `WeatherData` readonly DTO holding the five fields (temperature, conditions, precipitation, wind speed, sunset) plus a `units` string field (`'metric'` | `'imperial'` | `'standard'`) so callers know how to interpret raw values
- OpenWeather API key config entry (`config/services.php`) plus `.env` and `.env.example` updates
- 60-minute result cache keyed by lat/lng formatted to 2 decimal places via `number_format` (stable string keys)
- Daily call quota counter (max 1,000/day, free tier) stored in cache; return `null` without hitting the API when the limit is reached
- Successful results are cached; null/failure results are NOT cached so retries can recover
- Explicit 5s HTTP timeout; short-circuit to null when API key is unset
- Graceful null-fallback on HTTP errors, connection exceptions, malformed payloads, or empty `weather[]` array
- Pest feature test using `Http::fake()` + `Http::preventStrayRequests()` covering parsing, caching, fallback, quota, and edge cases

### Out of Scope
- UI integration (Quick Pick mode wiring)
- Forecast / hourly data
- Unit-level location resolution (geocoding)

## Success Criteria
- [ ] `WeatherService::fetch(float $lat, float $lng): ?WeatherData` returns a populated `WeatherData` on success
- [ ] Returns `null` on non-2xx response, connection exception, malformed JSON, empty `weather[]` array, or missing API key
- [ ] Returns `null` without making an HTTP call when the daily quota (1,000 calls) has been reached
- [ ] Daily call counter increments on each real API call; stored in cache keyed by date
- [ ] Successful results cached for 60 minutes; a second call hits the cache, not the HTTP layer
- [ ] Failures are NOT cached (so a transient OpenWeather outage clears on the next call)
- [ ] Cache key uses lat/lng formatted to 2 decimal places via `number_format` (e.g. `weather:37.77:-122.42`)
- [ ] HTTP requests use an explicit 5s timeout
- [ ] Sunset is a `CarbonImmutable` in UTC
- [ ] `WeatherData` carries a `units` field (`'metric'` | `'imperial'` | `'standard'`) alongside raw numeric values
- [ ] All tests passing (run via `ddev exec php artisan test --compact --parallel`)
- [ ] Code follows project standards (Pint clean via `ddev exec vendor/bin/pint --dirty --format agent`)
- [ ] `.env.example` updated with `OPENWEATHER_API_KEY=`

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | WeatherService — config, DTO, service, and tests | - | completed |

## Architecture Notes
- Service lives in `app/Services/WeatherService.php` per the architecture pattern
- `app/Services/` directory does not yet exist — the implementer must create it
- Use `WeatherData` as a PHP 8.4 `readonly` class in `app/Services/WeatherData.php`
- HTTP calls via Laravel's `Http` facade with `->timeout(5)`; fake in tests with `Http::fake()` and lock down with `Http::preventStrayRequests()`
- Config entry: `config/services.php` key `openweather.key` (env var `OPENWEATHER_API_KEY`)
- Cache facade: success path uses manual `Cache::get` then `Cache::put`; failure path returns null without writing (NOT `Cache::remember` since that always caches the closure return including null)
- Sunset stored as `CarbonImmutable` in UTC via `CarbonImmutable::createFromTimestampUTC()`
- Daily quota counter: `Cache::increment("openweather_quota:{today}", 1)` where today = `now()->toDateString()`; set TTL to 48 h on first write to auto-expire; check before every real HTTP call
- `WeatherData` carries a `units` string field alongside numeric values — set to `'metric'` for now (hardcoded `units=metric` query param), so callers know raw temp is °C and wind is m/s
- Empty `weather[]` array in the response is treated the same as a malformed payload: return `null`
- All artisan/composer/pint commands run inside ddev: prefix every shell command with `ddev exec`

## Risks & Mitigations
- OpenWeather free tier rate limits: mitigated by 60-min cache + rounded key reducing unique calls
- API shape changes: isolated in one parsing method, easy to update
- Stale-failure cache: avoided by skipping cache writes on null/failure paths
- Float-to-string drift in cache keys: mitigated by `number_format($v, 2, '.', '')` for stable, locale-agnostic keys
