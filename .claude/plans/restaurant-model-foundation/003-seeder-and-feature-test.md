# Task 003: RestaurantSeeder and Feature Test

**Status**: completed
**Depends on**: [001, 002]
**Retry count**: 0

## Description
Create `RestaurantSeeder` with 10 hardcoded realistic Des Moines restaurants, wire it into `DatabaseSeeder`, and write a feature test that confirms the seeder executes and owner-scoped queries return only the correct restaurants.

## Context
- Files to create or modify:
  - `database/seeders/RestaurantSeeder.php` (new)
  - `database/seeders/DatabaseSeeder.php` (modify — call `RestaurantSeeder` after creating the test user)
  - `tests/Feature/RestaurantOwnerQueryTest.php` (new)
- Run: `php artisan make:seeder RestaurantSeeder --no-interaction`
- Run: `php artisan make:test --pest RestaurantOwnerQueryTest --no-interaction`

### Self-sufficient seeder (idempotent)
- The seeder must NOT assume the test user already exists. Use:
  ```
  $owner = User::firstOrCreate(
      ['email' => 'test@example.com'],
      ['name' => 'Test User', 'password' => Hash::make('password')],
  );
  ```
- For each of the 10 restaurants below, use `Restaurant::firstOrCreate(['owner_user_id' => $owner->id, 'name' => $name], [...rest])` so re-running the seeder is safe.

### Des Moines restaurants to include
1. Fong's Pizza — 223 4th St, Des Moines (cuisine: pizza; vibes: lively; patio: decent; source: favorite)
2. Exile Brewing Co — 1514 Walnut St, Des Moines (American; casual; patio: decent)
3. Centro — 1003 Locust St, Des Moines (Italian; romantic; indoor_vibe: cozy)
4. Zombie Burger — 300 E Grand Ave, Des Moines (burgers; casual; patio: none)
5. Django — 1420 Locust St, Des Moines (French/American; romantic; cozy)
6. Proof — 1401 Locust St, Des Moines (American; romantic; patio: destination)
7. ARC Restaurant — 1901 Bell Ave Ste 111, Des Moines (American; seasonal)
8. El Bait Shop — 200 SW 2nd St, Des Moines (American/burgers; casual; patio: decent)
9. Zanzibar's Coffee Adventure — 2723 Ingersoll Ave, Des Moines (café; casual)
10. Eatery A — 600 Keosauqua Way, Des Moines (Asian fusion; romantic)

For each row, set `source = RestaurantSource::Favorite`, fill `cuisine_tags` and `vibe_tags` arrays based on the description, set realistic `price_level`, and use Des Moines lat/lng (~41.58 / -93.62).

### DatabaseSeeder wiring
- The existing `DatabaseSeeder::run()` creates the test user. Append `$this->call(RestaurantSeeder::class);` AFTER the user creation. Order matters in case the test user is referenced anywhere downstream — though `RestaurantSeeder` is now self-sufficient via `firstOrCreate`.

### Feature test setup
- Test file MUST start with `uses(Illuminate\Foundation\Testing\RefreshDatabase::class);` — the project's `tests/Pest.php` does NOT apply it globally.
- Tests should call `$this->seed(RestaurantSeeder::class);` and then assert.
- `it returns only the owner user's restaurants via the relationship` — create a second user via `User::factory()->create()` and assert that user's `restaurants()` returns an empty collection while the seeded user has 10 rows.
- `it can filter restaurants by owner_user_id using a query scope` — assumes `Restaurant::ownedBy($user)` defined in Task 001.

## Requirements (Test Descriptions)
- [x] `it seeds ten restaurants without errors`
- [x] `it assigns all seeded restaurants to the owner user`
- [x] `it returns only the owner user's restaurants via the relationship`
- [x] `it returns an empty collection for a user with no restaurants`
- [x] `it can filter restaurants by owner_user_id using the ownedBy scope`
- [x] `it is idempotent — running the seeder twice produces ten rows, not twenty`

## Acceptance Criteria
- All requirements have passing tests
- `php artisan db:seed` runs without error
- `php artisan db:seed --class=RestaurantSeeder` runs standalone without error (seeder is self-sufficient)
- `User::restaurants()` returns the 10 seeded rows for the seeded user
- `vendor/bin/pint --dirty --format agent` produces no changes after the task

## Implementation Notes
- Created `RestaurantSeeder` with 10 Des Moines restaurants using `firstOrCreate` for idempotency.
- Updated `DatabaseSeeder` to use `User::firstOrCreate` (instead of `factory()->create`) so `db:seed` is safe to re-run, then calls `RestaurantSeeder`.
- Feature test uses `uses(RefreshDatabase::class)` at file level (not in Pest.php).
- All 6 requirements passed; some passed immediately because prior tasks (001, 002) already implemented `User::restaurants()` and `Restaurant::scopeOwnedBy()`.
