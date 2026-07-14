# Task 001: QuizService — Per-Filter Exclusion Counts

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add a method to `QuizService` that reports, for each of the 4 hard filters, how many of the user's favorites that filter alone would exclude from the base pool (before any other filter is applied). This powers the empty-pool screen's "which filter is the biggest blocker" ranking.

## Context
- Related files: `app/Services/QuizService.php`, `app/Services/QuizAnswers.php`, `tests/Feature/QuizServiceTest.php`
- `buildPool()` currently applies all 4 filters (`dineInTakeout`, `serviceLevel`, `cuisine`, `distance`) against `Restaurant::ownedBy($user)->favorites()`. **Important:** `serviceLevel` and `cuisine` are applied as query-builder constraints (`whereIn`/`where`), while `dineInTakeout` and `distance` are already PHP collection filters. To measure each filter "against the same unfiltered base pool" you MUST evaluate all four as **PHP-level predicates over an already-loaded base collection** (`Restaurant::ownedBy($user)->favorites()->get()`). Extract each filter's predicate into its own small private method (e.g. `excludedByDineInTakeout`, `excludedByServiceLevel`, `excludedByCuisine`, `excludedByDistance`) that takes a loaded `Restaurant` and the `QuizAnswers`.
- You do NOT have to rewrite `buildPool()`'s `serviceLevel`/`cuisine` SQL filtering into PHP — that would risk a subtle behavior change. It is acceptable for `buildPool()` to keep its query constraints and for `filterExclusionCounts()` to reuse the extracted PHP predicates only where `buildPool()` already filters in PHP (`dineInTakeout`, `distance`), reimplementing the `serviceLevel`/`cuisine` checks as equivalent PHP predicates. Whichever way you share code, `buildPool()`'s existing results and tests must be byte-for-byte unchanged, and each predicate must produce the same include/exclude decision the corresponding `buildPool()` filter does.
- The distance filter only applies when `$answers->lat`/`$answers->lng` are present, and a restaurant with null `lat`/`lng` is excluded by the distance filter (mirroring `buildPool()`). No user lat/lng means the distance filter contributes 0 to every count.
- A restaurant with `serviceLevel` set to the neutral sentinel or `cuisine`/`distance`/`dineInTakeout` at their "no filter" values must yield a 0 count for that filter.
- Follow the existing PHPDoc array-shape style used elsewhere in this file.

## Requirements (Test Descriptions)
- [x] `it returns zero for a filter that excludes none of the base pool`
- [x] `it counts exclusions for the dineInTakeout filter independently of the other filters`
- [x] `it counts exclusions for the serviceLevel filter independently of the other filters`
- [x] `it counts exclusions for the cuisine filter independently of the other filters`
- [x] `it counts exclusions for the distance filter independently of the other filters`
- [x] `it returns all four filter keys in the result even when some counts are zero`
- [x] `it measures every filter against the same unfiltered base pool rather than cumulatively`

## Acceptance Criteria
- All requirements have passing tests in `tests/Feature/QuizServiceTest.php`.
- `buildPool()` behavior and its existing tests are unchanged (predicate extraction is a pure refactor).
- Code follows code standards; `vendor/bin/pint --dirty --format agent` run after edits.

## Implementation Notes
Added `QuizService::filterExclusionCounts(User $user, QuizAnswers $answers): array` returning
`{dineInTakeout, serviceLevel, cuisine, distance}` counts, each measured independently against
`Restaurant::ownedBy($user)->favorites()->get()` (the unfiltered base pool).

Extracted four private predicate methods (`excludedByDineInTakeout`, `excludedByServiceLevel`,
`excludedByCuisine`, `excludedByDistance`), each taking a loaded `Restaurant` + `QuizAnswers` and
returning whether that single filter would exclude it. `buildPool()` now reuses
`excludedByDineInTakeout`/`excludedByDistance` (its existing PHP-level filters) via `->reject()`;
its `serviceLevel`/`cuisine` SQL query constraints were left untouched, and
`excludedByServiceLevel`/`excludedByCuisine` are equivalent PHP-level reimplementations used only
by `filterExclusionCounts()`. All existing `buildPool()`/`topMatch()`/`runnerUp()` tests still pass
unchanged, confirming the refactor is behavior-preserving.

7 new tests added to `tests/Feature/QuizServiceTest.php`. Full suite: 651 passed. Pint clean.
