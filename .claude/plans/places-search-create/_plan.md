# Plan: Places Search on Restaurant Create Page

## Created
2026-06-01

## Status
completed

## Objective
Add a "Search Google" tab to the restaurant create page so users can search for real restaurants via the Google Places API and have name, address, cuisine tags, price level, and coordinates pre-filled before completing the form manually.

## Related Issues
none

## Discovery Notes
- `PlacesService::textSearch()` exists and works but its field mask omits `places.location` and `places.priceLevel` — these need adding. `parsePlaces()` is shared with `nearbySearch()`, so changes there affect both (benign: nearby gains null lat/lng/price_level).
- `textSearch()` returns `null` (not `[]`) when the quota is exceeded or the API key is missing — callers must handle null.
- `places_id` column already exists on `restaurants` (fillable, **globally unique**, nullable) — schema is ready, but the unique index means duplicate inserts throw and must be guarded.
- `RestaurantSource::Places` enum value already exists. Restaurants created via this create page stay `source = Favorite` (the user is curating a favorite); `places_id` is stored independently of source.
- The create page is a Volt SFC (`resources/views/pages/restaurants/⚡create.blade.php`), tested via `Livewire::test('pages::restaurants.create')`, with a shared `x-restaurants.form-fields` blade partial and a `VibePicker` child component.
- The existing `save()` method hardcodes `source => Favorite`, `lat => null`, `lng => null` and does NOT include `places_id` — it must be modified for the search pre-fill to actually persist.
- Volt SFCs resolve services via method-injection on the action signature (e.g. `search(PlacesService $places)`), not constructor injection.
- Search fires on button click (not real-time) to protect the 100/day quota cap.
- Google `types` (e.g. `italian_restaurant`, `pizza`) are auto-mapped to cuisine_tags; noise types (`food`, `restaurant`, `establishment`, `point_of_interest`) are stripped.

## Scope

### In Scope
- Extend `PlacesService::textSearch()` field mask to return lat, lng, price_level
- "Search Google" tab on the create page with a text input and search button
- Results list showing name + address as selectable cards
- Selecting a result pre-fills name, address, cuisine_tags (mapped), price_level, lat, lng, places_id and switches to the manual tab
- Update the create page `save()` method to persist `lat`, `lng`, and `places_id` (currently hardcoded to null / omitted)
- Guard `save()` against the global unique `places_id` constraint (friendly validation error instead of a 500)
- Empty-state message when search returns no results (treat both `[]` and `null` returns as no results)

### Out of Scope
- Real-time / debounced search
- Fetching additional detail via `placeDetails()` on selection
- Duplicate detection (alerting the user if the place already exists as a favorite)
- Editing the search-prefilled fields before they are shown

## Success Criteria
- [ ] `PlacesService::textSearch()` returns lat, lng, and price_level in every parsed result
- [ ] Search tab is visible on the create page and fires a Places search on button click
- [ ] Selecting a result pre-fills all mappable form fields and switches to the manual tab
- [ ] Saving after a search persists `lat`, `lng`, and `places_id` to the database
- [ ] A duplicate `places_id` yields a validation error, not a 500
- [ ] Google types are mapped to cuisine tags with noise types removed
- [ ] All new tests pass and all existing `RestaurantCreateTest` / `PlacesServiceTest` tests still pass
- [ ] Code follows project standards (Pint clean)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Extend PlacesService textSearch with lat/lng and price level | - | completed |
| 002 | Add Places search tab to the restaurant create page | 001 | completed |

## Architecture Notes
- Keep the cuisine-type mapping as a private method on the create page component — it is UI-adjacent logic that doesn't warrant a standalone class.
- The `selectPlace()` Livewire action handles pre-fill and tab switch in one shot; no intermediate state needed.
- `places_id` is stored so the record can be correlated back to Google later (e.g. for event lookups).

## Risks & Mitigations
- Quota burn during testing: quota is per calendar day and capped at 100 — fake `PlacesService` in component tests and use `Http::fake()` in service tests; never make real HTTP calls.
- Type noise in cuisine mapping: strip well-known noise types and lowercase everything so tags are consistent with the existing tagging convention.
- Dropped data: the existing `save()` discards `lat`/`lng`/`places_id`; Task 002 must update it or the search pre-fill silently loses those fields. Covered by a save-path test.
- Duplicate places_id: the column is globally unique; selecting an already-saved place would throw a 500. Task 002 guards with a pre-insert existence check and a friendly validation error.
- Empty cuisine mapping: a Places result whose types are all noise yields an empty `cuisine_tags`; the existing required-validation will force the user to fill it manually (acceptable, no special handling needed).
