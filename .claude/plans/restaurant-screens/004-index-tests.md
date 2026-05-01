# Task 004: Feature Tests — Restaurant List

**Status**: completed
**Depends on**: [002]
**Retry count**: 0

## Description
Write a Pest feature test file `tests/Feature/RestaurantIndexTest.php` covering the restaurant index page behavior: data isolation between users, card content, empty state, and the create link. Route auth/redirect tests already live in `tests/Feature/RestaurantRoutesTest.php` (Task 001) and MUST NOT be duplicated here.

## Context
- Related files:
  - `tests/Feature/RestaurantModelTest.php` (factory usage and `actingAs` patterns)
  - `tests/Feature/Settings/ProfileUpdateTest.php` (Livewire test pattern: `Livewire::test('pages::settings.profile')`)
  - `tests/Feature/RestaurantRoutesTest.php` (Task 001 — route smoke tests already covered)
  - `resources/views/pages/restaurants/⚡index.blade.php`
- Patterns to follow:
  - `tests/Pest.php` already binds `RefreshDatabase` to all `Feature` tests — do NOT add `uses(RefreshDatabase::class)` again
  - `actingAs($user)` then either `$this->get(route('restaurants.index'))` for HTTP-level assertions or `Livewire::test('pages::restaurants.index')` for component-level state assertions
  - Seed data with `Restaurant::factory()->create(['owner_user_id' => $user->id, ...])` (see existing `RestaurantModelTest.php`)
- Use `assertSee` / `assertDontSee` to verify card content and isolation
- Price level rendering test: factory create with `price_level => 3`, then `assertSee('$$$')`

## Requirements (Test Descriptions)
Tests in `tests/Feature/RestaurantIndexTest.php` — DO NOT include the route auth tests already in `RestaurantRoutesTest.php`.
- [ ] `it shows an empty state message when the user has no restaurants`
- [ ] `it lists each restaurant the authenticated user owns by name`
- [ ] `it displays cuisine tags on each card`
- [ ] `it displays the price level as dollar signs on each card`
- [ ] `it does not show restaurants owned by other users`
- [ ] `it shows a link to the create restaurant page` (assert `route('restaurants.create')` URL appears in the response)

## Acceptance Criteria
- All requirements have passing tests
- Tests run with `php artisan test --compact --filter=RestaurantIndexTest`
- No duplication of `RestaurantRoutesTest.php` cases
- Pint clean (`vendor/bin/pint --dirty --format agent`)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
