# Task 005: Create Form Validation for Classification Fields

**Status**: completed
**Depends on**: 001, 004
**Retry count**: 0

## Description
Add feature tests proving the create form rejects invalid values for the three classification fields, tightening the validation rules added in task 004 if any test exposes a gap.

## Context
- Related files: `resources/views/pages/restaurants/⚡create.blade.php`, `tests/Feature/RestaurantCreateTest.php`
- Patterns to follow: existing validation tests in `RestaurantCreateTest` (Livewire `->set(...)->call('save')->assertHasErrors(...)`)
- Run all php/artisan commands via `ddev exec`
- Task 004 already added the `Rule::enum` rules; this task proves them with tests (rules may already pass — that's fine, TDD note it and move on)
- Do NOT test "rejects a non-array service_options": the component property is typed `public array $service_options`, so Livewire enforces the array type during hydration and throws before validation runs — `->set('service_options', 'string')->call('save')->assertHasErrors(...)` cannot work. The PHP `array` type hint is the guard; the `['nullable','array']` rule stays but needs no dedicated test.
- The invalid single-enum tests (`service_level`, `primary_cuisine`) use a bogus string like `'not_real'` — validation runs before persistence and catches it, so there is no enum-cast crash on this path. (The empty-string path, by contrast, skips validation and is covered by task 004's blank-select test.)

## Requirements (Test Descriptions)
- [x] `it rejects an invalid service_level value`
- [x] `it rejects an invalid service option value in the array` (set `service_options` to `['not_a_real_option']`, assert errors on `service_options.*`)
- [x] `it rejects an invalid primary_cuisine value`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards (Pint clean)

## Implementation Notes
All three tests passed immediately on the first run — task 004's `Rule::enum(...)` validation rules already covered every case. No changes to `⚡create.blade.php` were needed. Only `tests/Feature/RestaurantCreateTest.php` was modified.
