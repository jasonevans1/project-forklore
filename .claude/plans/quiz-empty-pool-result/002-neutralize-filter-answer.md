# Task 002: QuizService — Neutralize a Filter Answer

**Status**: completed
**Depends on**: [001]
**Retry count**: 0

## Description
Add a method that returns a copy of `QuizAnswers` with one named filter field reset to its "no filter" value, so the quiz can re-attempt a match with a single filter loosened without mutating the stored answers.

## Context
- Related files: `app/Services/QuizService.php`, `app/Services/QuizAnswers.php`, `tests/Feature/QuizServiceTest.php`
- `QuizAnswers` is a `readonly` class — build the neutralized copy via `new QuizAnswers(...)`, copying every field from the input except the one being neutralized.
- Neutral values: `dineInTakeout` → `'either'`, `cuisine` → `null`, `distance` → `'anywhere'` (these already mean "no filter" in `buildPool()`). `serviceLevel` has no existing neutral answer value — add a private class constant sentinel (e.g. `NEUTRAL_SERVICE_LEVEL = 'no_preference'`) that intentionally matches none of the 4 arms in `buildPool()`'s `serviceLevel` `match()`, and use it here.
- Signature: `public function neutralize(QuizAnswers $answers, string $field): QuizAnswers`. Throw `InvalidArgumentException` for any `$field` outside the 4 known filter names — this method is only ever called with a value drawn from `filterExclusionCounts()`'s keys (Task 001), never raw user input.

## Requirements (Test Descriptions)
- [x] `it neutralizes dineInTakeout to either`
- [x] `it neutralizes serviceLevel so no restaurant is excluded by service level`
- [x] `it neutralizes cuisine to null`
- [x] `it neutralizes distance to anywhere`
- [x] `it leaves every other answer field unchanged when neutralizing one field`
- [x] `it throws an InvalidArgumentException for an unrecognized filter field`

## Acceptance Criteria
- All requirements have passing tests in `tests/Feature/QuizServiceTest.php`.
- No change to `buildPool()`'s existing filter behavior.
- Code follows code standards; `vendor/bin/pint --dirty --format agent` run after edits.

## Implementation Notes
Added `QuizService::neutralize(QuizAnswers $answers, string $field): QuizAnswers`. Validates `$field` against the 4 known filter names (throws `InvalidArgumentException` otherwise), then rebuilds a new `QuizAnswers` copying every field, resetting only the targeted one: `dineInTakeout` → `'either'`, `cuisine` → `null`, `distance` → `'anywhere'`, `serviceLevel` → new sentinel constant `NEUTRAL_SERVICE_LEVEL = 'no_preference'` (matches none of the arms in `buildPool()`'s `serviceLevel` match). All 6 requirement tests added to `tests/Feature/QuizServiceTest.php` under a new "neutralize" section. Full suite (657 tests) and Pint both pass; `buildPool()` untouched.
