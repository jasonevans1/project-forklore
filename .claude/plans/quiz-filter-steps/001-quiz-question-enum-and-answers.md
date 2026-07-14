# Task 001: QuizQuestion Enum + QuizAnswers New Fields

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Introduce the `QuizQuestion` backed enum that models the wizard's 7 step slots and their skip logic, and extend `QuizAnswers` with the two new filter fields (`dineInTakeout`, `serviceLevel`) plus new distance bucket values. This is pure data-model groundwork — no Livewire component or service wiring yet.

## Context
- Related files: `app/Services/QuizAnswers.php` (existing), new file `app/Enums/QuizQuestion.php`
- Patterns to follow: existing backed enums with instance methods, e.g. `app/Enums/ServiceLevel.php`, `app/Enums/PatioQuality.php` — one case per value, methods via `match ($this)`
- Final step order (see `_plan.md` Discovery Notes): 1 `dineInTakeout`, 2 `serviceLevel`, 3 `cuisine`, 4 `energy`, 5 `hunger`, 6 `distance`, 7 `familiarity`
- `QuizQuestion` case values must equal the exact camelCase field/property names (`dineInTakeout`, `serviceLevel`, `cuisine`, `energy`, `hunger`, `distance`, `familiarity`) — these are reused as Livewire property names, `answer()` field keys, and blade partial filenames in later tasks
- `shouldSkip(QuizAnswers $answers): bool` — only `Energy` and `Familiarity` cases return true, and only when `$answers->serviceLevel === 'quick_easy'`; every other case always returns false
- New `QuizAnswers` fields:
  - `dineInTakeout: string` — `'dine_in' | 'takeout' | 'either'`, default `'either'`
  - `serviceLevel: string` — `'quick_easy' | 'casual_sit_down' | 'nicer_night_out' | 'special_occasion'`, default `'casual_sit_down'`
- `QuizAnswers::$distance` values change from `'nearby' | 'close' | 'anywhere'` to `'under_2_miles' | '2_to_5_miles' | '5_to_15_miles' | 'anywhere'`, default stays `'anywhere'`
- Update the PHPDoc property-read block on `QuizAnswers` to document the new/changed fields
- `QuizAnswers::$cuisine` stays `?string` (a `PrimaryCuisine` enum value or null for "surprise me") — no shape change, just a semantic note for later tasks

## Requirements (Test Descriptions)
- [x] `it returns false for shouldSkip on every question when service level is not set`
- [x] `it returns true for energy shouldSkip when service level is quick_easy`
- [x] `it returns true for familiarity shouldSkip when service level is quick_easy`
- [x] `it returns false for energy and familiarity shouldSkip when service level is casual_sit_down`
- [x] `it returns false for shouldSkip on dine-in/takeout, service level, cuisine, hunger, and distance regardless of service level`
- [x] `it defaults dineInTakeout to either and serviceLevel to casual_sit_down when not provided`
- [x] `it accepts the new distance bucket values under_2_miles, 2_to_5_miles, and 5_to_15_miles`

## Acceptance Criteria
- All requirements have passing tests (new `tests/Unit/Enums/QuizQuestionTest.php`)
- `vendor/bin/pint --dirty --format agent` run and clean
- No decrease in test coverage

## Implementation Notes
- Added `App\Enums\QuizQuestion` (string-backed, one case per wizard step) with `shouldSkip(QuizAnswers $answers): bool`, implemented as a single `match` — `Energy` and `Familiarity` skip when `serviceLevel === 'quick_easy'`, every other case always returns false.
- Extended `App\Services\QuizAnswers` with `serviceLevel` (default `'casual_sit_down'`) and `dineInTakeout` (default `'either'`) constructor-promoted properties; updated the PHPDoc property-read block to document both plus the new `distance` bucket values.
- `distance` remains an untyped `string` property (no enum/validation existed previously), so the new bucket values (`under_2_miles`, `2_to_5_miles`, `5_to_15_miles`) required no code change — test for that requirement passed immediately since the property never restricted its accepted values.
- Left existing `QuizService` distance-bucket matching logic (`'nearby'`/`'close'`/`'anywhere'`) untouched per task scope — that's service-wiring work for a later task.
- Full suite (616 tests) and `vendor/bin/pint --dirty --format agent` both green after the change.
