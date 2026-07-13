# Task 006: End-to-End Wizard Flow (Happy + Skip Path) + Full-Suite Green

**Status**: completed
**Depends on**: 004
**Retry count**: 0

## Description
With the front-end (task 004) green, add full end-to-end coverage of both wizard paths through the component: the
happy path (all 7 steps → result) and the skip path (fast-food/fast-casual bypasses energy + familiarity → result
after 5 effective steps). These are component-level tests that mock `QuizService::topMatch()` (existing pattern),
so they exercise navigation/skip/step-count wiring, not the service filters. Finish with a full-suite run.

## Context
- Related files: `tests/Feature/QuizPageTest.php` (already green from task 004 — this task appends the e2e
  capstone cases and the step-count display assertions)
- Happy path walk: `dineInTakeout` (`'either'`) → `serviceLevel` (`'casual_sit_down'`) → `cuisine` (any value or
  null) → `energy` → `hunger` → `distance` → `familiarity` → asserts `state === 'result'`.
- Skip path walk: `dineInTakeout` (`'either'`) → `serviceLevel` (`'quick_easy'`) → `cuisine` → `hunger` →
  `distance` → asserts `state === 'result'` (energy and familiarity never shown; `effectiveStepTotal()` is 5).
- Step-count display: on step 1, `serviceLevel` is still null so the flow is casual-bound and the header shows
  "Step 1 of 7". After answering `serviceLevel` = `quick_easy` (now on step 3), the header shows "Step 3 of 5".
  (The original "Step 1 of 5 on the first step" phrasing was impossible — you can't know the service level before
  answering step 2.)
- Mock `QuizService::topMatch()` (and `runnerUp()` where needed) as the existing result-state tests already do.

## Requirements (Test Descriptions)
- [x] `it completes the full 7-step happy path and reaches the result state`
- [x] `it completes the 5-step skip path when service level is quick_easy and reaches the result state`
- [x] `it shows Step 1 of 7 on the first step for a casual-bound flow`
- [x] `it shows Step 3 of 5 after service level quick_easy has been answered`

## Acceptance Criteria
- All requirements have passing tests in `tests/Feature/QuizPageTest.php`
- Full suite green: `php artisan test --compact --parallel`
- `vendor/bin/pint --dirty --format agent` run and clean
- No decrease in test coverage

## Implementation Notes
Added all 4 capstone tests to `tests/Feature/QuizPageTest.php`, following the existing mocking pattern
(`$this->mock(QuizService::class)->allows('topMatch')->andReturn(...)` + `answerIntakeSteps()` helper). All 4
tests passed immediately on first run with no production-code changes required — task 004's wizard rewrite
already fully implemented the skip-aware navigation, `effectiveStepNumber()`/`effectiveStepTotal()` helpers,
and "Step X of Y" header display that these tests assert against. This confirms task 004 did not over- or
under-implement relative to this task's acceptance criteria.

Full suite: 634 passed (1345 assertions). Pint: clean, no changes needed.
