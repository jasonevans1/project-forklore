# Task 002: Restaurant Index Livewire Component

**Status**: completed
**Depends on**: [001]
**Retry count**: 0

## Description
Replace the stub at `resources/views/pages/restaurants/⚡index.blade.php` with the real Volt single-file Livewire component that lists the authenticated user's restaurants as mobile-first cards. Each card shows the restaurant name, cuisine tags as pills, and price level as dollar signs. Includes an empty state and an "Add restaurant" button linking to the create page.

## Context
- Related files:
  - `resources/views/pages/settings/⚡profile.blade.php` (Volt class+template pattern)
  - `resources/views/dashboard.blade.php` (layout wrapper pattern: `<x-layouts::app :title="...">`)
  - `app/Models/Restaurant.php` (has `scopeOwnedBy(User $user)`)
  - `app/Models/User.php` (has `restaurants(): HasMany` relationship)
- Patterns to follow:
  - Volt syntax: `new #[Title('Restaurants')] class extends Component { ... }; ?>` followed by Blade markup
  - Use a `#[Computed]` property `restaurants()` returning `Restaurant::ownedBy(Auth::user())->orderBy('name')->get()` (or use `$user->restaurants` relationship)
  - Wrap the template body in `<x-layouts::app :title="__('Restaurants')">` — without this the sidebar/header chrome will not render
- Price level rendering: `str_repeat('$', $restaurant->price_level)` when not null; render nothing when null
- Cuisine tags: iterate `$restaurant->cuisine_tags` (already cast to `array`); render each as a `<flux:badge size="sm">` or styled `<span>`
- Card container: `<flux:card>` (Flux UI 2)
- "Add restaurant" link must use `route('restaurants.create')` and `wire:navigate` (Blade attribute on the `<a>` / `<flux:button as="link">`).

## Test Spec (tests live in Task 004 — DO NOT write tests here)
The following behaviors are the spec for Task 004's `RestaurantIndexTest.php`. They are intentionally NOT duplicated in this task to keep test ownership clear.
- shows an empty state message when the user has no restaurants
- lists each restaurant the authenticated user owns
- displays the restaurant name on each card
- displays cuisine tags on each card
- displays the price level as dollar signs on each card (price_level=2 → "$$")
- does not show restaurants owned by other users (uses `scopeOwnedBy` / `restaurants()` relationship)
- shows a link to `route('restaurants.create')`

## Manual Verification Acceptance Criteria
- Component renders at `/restaurants` on a 360px-wide viewport without horizontal scroll
- Cards stack vertically in a single column on mobile
- Empty state copy is visible when the user has zero restaurants
- "Add restaurant" link/button is reachable above or below the list and points to `route('restaurants.create')`
- Pint clean (`vendor/bin/pint --dirty --format agent`)
- Manual smoke check via the existing `tests/Feature/RestaurantRoutesTest.php` still passes (the route returns 200 with the new component)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
