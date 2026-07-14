# Task 004: Extract Shared Tagline/Distance Trait From Quick Pick

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Extract Quick Pick's weather-aware tagline and Haversine distance-label logic (currently private methods on the `⚡pick.blade.php` page class) into a shared trait so the Quiz result card can reuse it instead of duplicating ~70 lines of weather/geometry code (Task 005).

## Context
- Related files: `resources/views/pages/⚡pick.blade.php` (source of the logic to extract), new file `app/Concerns/ComputesRestaurantPresentation.php`, `tests/Feature/QuickPickPageTest.php` (regression guard)
- `app/Concerns/` already holds shared traits (`ValidatesEventFields.php`, etc.) — follow that pattern and directory.
- Move `resolveTagline()`, `resolveDistanceLabel()`, and `haversineMiles()` verbatim into the trait (same method bodies, same signatures), then have the `⚡pick.blade.php` page class `use ComputesRestaurantPresentation;` and delete its now-duplicate private methods.
- This is a pure extraction — no behavior change. `tests/Feature/QuickPickPageTest.php` already exercises the tagline/distance output; it must pass unmodified after the move.
- Do not touch `⚡quiz.blade.php` in this task (Task 005 wires the trait into Quiz).

## Requirements (Test Descriptions)
- [x] `it still shows a weather-aware tagline on the Quick Pick result card after extraction`
- [x] `it still shows a distance label on the Quick Pick result card after extraction`
- [x] `it still shows no distance label when the user's coordinates are unavailable`

## Acceptance Criteria
- All requirements have passing tests (extend `tests/Feature/QuickPickPageTest.php` if these exact cases aren't already covered; if they already exist and pass, note that in Implementation Notes instead of duplicating them).
- Every pre-existing test in `tests/Feature/QuickPickPageTest.php` still passes unmodified.
- `app/Concerns/ComputesRestaurantPresentation.php` created following existing trait conventions in that directory.
- Code follows code standards; `vendor/bin/pint --dirty --format agent` run after edits.

## Implementation Notes
- None of the three required test cases existed verbatim before; added them to `tests/Feature/QuickPickPageTest.php`. Since this is a pure extraction, all three passed immediately against the pre-extraction code (no RED phase possible for this task type) — confirmed, then performed the extraction as a structural refactor.
- Created `app/Concerns/ComputesRestaurantPresentation.php` with `resolveTagline()`, `resolveDistanceLabel()`, and `haversineMiles()` moved verbatim (same bodies/signatures) from `⚡pick.blade.php`, following the `ValidatesEventFields` trait convention (PHPDoc documents the required `$lat`/`$lng` properties on the consuming class).
- `⚡pick.blade.php` now does `use ComputesRestaurantPresentation;` and no longer has its own copies of those methods; removed now-unused `IndoorVibe`/`PatioQuality`/`WeatherService` imports from the page (still used inside the trait).
- Full targeted suite: `QuickPickPageTest` 18/18 passing. Full parallel suite: 651/651 passing (an earlier flaky run showed unrelated failures in `QuizServiceTest.php`/`QuizService.php`, caused by a concurrent task-agent mid-edit on those files in the shared worktree — confirmed via `git stash` that those failures pre-date and are unrelated to this task's changes).
- `vendor/bin/pint --dirty --format agent` run, clean.
