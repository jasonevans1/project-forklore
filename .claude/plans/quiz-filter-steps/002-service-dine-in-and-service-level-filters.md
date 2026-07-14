# Task 002: QuizService — Dine-in/Takeout + Service Level Filters

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Add two hard filters to `QuizService::buildPool()`: dine-in/takeout availability (`Restaurant::service_options`) and service level tier (`Restaurant::service_level`), including the grouped `quick_easy` tier that must match both `fast_food` and `fast_casual`.

## Context
- Related files: `app/Services/QuizService.php` (`buildPool()`), `app/Models/Restaurant.php` (`service_options` cast to `array`, `service_level` cast to `ServiceLevel` enum)
- **`service_options` (JSON array) MUST be filtered in PHP after `->get()`, not with `whereJsonContains`.** The app runs on SQLite (default + test connection) and there is no existing `whereJsonContains` usage to confirm it works here — SQLite JSON-contains support is a known Laravel gotcha ("This database engine does not support JSON contains operations"). Follow the existing distance filter's PHP-side `->filter()` pattern instead: `fn (Restaurant $r) => in_array('dine_in', $r->service_options ?? [], true)`.
- `service_level` is a plain scalar column, so keep it as a query-level `where`/`whereIn` on the `Restaurant::ownedBy($user)->favorites()` builder before `->get()` — no JSON risk there.
- `service_options` is a JSON array cast (see `RestaurantFactory`/migration for stored value shape — values are `ServiceOption` enum strings like `'dine_in'`, `'takeout'`)
- dineInTakeout → `service_options` (PHP-side containment filter):
  - `'either'` → no constraint
  - `'dine_in'` → keep only restaurants whose `service_options` contains `'dine_in'`
  - `'takeout'` → keep only restaurants whose `service_options` contains `'takeout'`
- serviceLevel tier → `service_level` (query-level clause):
  - `'quick_easy'` → `whereIn('service_level', [ServiceLevel::FastFood->value, ServiceLevel::FastCasual->value])`
  - `'casual_sit_down'` → `where('service_level', ServiceLevel::Casual->value)`
  - `'nicer_night_out'` → `where('service_level', ServiceLevel::UpscaleCasual->value)`
  - `'special_occasion'` → `where('service_level', ServiceLevel::FineDining->value)`
- Use `RestaurantFactory::withServiceLevel(ServiceLevel $level)` (already exists — regenerates matching `service_options`) or explicit `service_options`/`service_level` overrides in tests. Set `service_options` explicitly when asserting the dine-in/takeout filter, since the factory's default set is randomized.

## Requirements (Test Descriptions)
- [x] `it excludes restaurants without dine_in in service_options when dineInTakeout is dine_in`
- [x] `it excludes restaurants without takeout in service_options when dineInTakeout is takeout`
- [x] `it includes restaurants regardless of service_options when dineInTakeout is either`
- [x] `it includes only fast_food and fast_casual restaurants when serviceLevel is quick_easy`
- [x] `it includes only casual restaurants when serviceLevel is casual_sit_down`
- [x] `it includes only fine_dining restaurants when serviceLevel is special_occasion`
- [x] `it combines dine-in/takeout and service level filters together`

## Acceptance Criteria
- All requirements have passing tests in `tests/Feature/QuizServiceTest.php`
- `vendor/bin/pint --dirty --format agent` run and clean
- No decrease in test coverage

## Implementation Notes
- `buildPool()` now applies `service_level` as a query-level `where`/`whereIn` on the
  `Restaurant::ownedBy($user)->favorites()` builder (before `->get()`), and `service_options`
  as a PHP-side `->filter()` on the fetched collection — matching the existing distance-filter
  pattern and avoiding `whereJsonContains` (unsupported on SQLite).
- The `serviceLevel`/`dineInTakeout` tests were implemented incrementally per the task list, but
  because both filters are naturally expressed as a single generic `match`/`in_array` check
  keyed off the answer value, most tests after the first case for each filter passed immediately
  (no new code needed) — noted at each step rather than artificially special-casing.
- Discovered a latent test-fragility issue while implementing the `serviceLevel` filter:
  `RestaurantFactory`'s default `service_level` was `fake()->randomElement(ServiceLevel::cases())`,
  while `QuizAnswers`/the test suite's `neutralAnswers()` helper default `serviceLevel` to
  `'casual_sit_down'` (→ `ServiceLevel::Casual`). Once the query-level filter was added, every
  existing `QuizServiceTest` test that creates a plain `Restaurant::factory()->create()` and calls
  `topMatch()` with default/neutral answers became flaky (restaurant excluded whenever the random
  service level wasn't `Casual`). Fixed by changing the factory default to a fixed
  `ServiceLevel::Casual` (marked with a `ponytail:` comment explaining why) rather than touching
  every existing test — confirmed no test asserts on the previous random distribution.
- Extended the `neutralAnswers()` test helper in `tests/Feature/QuizServiceTest.php` to accept
  `serviceLevel`/`dineInTakeout` overrides (defaulting to the same values as `QuizAnswers`), so new
  tests can flip just the dimension under test.
- Verified flakiness pre-fix and stability post-fix by running each new test 3x before/after the
  corresponding implementation change.
- Full suite: 630 passed (1340 assertions), both `--parallel` and sequential. Pint clean.
