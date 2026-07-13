# Task 005: QuizService Combined-Filter Integration + QuizServiceTest Sweep

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description
With all four hard filters in place (tasks 002 + 003), add integration coverage that exercises them **together**
against a real `QuizService::topMatch()` call, verify "Surprise me" applies no cuisine constraint end-to-end, and
do the final `QuizServiceTest` sweep so no assertions against removed behavior (`nearby`/`close` distance,
`CUISINE_MATCH_BONUS` soft scoring) survive.

> Note: the wizard blade partials that this file was originally scoped to build have been folded into task 004 —
> the component and the partials it renders must ship atomically (Livewire renders on every test), so they can't be
> split. This task is now the service-layer integration + cleanup counterpart.

## Context
- Related files: `tests/Feature/QuizServiceTest.php`, `app/Services/QuizService.php` (read-only — filters already
  built in 002/003), `database/factories/RestaurantFactory.php` (`withServiceLevel()` state)
- Combined-filter test: seed a small set of favorites that differ across `service_options`, `service_level`,
  `primary_cuisine`, and location, then assert `topMatch()` returns only the restaurant satisfying **all four**
  constraints at once. Construct `QuizAnswers` with `dineInTakeout`, `serviceLevel`, `cuisine` (a lowercase
  `PrimaryCuisine` value), `distance` (a new bucket), and `lat`/`lng` set (distance filter no-ops without coords).
- "Surprise me" test: with `cuisine: null`, a restaurant whose `primary_cuisine` differs from every other must
  still be eligible (no cuisine exclusion), proving null bypasses the filter.
- Sweep: confirm `QuizServiceTest.php` has **no** leftover assertions referencing `nearby`/`close` distance values
  or the removed `CUISINE_MATCH_BONUS` soft-scoring behavior (task 003 should have handled these — this is the
  final check). The `neutralAnswers()` helper stays valid since the new `QuizAnswers` fields have defaults and all
  callers use named args.

## Requirements (Test Descriptions)
- [x] `it narrows the final pick using all four filters combined (dine-in/takeout, service level, cuisine, distance)`
- [x] `it does not exclude any restaurant by cuisine when cuisine is null (surprise me)`
- [x] `it has no remaining assertions against removed nearby/close distance or cuisine soft-scoring behavior`

## Acceptance Criteria
- All requirements have passing tests in `tests/Feature/QuizServiceTest.php`
- `QuizServiceTest.php` is fully green with no stale assertions
- `vendor/bin/pint --dirty --format agent` run and clean
- No decrease in test coverage

## Implementation Notes
- `QuizService::buildPool()` already applied all four hard filters (service level, dine-in/takeout, cuisine,
  distance) from tasks 002/003 — no service code changes were needed for this task. All three new tests passed
  as soon as they were written; per the task's own framing, this is expected for an integration/sweep task and
  not over-implementation.
- Added `it narrows the final pick using all four filters combined ...`: five restaurants each violating exactly
  one of the four constraints, asserting `topMatch()` returns only the one satisfying all four.
- Added `it does not exclude any restaurant by cuisine when cuisine is null (surprise me)`: an odd-cuisine-out
  restaurant also carries the best-matching vibe tag, so it can only win if the (null) cuisine filter did not
  exclude it — proves null bypasses the filter without relying on randomness.
- Added `it has no remaining assertions against removed nearby/close distance or cuisine soft-scoring behavior`
  as a self-check test asserting `QuizServiceTest.php` and `QuizService.php` contain none of the forbidden
  strings (`CUISINE_MATCH_BONUS`, `NEARBY_MILES`, `CLOSE_MILES`, `'nearby'`, `'close'`). Forbidden strings are
  built via string concatenation in the test so the assertion itself doesn't trip its own check. Confirmed
  clean — task 003 had already fully removed the old soft-scoring/bucket behavior.
- Full suite: 639 passed. `vendor/bin/pint --dirty --format agent` clean.
