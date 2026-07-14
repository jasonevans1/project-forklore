# Task 002: Skipped-Steps Indicator, Completion Log, and Full End-to-End Flow Tests

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
On the result card, show a subtle indicator when the just-completed run skipped one or more wizard steps (e.g. "You picked fast food, so we skipped 2 questions"), so the smart-skip behavior reads as intentional rather than buggy. Log an entry when the quiz resolves to a real match, recording which steps were skipped and why, so skip logic can be verified in production. Finish by adding the two feature tests the plan exists for: a fast-food run that renders exactly the 5 non-skipped steps, and a fine-dining run that renders all 7.

## Context
- Single file to edit: `resources/views/pages/⚡quiz.blade.php`
- Tests to edit/add: `tests/Feature/QuizPageTest.php`
- Depends on Task 001 for the `startQuiz` action / intro gating and the repaired test suite — build on top of that, don't re-fix what Task 001 already fixed.
- Skip source of truth already exists: `steps()` (private method, lines ~376-387) returns the fixed 7-slot `QuizQuestion` registry; `QuizQuestion::shouldSkip(QuizAnswers $answers): bool` (in `app/Enums/QuizQuestion.php`) returns true for `Energy` and `Familiarity` when `serviceLevel === 'quick_easy'`. `effectiveStepTotal()` (lines ~334-341) already filters `steps()` by `! shouldSkip($answers)` and counts — reuse this, don't duplicate the filter logic.
- Add a private/computed helper, e.g. `skippedStepFields(): array` returning the field-name values (`$slot->value`) of every `QuizQuestion` in `steps()` where `shouldSkip($this->buildAnswers())` is true. This is what both the indicator and the log consume — compute it once, use it in both places.
- Indicator: add a `#[Computed]` method (follow the existing pattern used by `restaurant()` / `emptyStateFilters()` earlier in the file) that returns a nullable string — `null` when `skippedStepFields()` is empty, otherwise a message built with `trans_choice()` for correct pluralization, e.g. `trans_choice('You picked fast food, so we skipped :count question|You picked fast food, so we skipped :count questions', count($skipped), ['count' => count($skipped)])`. The literal reason text ("You picked fast food") is fine to hardcode — `quick_easy` is the only skip condition that exists today (see plan's Out of Scope: no multi-reason taxonomy).
- Render this message inside the `@if ($state === 'result' && $this->restaurant)` block (lines ~548-625), after the tagline/address text, before the action buttons — use `<flux:text>` with muted styling consistent with the tagline block just above it.
- Per the plan's scope decision, the indicator and the log **only** fire when a real match is found (the existing `resolve()` success path, lines ~420-439, and — since it's also a real match — the success path of `loosenFilter()`, lines ~211-232). They do **not** fire on the empty-pool path.
- Log call: use `Illuminate\Support\Facades\Log`. In `resolve()`, right after `$restaurant !== null` is confirmed (and likewise in `loosenFilter()`'s success branch), call:
  ```php
  Log::info('Quiz completed', [
      'user_id' => Auth::id(),
      'skipped_steps' => $skipped, // array of field-name strings, e.g. ['energy', 'familiarity']; [] when none
      'skip_reason' => $skipped === [] ? null : 'quick_easy_service_level',
  ]);
  ```
  Compute `$skipped` via the same `skippedStepFields()` helper added above.
- For the log tests: this is the first `Log::` call in the whole app (verified — there are zero other `Log::` calls in `app/` and no existing log-assertion patterns in `tests/`), so nothing else logs and it is safe to mock the facade fully. Use `Log::shouldReceive('info')->once()->with('Quiz completed', $expectedArray)` where `$expectedArray` includes `'user_id' => $this->user->id` (the test uses `actingAs`, so `Auth::id()` resolves to that user), `'skipped_steps' => [...]`, and `'skip_reason' => ...`. Alternatively `Log::spy()` then `Log::shouldHaveReceived('info')->once()->withArgs(...)`. Either is a valid Pest 4 idiom; pick one and keep the whole payload asserted so the test fails if a field name or the reason string drifts.
- The two full end-to-end tests are the ones this plan is ultimately for. Drive them through the **entire** real flow starting from the intro screen (`->call('startQuiz')` first, matching Task 001's pattern), not through the `answerIntakeSteps()`/`completeAllSteps()` shortcuts, since the point is to lock in the *exact* step sequence end to end:
  - Fast-food (`serviceLevel: 'quick_easy'`): assert the wizard renders exactly these 5 steps in order — dineInTakeout, serviceLevel, cuisine, hunger, distance (energy and familiarity skipped) — then resolves to the result state. Mock `QuizService::topMatch` to return a factory-created restaurant so the flow completes.
  - Fine-dining (`serviceLevel: 'special_occasion'`): assert all 7 steps render in order — dineInTakeout, serviceLevel, cuisine, energy, hunger, distance, familiarity — then resolves to the result state. Same mocking approach.
  - "Renders" here means asserting the step-specific content is visible at each step transition (follow the existing per-step content assertions already in the file, e.g. `assertSee('energy')`/`assertSee("What's your energy tonight?")` style, or the effective step counter text `Step X of Y`) — the goal is a single test per scenario that walks the whole thing and would fail if a step were added, removed, or reordered.

## Requirements (Test Descriptions)
- [x] `it shows a skipped-questions message on the result card when service level is quick_easy`
- [x] `it does not show a skipped-questions message on the result card when service level is not quick_easy`
- [x] `it logs the quiz completion with skipped step names and reason when service level is quick_easy`
- [x] `it logs the quiz completion with an empty skipped-steps list and no reason when no steps were skipped`
- [x] `it renders exactly the 5 non-skipped steps end to end, starting from the entry screen, for a fast-food quick_easy selection`
- [x] `it renders all 7 steps end to end, starting from the entry screen, for a fine-dining special_occasion selection`

## Acceptance Criteria
- All requirements above have passing tests
- Full `tests/Feature/QuizPageTest.php` suite passes (`php artisan test --compact tests/Feature/QuizPageTest.php`)
- `vendor/bin/pint --dirty --format agent` run and clean
- No decrease in test coverage

## Implementation Notes
Implemented in `resources/views/pages/⚡quiz.blade.php`:
- Added private `skippedStepFields(): array` helper (filters `steps()` by `QuizQuestion::shouldSkip($this->buildAnswers())`, maps to `->value`) — single source of truth used by both the indicator and the log.
- Added `#[Computed] skippedStepsMessage(): ?string`, using `trans_choice()` for pluralization; returns null when nothing was skipped.
- Rendered the message in the result card (`<flux:text>`, muted styling) right after the address block, before the action buttons.
- Added private `logQuizCompletion(): void` (calls `Log::info('Quiz completed', [...])` with `user_id`, `skipped_steps`, `skip_reason`), called from both `resolve()`'s success path and `loosenFilter()`'s success path — not on the empty-pool path.
- `tests/Feature/QuizPageTest.php`: added 6 new tests per the requirements list, including two full end-to-end wizard walks (starting from `startQuiz`) that assert per-step content and the `Step X of Y` counter at every transition for the quick_easy (5-step) and special_occasion (7-step) paths.
- Full suite: `ddev exec php artisan test --compact --parallel` → 687 passed. Pint clean.
