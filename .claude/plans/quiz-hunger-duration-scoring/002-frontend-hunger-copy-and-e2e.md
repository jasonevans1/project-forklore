# Task 002: Frontend — Hunger Step Copy + e2e Spec

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Update the hunger step's Blade partial to the new `quick_bite` / `full_meal` / `feast` option values and copy, then update the one `QuizPageTest` assertion and the `tests/e2e/quiz.spec.ts` helper/assertions that reference the old `light`/`moderate`/`hungry` labels — these three files must stay in lockstep since Playwright duplicates label text independently of the Blade partial.

## Context
- `resources/views/components/quiz/steps/hunger.blade.php`: currently a 3-option `@foreach` over `light`/`moderate`/`hungry` with labels `🥗 Light bite`, `🍝 Moderate`, `🥩 Very hungry`. Replace the array with the new values/labels, e.g.:
  ```php
  ['value' => 'quick_bite', 'label' => '🥪 Quick bite', 'sub' => 'In and out fast'],
  ['value' => 'full_meal', 'label' => '🍝 Full meal', 'sub' => 'A solid sit-down meal'],
  ['value' => 'feast', 'label' => '🥩 Feast', 'sub' => 'Take your time, go big'],
  ```
  Exact emoji/copy wording is a low-stakes content default — adjust freely as long as the `value=` strings are exactly `quick_bite`, `full_meal`, `feast` (these must match what task 001 wires into `QuizService::scoreAll()`'s match arms).
- `tests/Feature/QuizPageTest.php` (~line 613): the test `'still shows all 7 question option buttons with unchanged copy after extraction'` currently has
  ```php
  ->assertSeeHtml("wire:click=\"answer('hunger', 'light')\"")
  ->assertSee('🥗 Light bite')
  ```
  Update both lines to match whatever value/label you chose for the "quick" option in the blade partial (e.g. `answer('hunger', 'quick_bite')` / `'🥪 Quick bite'`). No other line in this file needs to change — every other `->call('answer', 'hunger', 'moderate')` in this file is just advancing the wizard past the hunger step (not asserting on hunger content), and `QuizService::scoreAll()`'s `default` match arm (task 001) means an unrecognized `'moderate'` value there doesn't break anything.
- `tests/e2e/quiz.spec.ts`: the `completeQuiz()` helper's default `hunger = 'Moderate'` (~line 39) relies on the hunger step's middle option sharing the literal label text `'Moderate'` with the energy step's middle option — that's how the shared-default-fill trick and the "steps 4 and 5 both have a 'Moderate' button" comment (~line 48) currently work. Once the hunger labels change, that default needs to become whatever label you gave the "full" option (e.g. `'Full meal'`), and the inter-step race-condition comment should be updated to no longer claim both steps share the same label.
  - Lines ~195–196, ~205–206, ~267–268, ~281–282, ~523–524: each pair clicks `/Moderate/i` twice in a row (once for energy, once for hunger). The second click in each pair must target the new "full" option's label instead.
  - Lines ~258–259: `'step 5 shows the hunger question'` asserts `getByRole('button', { name: /Light bite/i })` and `/Very hungry/i` are visible — update to the new "quick" and "feast" labels.
  - Lines ~418 and ~455: `hunger: 'Light bite'` in test option objects — update to the new "quick" label.
  - Keep the values consistent with whatever exact copy you picked in `hunger.blade.php` in this same task — there is no PHP-to-TS import, so drift here is silent until the e2e suite runs.

## Requirements (Test Descriptions)
- [x] `it shows the hunger question on step 5` (existing test, `QuizPageTest` — must still pass unmodified; verifies no regression from the copy change) — passed unmodified before and after the blade change.
- [x] `it shows all 7 question option buttons with unchanged copy after extraction` (existing test, `QuizPageTest` — updated to assert the new hunger value/label instead of the old one) — asserts `quick_bite` / `🥪 Quick bite`.
- [x] Playwright: `step 5 shows the hunger question` (`quiz.spec.ts`) asserts the new quick/feast button labels are visible — now asserts `/Quick bite/i` and `/Feast/i`.
- [x] Playwright: full `completeQuiz()` happy-path flow still resolves to a result when using the new default hunger label — default `hunger` is now `'Full meal'`; verified via `chromium` project run (34/34 passed).

## Acceptance Criteria
- All requirements have passing tests
- `hunger.blade.php` option `value=` strings exactly match `quick_bite` / `full_meal` / `feast` (the strings task 001 wires into `QuizService`)
- `php artisan test --compact --filter=QuizPageTest` passes
- Playwright `quiz.spec.ts` passes (`npx playwright test tests/e2e/quiz.spec.ts` or project's existing e2e run command)
- `vendor/bin/pint --dirty --format agent` clean (for any touched PHP)

## Implementation Notes
- New hunger option values/labels: `quick_bite` (🥪 Quick bite), `full_meal` (🍝 Full meal), `feast` (🥩 Feast).
- `completeQuiz()`'s default `hunger` changed from `'Moderate'` to `'Full meal'`; removed the now-inaccurate "steps 4 and 5 share a Moderate button" comment since the labels no longer collide.
- Full PHP suite: `ddev exec php artisan test --compact --parallel` — 641 passed.
- Playwright `chromium` project: `npx playwright test tests/e2e/quiz.spec.ts --project=chromium` — 34/34 passed.
- Playwright `mobile` project failed with `browserType.launch: Executable doesn't exist ... webkit-2311` — a missing local browser binary in this environment (`npx playwright install` never run for webkit), unrelated to the copy change. Not fixed here since it's an environment/tooling gap, not a test logic issue.
