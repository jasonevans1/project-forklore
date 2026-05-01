# Task 003: Restaurant Create Livewire Form Component

**Status**: completed
**Depends on**: [001]
**Retry count**: 0

## Description
Replace the stub at `resources/views/pages/restaurants/⚡create.blade.php` with the real Volt single-file Livewire form component. The form includes all user-editable fields from the restaurants migration in a single-column layout with large tap targets. A sticky bottom bar contains the submit button. Inline validation errors appear beneath each field via Flux UI's built-in error rendering. On success, the user is redirected to `restaurants.index` with a success toast.

## Context
- Related files:
  - `resources/views/pages/settings/⚡profile.blade.php` (Volt + form + `Flux::toast()` pattern)
  - `app/Models/Restaurant.php` (fillable, casts)
  - `app/Enums/IndoorVibe.php`, `app/Enums/PatioQuality.php`, `app/Enums/RestaurantSource.php`
  - `database/factories/RestaurantFactory.php` (defaults reference for sane test inputs)
- Patterns to follow:
  - Volt class extends `Livewire\Component`; declare typed public properties for each field
  - Use `$this->validate([...])` inside the save action
  - Toast: `Flux::toast(variant: 'success', text: __('Restaurant added.'))`
  - Redirect: `$this->redirect(route('restaurants.index'), navigate: true)` — do NOT rely on `wire:navigate` Blade attribute for the post-save redirect
  - Wrap the template body in `<x-layouts::app :title="__('Add restaurant')">`
- **Livewire property types** (critical to lock down for tests):
  - `public string $name = '';`
  - `public string $address = '';`
  - `public string $cuisine_tags = '';` — string in form (comma-separated); split into array at save time
  - `public string $vibe_tags = '';` — string in form (comma-separated); split into array at save time
  - `public ?int $price_level = null;`
  - `public string $patio_quality = PatioQuality::None->value;`
  - `public string $indoor_vibe_when_cold = IndoorVibe::Neutral->value;`
  - `public ?int $avg_duration_minutes = null;`
- **Tag-splitting helper** (single canonical implementation to avoid worker drift):
  ```php
  $tags = collect(explode(',', $value))
      ->map(fn (string $tag): string => trim($tag))
      ->filter()
      ->values()
      ->all();
  ```
- Hidden / server-set fields: `source = RestaurantSource::Favorite`, `owner_user_id = Auth::id()`, `lat = null`, `lng = null`, `last_visited_at = null`, `visit_count = 0`
- Validation rules:
  - `name` => `['required', 'string', 'max:255']`
  - `address` => `['nullable', 'string', 'max:500']`
  - `cuisine_tags` => `['required', 'string', 'max:500']` (validates the raw input string before splitting; ensures non-empty after trim is enforced by checking the split result is non-empty — see below)
  - `vibe_tags` => `['required', 'string', 'max:500']`
  - `price_level` => `['nullable', 'integer', 'between:1,4']`
  - `patio_quality` => `['required', Rule::enum(PatioQuality::class)]`
  - `indoor_vibe_when_cold` => `['required', Rule::enum(IndoorVibe::class)]`
  - `avg_duration_minutes` => `['nullable', 'integer', 'min:1', 'max:600']`
  - After validation, if either tag list is empty after splitting (e.g., user entered only commas/spaces), call `$this->addError('cuisine_tags', __('At least one cuisine tag is required.'))` (and the equivalent for vibe_tags) and `return` without saving.
- Form layout:
  - Single column, each field stacked
  - `<flux:input>`, `<flux:select>`, `<flux:textarea>` from Flux UI 2 (these render their own validation error using the `$errors` bag — do NOT use a non-existent `<flux:error>` element)
  - For manual error display next to a field, use `@error('field_name') <flux:text variant="danger">{{ $message }}</flux:text> @enderror`
  - Sticky submit bar: `<div class="fixed inset-x-0 bottom-0 ...">` containing a full-width `<flux:button type="submit" variant="primary" class="w-full">`. Add `pb-24` to the form's content wrapper to prevent the sticky bar from overlapping the last field.

## Test Spec (tests live in Task 005 — DO NOT write tests here)
The following behaviors are the spec for Task 005's `RestaurantCreateTest.php`. They are intentionally NOT duplicated in this task.
- requires name to save a restaurant (validation error on `name`)
- requires non-empty cuisine_tags to save a restaurant (validation error on `cuisine_tags`)
- requires non-empty vibe_tags to save a restaurant (validation error on `vibe_tags`)
- saves a restaurant with valid data and redirects to the index (`assertRedirect(route('restaurants.index'))`)
- sets `owner_user_id` to the authenticated user's id on save
- sets `source` to `RestaurantSource::Favorite` on save
- splits comma-separated `cuisine_tags` into an array on save (DB column should contain a JSON array)
- splits comma-separated `vibe_tags` into an array on save
- shows inline validation errors without redirecting (`assertHasErrors(['name'])` then `assertNoRedirect()`)
- saves optional fields when provided (`address`, `price_level`, `avg_duration_minutes`, enum selects)
- does not display the `source` field as a user-editable input (regression guard against accidentally exposing it)

## Manual Verification Acceptance Criteria
- Form renders in a single column with `min-h-[44px]` (or Flux default tap height) on each input
- Submit button is visually sticky at the bottom of the viewport on mobile
- Validation errors render adjacent to each field
- Pint clean (`vendor/bin/pint --dirty --format agent`)
- `tests/Feature/RestaurantRoutesTest.php` still passes (route still returns 200 with the new component)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
