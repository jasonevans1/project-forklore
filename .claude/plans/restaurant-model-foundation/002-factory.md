# Task 002: RestaurantFactory

**Status**: completed
**Depends on**: [001]
**Retry count**: 0

## Description
Create `RestaurantFactory` with realistic defaults so tests and the seeder can generate `Restaurant` instances without specifying every column. The factory must produce valid enum values, JSON array fields, and an `address` string consistent with the migration schema defined in Task 001.

## Context
- Files to create:
  - `database/factories/RestaurantFactory.php`
- Patterns to follow: `database/factories/UserFactory.php` — uses global `fake()` helper (not `$this->faker`)
- Run: `php artisan make:factory RestaurantFactory --model=Restaurant --no-interaction`

### Required imports in the factory
```php
use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Enums\RestaurantSource;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
```

### Default state values
- `owner_user_id` => `User::factory()` (creates a user if none passed)
- `name` => `fake()->company().' Restaurant'` (or similar; must be a non-empty string)
- `address` => `fake()->streetAddress().', Des Moines, IA'`
- `source` => `RestaurantSource::Favorite`
- `cuisine_tags` => `[fake()->randomElement(['Italian', 'Mexican', 'American', 'Thai', 'Japanese'])]`
- `vibe_tags` => `[fake()->randomElement(['romantic', 'casual', 'lively', 'quiet'])]`
- `price_level` => `fake()->numberBetween(1, 4)`
- `patio_quality` => `PatioQuality::None`
- `indoor_vibe_when_cold` => `IndoorVibe::Neutral`
- `avg_duration_minutes` => `fake()->randomElement([45, 60, 75, 90, 120])`
- `lat` => `41.58 + (fake()->randomFloat(4, -0.05, 0.05))`
- `lng` => `-93.62 + (fake()->randomFloat(4, -0.05, 0.05))`
- `last_visited_at` => `null` (intentionally unset — let tests opt in)
- `visit_count` => `0` (matches migration default; can be overridden in tests)

## Requirements (Test Descriptions)
- [x] `it creates a restaurant with factory defaults`
- [x] `it creates a restaurant belonging to a specific user`
- [x] `it generates cuisine_tags as a non-empty array`
- [x] `it generates vibe_tags as a non-empty array`
- [x] `it generates a valid price_level between 1 and 4`
- [x] `it generates a valid RestaurantSource enum value`
- [x] `it generates an address string`

## Acceptance Criteria
- All requirements have passing tests
- Factory creates a persisted restaurant row without errors against the Task 001 schema
- Test file uses `uses(Illuminate\Foundation\Testing\RefreshDatabase::class);` at top (Pest.php does not apply it globally)
- `vendor/bin/pint --dirty --format agent` produces no changes after the task

## Implementation Notes
- Updated existing `RestaurantFactory` to match all required defaults: `name` with `' Restaurant'` suffix, `address` with `, Des Moines, IA` suffix, `source` fixed to `RestaurantSource::Favorite`, `cuisine_tags`/`vibe_tags` as single-element arrays from curated lists, `avg_duration_minutes` from fixed set `[45, 60, 75, 90, 120]`, `lat`/`lng` centered on Des Moines with ±0.05 degree jitter.
- Test file at `tests/Feature/RestaurantFactoryTest.php` uses `uses(RefreshDatabase::class)` as required.
- The pre-existing `RegistrationTest::new_users_can_register` failure (missing `RefreshDatabase`) is unrelated to this task.
