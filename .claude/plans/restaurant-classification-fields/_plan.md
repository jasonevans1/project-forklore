# Plan: Restaurant Classification Fields

## Created
2026-07-09

## Status
completed

## Objective
Add `service_level`, `service_options`, and `primary_cuisine` columns to restaurants, wired through the model, factory, seeder, and create/edit forms with friendly labels and full feature-test coverage.

## Related Issues
none

## Discovery Notes
- `Restaurant` model uses the `#[Fillable]` attribute and a `casts()` method; existing string-backed enums (`PatioQuality`, `IndoorVibe`, `RestaurantSource`) live in `app/Enums/` with TitleCase cases.
- Create/edit forms are Livewire single-file pages at `resources/views/pages/restaurants/⚡create.blade.php` and `⚡edit.blade.php`, sharing the `resources/views/components/restaurants/form-fields.blade.php` partial. Existing selects render labels via `ucfirst($case->value)` — insufficient for multi-word values like `fast_food`, so new enums get a `label(): string` method.
- `RestaurantSeeder` seeds 10 Des Moines restaurants via `firstOrCreate`; `RestaurantSeederTest`, `RestaurantCreateTest`, `RestaurantEditTest`, `RestaurantFormFieldsTest`, `RestaurantFactoryTest` already exist as homes for new tests.
- Defaults chosen (not user-specified): all three columns nullable with no DB default (existing rows and Places-sourced restaurants may lack data); form fields optional (`nullable` validation), matching the `price_level` pattern; `service_options` stored as JSON array of `ServiceOption` enum string values.
- All php/artisan/composer/pint commands run via `ddev exec` (project runs in a ddev container).

## Scope

### In Scope
- Migration adding `service_level` (string), `service_options` (json), `primary_cuisine` (string) — all nullable
- New enums: `ServiceLevel` (5 cases), `ServiceOption` (5 cases), `PrimaryCuisine` (18 cases), each with `label()`
- `Restaurant` model: fillable + casts for the three fields
- `RestaurantFactory`: realistic combinations of the three fields
- `RestaurantSeeder`: accurate values for all 10 Des Moines restaurants
- Create/edit forms + shared form-fields partial: capture all three fields with friendly labels
- Feature tests: model casts, factory, seeder completeness, form save paths, form validation

### Out of Scope
- Using the new fields in decision modes (Quick Pick / Quiz / Tournament scoring)
- Mapping Google Places data into the new fields on import/promote
- Backfilling non-seeded existing rows
- Filtering/searching restaurants by the new fields

## Success Criteria
- [ ] Migration runs cleanly on existing data (columns nullable)
- [ ] Restaurant model casts all three fields correctly
- [ ] Factory generates valid, realistic combinations
- [ ] Every seeded restaurant has all three fields populated
- [ ] Create and edit forms capture the fields with friendly labels (never raw enum values)
- [ ] All tests passing
- [ ] Code follows project standards (Pint clean)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Enums, migration, model casts | - | completed |
| 002 | Factory realistic combinations | 001 | completed |
| 003 | Seeder values for Des Moines restaurants | 001 | completed |
| 004 | Form-fields partial + create form save path | 001 | completed |
| 005 | Create form validation | 001, 004 | completed |
| 006 | Edit form pre-fill and update | 001, 004 | completed |

## Architecture Notes
- Follow existing enum pattern: string-backed, TitleCase cases, one file per enum in `app/Enums/`
- `label()` on each new enum is the single source of friendly labels; forms must use it (no `ucfirst` on values)
- Validation follows existing form pattern: `Rule::enum(...)` for single enums, `service_options` as `nullable|array` with `service_options.*` => `Rule::enum(ServiceOption::class)`
- Empty-string guard (both forms): a blank `flux:select` submits `''`, which the `nullable` rule skips rather than rejects (non-implicit rules don't run on empty values). Persisting `''` into an enum-cast column throws `ValueError` (500). Normalize `service_level`/`primary_cuisine` with `?: null` before `create()`/`update()`, exactly as `service_options` already does. Do not test "non-array service_options" — the typed `array` property makes it unreachable by validation.
- Use `ddev exec php artisan make:migration ... --no-interaction`; run Pint via `ddev exec vendor/bin/pint --dirty --format agent`

## Risks & Mitigations
- Tasks 004/005/006 touch shared form files: mitigated by dependency ordering (005 and 006 both depend on 004; they touch different files from each other)
- Seeder uses `firstOrCreate`, so re-running on an existing DB won't update rows: seeder test uses fresh DB (RefreshDatabase), acceptable; note for local DBs to re-seed after `migrate:fresh`
- Real-world accuracy of seed values is best-effort (e.g., French cuisine maps to `other`); values listed explicitly in task 003 for review
