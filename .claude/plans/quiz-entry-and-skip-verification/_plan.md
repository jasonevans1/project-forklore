# Plan: Quiz Entry Point and Smart-Skip Verification

## Created
2026-07-13

## Status
completed

## Objective
Add a "Start quiz" entry screen ahead of the Guided Quiz wizard, surface a subtle indicator on the result screen when steps were smart-skipped, log skip data on quiz completion for production verification, and add feature tests locking in the exact step sequence for both a fast-food (skip) and a fine-dining (no-skip) run.

## Related Issues
none

## Discovery Notes
The Guided Quiz is a single-file Livewire component (`resources/views/pages/⚡quiz.blade.php`) with a `state` property (`'questions' | 'result' | 'empty'`) and a fixed 7-slot step registry (`app/Enums/QuizQuestion.php`). `QuizQuestion::shouldSkip()` already skips the Energy and Familiarity steps whenever `serviceLevel === 'quick_easy'` ("Quick and easy" / fast food), and `effectiveStepTotal()`/`effectiveStepNumber()` already compute skip-aware progress off the current answers. There is currently no landing/intro screen — a GET to `/quiz` drops straight into step 1 — and no `Log::` calls exist anywhere in `app/`, so this plan establishes the first one on the default log channel.

`tests/Feature/QuizPageTest.php` and `tests/e2e/quiz.spec.ts` already cover the 7-step and 5-step (quick_easy) flows exhaustively via two shared helpers (`answerIntakeSteps()`/`completeAllSteps()` in Pest, `completeQuiz()`/`resetQuizState()` in Playwright). Gating the wizard behind a new intro state breaks any test that mounts the component and immediately asserts wizard content without an explicit "start" action first — both suites need surgical fixes alongside the new feature, not a rewrite.

Clarified with the user: the entry screen shows on every fresh visit to `/quiz` (no persisted "seen it before" flag — mount() simply skips the intro whenever a session snapshot shows the wizard already in progress). The skip indicator and the analytics log both fire only on a real restaurant match (not on the empty-pool path).

## Scope

### In Scope
- New intro/entry state on the Guided Quiz page: single "Start quiz" button in the thumb zone, copy setting expectations of "5-7 quick questions"
- Skips the intro automatically when resuming a session already in progress (mid-wizard, result, or empty)
- Subtle "we skipped N questions" indicator on the result card when the current answers caused a skip, computed from the existing `shouldSkip()`/step-registry logic (no new skip-reason taxonomy — today's only skip cause is `quick_easy`)
- `Log::info` entry on quiz completion (real match found) recording the skipped step field names and the reason, for production skip-logic verification
- Feature test: fast-food (`quick_easy`) run through the entry screen renders exactly the 5 non-skipped steps
- Feature test: fine-dining (`special_occasion`) run through the entry screen renders all 7 steps
- Repair existing Pest and Playwright quiz tests broken by the new intro gate

### Out of Scope
- Persisted "has completed the quiz before" flag or any DB/migration work
- Per-step or multi-reason skip taxonomy (only `quick_easy` exists today)
- Showing the skip indicator or logging on the empty-pool path
- A dedicated logging channel/analytics service — uses the default Laravel log channel

## Success Criteria
- [ ] `/quiz` shows an entry screen with a single "Start quiz" button on a fresh visit; resuming an in-progress session skips it
- [ ] Result card shows a skip indicator only when the current run actually skipped steps
- [ ] `Log::info` fires on every successful quiz resolution with `skipped_steps` (possibly empty) and a reason
- [ ] Feature tests lock in exactly 5 rendered steps for a fast-food run and all 7 for a fine-dining run, starting from the entry screen
- [ ] Existing Pest (`QuizPageTest.php`) and Playwright (`quiz.spec.ts`) suites pass unmodified in intent (same coverage, adjusted for the new entry step)
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add intro/entry screen + Start quiz action; keep Pest suite green | - | completed |
| 002 | Skipped-steps indicator + completion log + full end-to-end fast-food/fine-dining tests | 001 | completed |
| 003 | Update Playwright quiz.spec.ts for the new intro screen | 001 | completed |

## Architecture Notes
- Single-file Livewire component pattern preserved — no new files. `state` keeps its existing 3 values (`questions`/`result`/`empty`); a new `introDismissed` boolean gates only the intro sub-view within the `questions` state, so the many existing `assertSet('state', 'questions')` tests are unaffected.
- `introDismissed` is persisted in the same `session('quiz.wizard')` snapshot as the rest of the wizard state.
- Skip indicator and log both derive their skipped-step list from the existing `steps()` registry + `QuizQuestion::shouldSkip()` — no duplicated skip logic.

## Risks & Mitigations
- **Same-file test conflicts**: Tasks 001 and 002 both edit `QuizPageTest.php`; 002 depends on 001 to avoid parallel edits to the same file. Task 003 touches only `quiz.spec.ts` and can run in parallel with 002.
- **Silent breakage of unrelated tests**: gating wizard content behind `introDismissed` breaks ~9 existing Pest tests and most of `quiz.spec.ts` that mount/navigate fresh and assert wizard content directly. Task 001 explicitly enumerates and repairs the Pest cases; Task 003 covers the Playwright side via a single shared-helper fix (`resetQuizState`) rather than touching every test.
