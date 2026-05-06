# Task 002: Extract Restaurant Form Fields into a Blade Partial

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Extract the seven form field blocks from `⚡create.blade.php` into an anonymous Blade component at `resources/views/components/restaurants/form-fields.blade.php`. Update the create page to include this component so behaviour is unchanged. The edit page (Task 005) will include the same component.

## Context
- Related files:
  - `resources/views/pages/restaurants/⚡create.blade.php` (source of truth for current fields)
  - `resources/views/components/restaurants/form-fields.blade.php` (new)
- The partial contains only the `<flux:input>` and `<flux:select>` field blocks — no `<form>` wrapper, no submit button
- Fields: `name`, `address`, `cuisine_tags`, `vibe_tags`, `price_level`, `patio_quality`, `indoor_vibe_when_cold`, `avg_duration_minutes`
- Anonymous components have no PHP class; all data binds via `wire:model` from the parent component scope.

## Requirements (Test Descriptions)
- [ ] `it renders the restaurant name field on the create page`
- [ ] `it renders the cuisine tags field on the create page`
- [ ] `it renders the patio quality select on the create page`
- [ ] `it renders the indoor vibe select on the create page`

## Acceptance Criteria
- All requirements have passing tests (these may live in a new `RestaurantFormFieldsTest.php` or appended to `RestaurantCreateTest.php`)
- `⚡create.blade.php` no longer contains the raw field HTML — it uses `<x-restaurants.form-fields />`
- The existing `RestaurantCreateTest` suite continues to pass without modification (this implicitly verifies "still saves correctly after extraction" — no new test needed for that)
- Code follows project standards (run `vendor/bin/pint --dirty --format agent` after PHP edits)

## Implementation Notes
- **Anonymous Blade components do NOT inherit `use` imports from the calling Volt component**. The partial must reference enums by FQCN (`\App\Enums\PatioQuality::cases()`) OR include a `@php use App\Enums\PatioQuality; use App\Enums\IndoorVibe; @endphp` block at the top.
- The fields use `wire:model="..."` against properties owned by the parent Volt component. Names must match the parent's public property names exactly (`name`, `address`, `cuisine_tags`, `vibe_tags`, `price_level`, `patio_quality`, `indoor_vibe_when_cold`, `avg_duration_minutes`).
- Test rendering with `Livewire::test('pages::restaurants.create')->assertSee(__('Cuisine tags'))` (or similar string assertions) — testing the component directly via `view()` is unreliable for Flux components that need a Livewire context.
