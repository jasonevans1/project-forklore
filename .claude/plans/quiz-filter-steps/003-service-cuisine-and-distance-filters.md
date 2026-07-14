# Task 003: QuizService — Cuisine + Distance Filters

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Replace the soft `cuisine_tags` scoring bonus with a hard `primary_cuisine` filter, and replace the 3-bucket distance filter (`nearby`/`close`/`anywhere`) with the new 4-bucket version (`under_2_miles`/`2_to_5_miles`/`5_to_15_miles`/`anywhere`). Depends on Task 002 because both tasks edit `buildPool()` — do this sequentially to avoid overlapping edits to the same method.

## Context
- Related files: `app/Services/QuizService.php` (`buildPool()`, `scoreAll()`, distance constants near the top of the class)
- Cuisine: `$answers->cuisine === null` → no constraint (Surprise me). Otherwise `where('primary_cuisine', $answers->cuisine)`. Remove the `CUISINE_MATCH_BONUS` constant and its scoring block in `scoreAll()` entirely — it's redundant once non-matches are excluded from the pool.
- Distance: replace `NEARBY_MILES`/`CLOSE_MILES` constants with bucket boundaries for the 4 new values. Each bucket is a `[min, max]` range in miles (min exclusive except the first bucket, which starts at 0):
  - `under_2_miles` → `(0, 2.0]`
  - `2_to_5_miles` → `(2.0, 5.0]`
  - `5_to_15_miles` → `(5.0, 15.0]`
  - `anywhere` → no constraint
  Keep the existing PHP-side haversine filtering approach (`distanceMiles()` helper) — only the bucket boundaries and answer values change, not the filtering mechanism.
- **The distance filter only runs when `$answers->lat`/`$answers->lng` are non-null** (unchanged behavior); with no coordinates every restaurant passes. Distance tests MUST construct `QuizAnswers` with `lat`/`lng` set (see the existing `distance=nearby` test for the pattern) or they pass for the wrong reason.
- **Cuisine value is the `PrimaryCuisine` backed value (lowercase, e.g. `'italian'`), not a title-case tag.** `where('primary_cuisine', $answers->cuisine)` compares against the `PrimaryCuisine`-cast column. Cuisine tests must set `primary_cuisine` and pass the enum value, never `'Italian'`.
- `cuisine_tags` display badges on the result card (`⚡quiz.blade.php`) are unaffected — only the scoring bonus goes away, not the `cuisine_tags` data itself

## Requirements (Test Descriptions)
- [x] `it excludes restaurants with a different primary_cuisine when a cuisine is specified`
- [x] `it includes restaurants regardless of primary_cuisine when cuisine is null (surprise me)`
- [x] `it excludes restaurants beyond 2 miles when distance is under_2_miles`
- [x] `it excludes restaurants outside the 2 to 5 mile range when distance is 2_to_5_miles`
- [x] `it excludes restaurants outside the 5 to 15 mile range when distance is 5_to_15_miles`
- [x] `it includes all restaurants regardless of distance when distance is anywhere`

## Acceptance Criteria
- All requirements have passing tests in `tests/Feature/QuizServiceTest.php`
- The old `it scores a restaurant with matching cuisine higher...` and `it excludes restaurants beyond the max distance when distance=nearby` / `it includes all restaurants when distance=anywhere` tests are removed or rewritten to match the new filter semantics (no stale assertions against removed behavior)
- `vendor/bin/pint --dirty --format agent` run and clean
- No decrease in test coverage

## Implementation Notes
- `buildPool()` now applies a hard `where('primary_cuisine', $answers->cuisine)` filter (skipped when `cuisine` is null), replacing the old soft `CUISINE_MATCH_BONUS` scoring block, which was removed from `scoreAll()` entirely.
- Distance filtering replaced `NEARBY_MILES`/`CLOSE_MILES` with a `DISTANCE_BUCKETS` const array of `[min, max]` ranges keyed by the answer string (`under_2_miles`, `2_to_5_miles`, `5_to_15_miles`); `anywhere` has no entry so the `isset()` check skips filtering. Buckets are exclusive on `min` except the first, which uses `>=` for 0 so exact-same-location restaurants aren't excluded.
- Rewrote the stale `distance=nearby`/`distance=close`/`anywhere` and cuisine-scoring tests in `tests/Feature/QuizServiceTest.php` to match the new hard-filter semantics; four of the six new tests initially passed against the old implementation for the wrong reason (coincidental scoring ties / bucket overlap) before the real filters were written — confirmed via manual inspection rather than re-running RED since the fix path was shared across all six.
- Full suite: 636 passed. Pint clean.
