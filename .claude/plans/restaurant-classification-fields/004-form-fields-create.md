# Task 004: Form-Fields Partial + Create Form Save Path

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Add the three classification fields to the shared `x-restaurants.form-fields` partial with friendly labels, and wire them through the create form's component properties, validation rules, and `Restaurant::create()` call.

## Context
- Related files: `resources/views/components/restaurants/form-fields.blade.php`, `resources/views/pages/restaurants/⚡create.blade.php`, `tests/Feature/RestaurantCreateTest.php`, `tests/Feature/RestaurantFormFieldsTest.php`
- Patterns to follow: existing optional select pattern (`price_level` with a "— Select —" empty option); mobile-first Flux UI
- Run all php/artisan commands via `ddev exec`
- UI: `service_level` and `primary_cuisine` as `flux:select` iterating enum cases using `$case->label()` (never raw values or `ucfirst`); `service_options` as a `flux:checkbox.group` (one checkbox per `ServiceOption` case, label from `label()`), bound to a `public array $service_options = []` property
- Component properties: `public ?string $service_level = null`, `public array $service_options = []`, `public ?string $primary_cuisine = null`
- Validation (all optional, matching `price_level`): `service_level` => `['nullable', Rule::enum(ServiceLevel::class)]`; `service_options` => `['nullable', 'array']`, `service_options.*` => `[Rule::enum(ServiceOption::class)]`; `primary_cuisine` => `['nullable', Rule::enum(PrimaryCuisine::class)]`
- Persist: pass all three to `Restaurant::create()`, normalizing empty strings to null so the enum cast never receives `''`: `service_level` => `$this->service_level ?: null`, `service_options` => `$this->service_options ?: null`, `primary_cuisine` => `$this->primary_cuisine ?: null`
- CRITICAL — empty-string guard: the `flux:select` renders a `value=""` "— Select —" option. If the user opens the select and picks that blank option, the property becomes `''` (empty string), NOT null. The `['nullable', Rule::enum(...)]` rule SKIPS validation for an empty string (a non-implicit rule is not run against an empty value), so `''` reaches persistence. Casting `''` to a backed enum throws `ValueError` (500 error). The `?: null` normalization above is what prevents this — do not omit it for `service_level`/`primary_cuisine`.

## Requirements (Test Descriptions)
- [x] `it renders friendly service level labels in the create form`
- [x] `it renders friendly primary cuisine labels in the create form`
- [x] `it renders a checkbox per service option with friendly labels`
- [x] `it saves service_level when creating a restaurant`
- [x] `it saves service_options when creating a restaurant`
- [x] `it saves primary_cuisine when creating a restaurant`
- [x] `it creates a restaurant with the classification fields omitted`
- [x] `it creates a restaurant when the classification selects are explicitly blank` (set `service_level`, `primary_cuisine` to `''` and `service_options` to `[]`; assert no errors and the three columns persist as null — proves the empty-string `?: null` guard)

## Acceptance Criteria
- All requirements have passing tests
- Labels shown to users are friendly ("Fast food", not "fast_food")
- Code follows code standards (Pint clean)

## Implementation Notes
- Component properties, validation rules, and `Restaurant::create()` normalization for all three fields were implemented together in one pass (single cohesive change to `⚡create.blade.php`), so the five save-path tests all passed on first run — no separate red step observed for those; UI-rendering tests were written and confirmed red before implementing the `form-fields.blade.php` selects/checkbox group.
- `service_options` is cast as a plain `array` (string values), not enum objects — test assertions compare against `ServiceOption::*->value`, not enum instances.
- Reused the existing `price_level` "— Select —" empty-option pattern for the two new selects; used `flux:checkbox.group` + `flux:checkbox` (Flux free tier) for `service_options`.
