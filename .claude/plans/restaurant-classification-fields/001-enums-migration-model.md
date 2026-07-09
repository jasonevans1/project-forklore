# Task 001: Create Enums, Migration, and Model Casts

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the three new enums (`ServiceLevel`, `ServiceOption`, `PrimaryCuisine`), a migration adding `service_level`, `service_options`, and `primary_cuisine` to the restaurants table, and wire the fields into the `Restaurant` model (fillable + casts). This is the foundation every other task builds on.

## Context
- Related files: `app/Models/Restaurant.php`, `app/Enums/` (new files), `database/migrations/` (new migration)
- Patterns to follow: `app/Enums/PatioQuality.php` (string-backed enum, TitleCase cases); `Restaurant` uses the `#[Fillable]` attribute and a `casts()` method
- Run all php/artisan commands via `ddev exec` (e.g., `ddev exec php artisan make:migration add_classification_fields_to_restaurants_table --no-interaction`)
- Enum cases and values:
  - `ServiceLevel`: FastFood=`fast_food`, FastCasual=`fast_casual`, Casual=`casual`, UpscaleCasual=`upscale_casual`, FineDining=`fine_dining`
  - `ServiceOption`: DineIn=`dine_in`, Takeout=`takeout`, Delivery=`delivery`, DriveThru=`drive_thru`, Curbside=`curbside`
  - `PrimaryCuisine`: American=`american`, Italian=`italian`, Mexican=`mexican`, AsianGeneral=`asian_general`, Chinese=`chinese`, Japanese=`japanese`, Thai=`thai`, Vietnamese=`vietnamese`, Korean=`korean`, Indian=`indian`, Mediterranean=`mediterranean`, Bbq=`bbq`, Seafood=`seafood`, Pizza=`pizza`, Breakfast=`breakfast`, Cafe=`cafe`, BarFood=`bar_food`, Other=`other`
- Each enum gets a `public function label(): string` returning a friendly label (e.g., FastFood → "Fast food", DineIn → "Dine in", AsianGeneral → "Asian (general)", Bbq → "BBQ", BarFood → "Bar food"). Forms will use these — never raw enum values.
- Migration columns: `$table->string('service_level')->nullable()`, `$table->json('service_options')->nullable()`, `$table->string('primary_cuisine')->nullable()` — all nullable, no defaults (existing rows and Places-sourced restaurants may lack data)
- Model: add all three to `#[Fillable]`; casts: `service_level` => `ServiceLevel::class`, `service_options` => `'array'`, `primary_cuisine` => `PrimaryCuisine::class`
- Tests go in `tests/Feature/RestaurantModelTest.php` (extend existing file)

## Requirements (Test Descriptions)
- [x] `it casts service_level to a ServiceLevel enum`
- [x] `it casts service_options to an array`
- [x] `it casts primary_cuisine to a PrimaryCuisine enum`
- [x] `it allows null for all three classification fields`
- [x] `it returns a friendly label for every ServiceLevel case`
- [x] `it returns a friendly label for every ServiceOption case`
- [x] `it returns a friendly label for every PrimaryCuisine case`

## Acceptance Criteria
- All requirements have passing tests
- Migration runs cleanly (`ddev exec php artisan migrate --no-interaction`)
- Code follows code standards (Pint clean)
- No decrease in test coverage

## Implementation Notes
- Created `app/Enums/ServiceLevel.php`, `app/Enums/ServiceOption.php`, `app/Enums/PrimaryCuisine.php` — string-backed enums with a `label()` method using `match` (following `PatioQuality`/`IndoorVibe` pattern).
- Migration `2026_07_09_222811_add_classification_fields_to_restaurants_table.php` adds nullable `service_level` (string), `service_options` (json), `primary_cuisine` (string) columns to `restaurants`.
- `Restaurant` model: added the three fields to `#[Fillable]` and to `casts()` (`service_level` => `ServiceLevel::class`, `service_options` => `'array'`, `primary_cuisine` => `PrimaryCuisine::class`).
- Tests added to `tests/Feature/RestaurantModelTest.php`; existing `RestaurantFactory` untouched since these fields default to null and were set explicitly per test.
- All 7 requirement tests pass; full suite (560 tests) passes in parallel; Pint clean.
