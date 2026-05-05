# Task 005: Feature Tests — Restaurant Create

**Status**: completed
**Depends on**: [003]
**Retry count**: 0

## Description
Write a Pest feature test file `tests/Feature/RestaurantCreateTest.php` covering the create form: required field validation, successful creation with correct owner and source, tag splitting, optional fields, and redirect behavior. Route auth/redirect tests already live in `tests/Feature/RestaurantRoutesTest.php` (Task 001) and MUST NOT be duplicated here.

## Context
- Related files:
  - `tests/Feature/Settings/ProfileUpdateTest.php` (Livewire form testing pattern)
  - `tests/Feature/RestaurantModelTest.php` (factory + DB assertion patterns)
  - `tests/Feature/RestaurantRoutesTest.php` (Task 001 — route smoke tests already covered)
  - `resources/views/pages/restaurants/⚡create.blade.php`
- Patterns to follow:
  - `tests/Pest.php` already binds `RefreshDatabase` globally for `Feature/` — do NOT re-apply it
  - Use `Livewire::test('pages::restaurants.create')` for property/action assertions: `->set('name', '...')->call('save')->assertRedirect(...)` and `->assertHasErrors(['name'])`
  - Use `actingAs($user)` before each test
  - Use `$this->assertDatabaseHas('restaurants', [...])` to verify saved row
  - For JSON array assertions on tag columns, retrieve the row via `Restaurant::first()` and assert `->cuisine_tags === ['Italian', 'Pizza']`
- Validate that `owner_user_id` equals the authenticated user's id
- Validate that `source` equals `'favorite'` (the enum's stored value) or `RestaurantSource::Favorite`
- The component's save action method name MUST be agreed with Task 003 — recommended: `save` (Pest test should call `->call('save')`).

## Requirements (Test Descriptions)
Tests in `tests/Feature/RestaurantCreateTest.php` — DO NOT include the route auth tests already in `RestaurantRoutesTest.php`.
- [ ] `it requires name to save a restaurant`
- [ ] `it requires non-empty cuisine_tags to save a restaurant`
- [ ] `it requires non-empty vibe_tags to save a restaurant`
- [ ] `it saves a restaurant with valid data and redirects to the index`
- [ ] `it sets the owner to the authenticated user on save`
- [ ] `it sets source to favorite on save`
- [ ] `it splits comma-separated cuisine_tags into an array on save`
- [ ] `it splits comma-separated vibe_tags into an array on save`
- [ ] `it surfaces validation errors without redirecting`
- [ ] `it saves optional fields when provided` (covers `address`, `price_level`, `avg_duration_minutes`, `patio_quality`, `indoor_vibe_when_cold`)
- [ ] `it does not expose the source field as user input` (rendered HTML does not contain a form input named `source`)

## Acceptance Criteria
- All requirements have passing tests
- Tests run with `php artisan test --compact --filter=RestaurantCreateTest`
- No duplication of `RestaurantRoutesTest.php` cases
- Pint clean (`vendor/bin/pint --dirty --format agent`)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
