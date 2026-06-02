# Task 001: Extend PlacesService textSearch with lat/lng and price level

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Update `PlacesService::textSearch()` to include `places.location` and `places.priceLevel` in its Google Places API field mask, and update `parsePlaces()` to expose `lat`, `lng`, and `price_level` in each returned result. This gives the create-page component the coordinates and price level it needs to pre-fill the form without a second API call.

## Context
- Related files: `app/Services/PlacesService.php`
- `textSearch()` currently uses field mask: `places.id,places.displayName,places.formattedAddress,places.types,places.rating`
- `parsePlaces()` currently returns: `id, name, address, types, rating`
- Google Places API (v1) returns location as `location.latitude` / `location.longitude` and price as `priceLevel` (string enum: `PRICE_LEVEL_FREE`, `PRICE_LEVEL_INEXPENSIVE`, `PRICE_LEVEL_MODERATE`, `PRICE_LEVEL_EXPENSIVE`, `PRICE_LEVEL_VERY_EXPENSIVE`)
- Map `priceLevel` to an integer 1–4 matching the app's `price_level` column convention (FREE → null, INEXPENSIVE → 1, MODERATE → 2, EXPENSIVE → 3, VERY_EXPENSIVE → 4, and any unrecognized/missing value → null)
- The existing `nearbySearch()` method has its own field mask which you must NOT change. However, **`parsePlaces()` is shared by both `textSearch()` and `nearbySearch()`**. Adding lat/lng/price_level to `parsePlaces()` means nearby results will also gain these keys (populated as null because the nearby field mask omits them). This is acceptable — verify the existing nearby tests still pass (they only assert `id` and `name`, not exact array shape). Do not add a separate parser unless a nearby test breaks.
- The existing `textSearchResponse()` helper in `tests/Feature/PlacesServiceTest.php` does NOT include `location` or `priceLevel`. Update this helper (or add a parameterized variant) so the new assertions have data to read, while keeping the existing tests green. Add `location` => `['latitude' => ..., 'longitude' => ...]` and `priceLevel` => `'PRICE_LEVEL_MODERATE'` (etc.) to the fixture places as needed.
- The Google `location` object uses keys `latitude` / `longitude`. Map them to `lat` / `lng` in the parsed result. Default to null when `location` is absent.

## Requirements (Test Descriptions)
- [x] `it includes latitude in parsed text search results`
- [x] `it includes longitude in parsed text search results`
- [x] `it includes price_level as an integer in parsed text search results`
- [x] `it maps PRICE_LEVEL_INEXPENSIVE to 1`
- [x] `it maps PRICE_LEVEL_MODERATE to 2`
- [x] `it maps PRICE_LEVEL_EXPENSIVE to 3`
- [x] `it maps PRICE_LEVEL_VERY_EXPENSIVE to 4`
- [x] `it returns null price_level when priceLevel is absent`
- [x] `it returns null lat and lng when location is absent`
- [x] `it maps PRICE_LEVEL_FREE to null price_level`
- [x] `it still returns id, name, address, types, and rating`

## Acceptance Criteria
- All requirements have passing tests
- The `nearbySearch()` and `placeDetails()` method bodies (their field masks and request payloads) are untouched. `parsePlaces()` may be modified since it is shared.
- The full existing `PlacesServiceTest.php` suite still passes (including the nearby and cache tests)
- No real HTTP calls in tests — use `Http::fake()` / `Http::preventStrayRequests()` per the existing test conventions
- Pint reports no style issues
