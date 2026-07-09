# Task 002: Factory Generates Realistic Combinations

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Update `RestaurantFactory` to populate the three new fields with realistic combinations — service options that make sense for the service level (e.g., fine dining never has a drive-thru; every restaurant offers dine-in or takeout).

## Context
- Related files: `database/factories/RestaurantFactory.php`, `tests/Feature/RestaurantFactoryTest.php`
- Patterns to follow: existing `definition()` uses `fake()->randomElement(...)`
- Run all php/artisan commands via `ddev exec`
- Realism rules (keep it simple — a match statement on the picked ServiceLevel choosing from level-appropriate option sets):
  - `fast_food`: always `takeout`, often `drive_thru`/`delivery`/`dine_in`
  - `fast_casual` / `casual`: always `dine_in`, often `takeout`/`delivery`/`curbside`, never `drive_thru` for casual
  - `upscale_casual`: `dine_in`, sometimes `takeout`; never `drive_thru`
  - `fine_dining`: `dine_in` only (never `drive_thru`, `delivery`, or `curbside`)
- `primary_cuisine`: any random `PrimaryCuisine` case is fine (independent of level)

## Requirements (Test Descriptions)
- [x] `it generates a valid service_level for factory restaurants`
- [x] `it generates a non-empty array of valid service options for factory restaurants`
- [x] `it generates a valid primary_cuisine for factory restaurants`
- [x] `it never generates drive_thru for fine dining or upscale casual restaurants`
- [x] `it always includes dine_in for fine dining restaurants`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards (Pint clean)
- No decrease in test coverage

## Implementation Notes
- `definition()` picks a random `ServiceLevel`, then derives `service_options` via a private `serviceOptionsFor()` match statement (guaranteed anchors per level: takeout for fast_food, dine_in for fast_casual/casual/upscale_casual/fine_dining), plus a random `PrimaryCuisine`.
- Added a `withServiceLevel(ServiceLevel $serviceLevel)` factory state so tests (and future callers) can force a specific level and get correctly-derived `service_options` in the same call — overriding `service_level` alone via `make(['service_level' => ...])` would not regenerate options.
- Requirements 2, 3, and 5 passed immediately once the level/options/cuisine generation was implemented for requirement 1 — no over-implementation, just shared derivation logic satisfying multiple requirements at once.
