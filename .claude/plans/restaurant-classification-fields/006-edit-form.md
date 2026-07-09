# Task 006: Edit Form Pre-fill and Update

**Status**: completed
**Depends on**: 001, 004
**Retry count**: 0

## Description
Wire the three classification fields through the edit form: component properties, `mount()` pre-fill from the existing restaurant, validation rules (same as create), and the `update()` call. The shared form-fields partial from task 004 already renders the inputs.

## Context
- Related files: `resources/views/pages/restaurants/⚡edit.blade.php`, `tests/Feature/RestaurantEditTest.php`
- Patterns to follow: how `mount()` pre-fills `patio_quality` (`$restaurant->patio_quality->value`) — enum casts need `?->value` since the new fields are nullable; `service_options` pre-fills as `$restaurant->service_options ?? []`
- Run all php/artisan commands via `ddev exec`
- Validation rules identical to create (task 004): `nullable` + `Rule::enum(...)`; `service_options` as nullable array with per-element enum rule
- The edit component's save method is `save()`, which calls `$restaurant->update([...])`. Add the three fields to that `update()` payload.
- Persist all three, normalizing empty strings to null (same empty-string enum-cast crash as create — see task 004): `service_level` => `$this->service_level ?: null`, `service_options` => `$this->service_options ?: null`, `primary_cuisine` => `$this->primary_cuisine ?: null`
- Component property types match create: `public ?string $service_level = null`, `public array $service_options = []`, `public ?string $primary_cuisine = null`; `mount()` pre-fills `$this->service_level = $restaurant->service_level?->value` (nullable → `?->value`), `$this->service_options = $restaurant->service_options ?? []`, `$this->primary_cuisine = $restaurant->primary_cuisine?->value`

## Requirements (Test Descriptions)
- [x] `it pre-fills service_level when editing a restaurant`
- [x] `it pre-fills service_options when editing a restaurant`
- [x] `it pre-fills primary_cuisine when editing a restaurant`
- [x] `it updates the classification fields on save`
- [x] `it pre-fills empty classification fields without errors`
- [x] `it clears classification fields to null when the selects are set blank on edit` (create a restaurant with values, then set `service_level`/`primary_cuisine` to `''` and `service_options` to `[]`, save, assert no errors and columns persist as null — proves the empty-string `?: null` guard)
- [x] `it rejects an invalid service_level when editing`

## Acceptance Criteria
- All requirements have passing tests
- Editing a restaurant created before this feature (all three fields null) works without errors
- Code follows code standards (Pint clean)

## Implementation Notes
Followed the create page pattern exactly: three nullable component properties, `mount()` pre-fill with `?->value` / `?? []`, validation rules identical to create, and `service_level ?: null` / `service_options ?: null` / `primary_cuisine ?: null` normalization in the `update()` payload. All 7 new tests pass; full RestaurantEditTest (26) and RestaurantCreateTest (22) suites pass; Pint clean.
