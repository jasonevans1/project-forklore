# Plan: Quiz Wizard Shell

## Created
2026-07-10

## Status
completed

## Objective
Refactor the existing single-file Guided Quiz component (`pages::quiz`) into a skip-aware wizard shell that supports up to 7 step slots, extracts each question into its own Blade partial, persists progress in the session, and adds back navigation plus a header start-over action.

## Related Issues
none

## Discovery Notes
- `resources/views/pages/⚡quiz.blade.php` is a working, fully-tested Livewire SFC implementing the 5-question Guided Quiz mode (energy, hunger, familiarity, distance, cuisine) with hardcoded steps 1–5, `QuizService`/`QuizAnswers` resolution, `going`/`reject`/`restart` actions, and 30+ passing tests in `tests/Feature/QuizPageTest.php` (plus `tests/Feature/QuizServiceTest.php` and a Playwright e2e spec).
- No back navigation exists today. `restart()` only renders in the empty state, not from mid-quiz. No session persistence exists anywhere in the app yet — a full page reload re-mounts the Livewire component fresh and answers are lost.
- Decision (user, Phase 2): refactor the existing component in place rather than building a parallel component. The 5 real questions are ported into partials and preserved; `QuizService` resolution/going/reject logic is untouched.
- Decision (user, Phase 2): "up to seven steps" is realized as a fixed 7-slot step registry — the 5 existing questions occupy slots 1–5, and slots 6–7 are reserved, always-skipped placeholders (no real question yet). This makes the shipped example ("Step 3 of 5" when two of seven are skipped) literally true today, and proves the skip/effective-count math without inventing fake question content. Real questions 6/7 (if ever added) are a future plan.
- Existing partial convention in this codebase: anonymous Blade components under `resources/views/components/{feature}/...`, included via `<x-feature.partial />` (see `resources/views/components/restaurants/form-fields.blade.php` used by `⚡create.blade.php`). Question partials will follow this: `resources/views/components/quiz/steps/{name}.blade.php`.
- No session-based state precedent exists in the app (turn-taking uses `HouseholdState`, a DB model). This plan introduces the first `session()` usage, scoped to a single `quiz.wizard` key.

## Scope

### In Scope
- 7-slot step registry on the quiz component: `field`, view/partial name, and a `skip` flag per slot (slots 6–7 always skipped, slots 1–5 map to existing questions).
- Effective step numbering (`effectiveStepNumber`, `effectiveStepTotal`) that accounts for skipped slots, driving the progress indicator text.
- Skip-aware forward navigation (already exists via `answer()`, updated to jump over skipped slots) and skip-aware backward navigation (new `back()` action, thumb-zone button).
- Session persistence of wizard state (`step`, `state`, answer fields, `restaurantId`) under a single session key; hydrated on `mount()`, updated after every mutating action, cleared on start-over and on completion (`going`).
- Header "start over" action available during the `questions` state (not just the `empty` state), clearing session and resetting to step 1.
- Extraction of the 5 existing question blocks into individual Blade partials rendered by the parent wizard view.
- Updated/added feature tests for forward navigation with skip-aware step counts, backward navigation, session persistence across simulated reloads, and start-over from mid-quiz.
- Update `tests/e2e/quiz.spec.ts` with a `beforeEach` reset so the newly-introduced server-side session persistence does not leak wizard state between the shared-session Playwright tests (see Risks).

### Out of Scope
- Any real question content for slots 6–7 — they remain reserved/always-skipped placeholders.
- Changes to `QuizService`, `QuizAnswers`, weather/scoring logic, the result card, or the empty state.
- Changes to Quick Pick, Tonight, or Tournament modes.
- Conditional/dynamic skip logic driven by prior answers — skip is a static flag per slot in this plan (slots 6–7 hardcoded `skip: true`); answer-driven conditional skipping is a future extension if slots 6–7 ever get real questions.

## Success Criteria
- [ ] Progress indicator reads "Step X of 5" (never counting slots 6–7) throughout the quiz.
- [ ] Back button in the thumb zone returns to the previous non-skipped step, hidden on the first step.
- [ ] Reloading the page mid-quiz restores the current step and previously given answers.
- [ ] Header start-over action resets the wizard and clears session state from any step.
- [ ] Each question renders from its own Blade partial; existing question content/copy unchanged.
- [ ] All tests passing (existing + new).
- [ ] Code follows project standards.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Step registry, effective-count helpers, and session hydrate/persist | - | completed |
| 002 | Skip-aware back navigation | 001 | completed |
| 003 | Header start-over action (mid-quiz) | 001 | completed |
| 004 | Extract question partials and wire skip-aware progress indicator | 001, 002, 003 | completed |

## Architecture Notes
- Everything lives in the existing single Livewire SFC (`resources/views/pages/⚡quiz.blade.php`) plus new anonymous Blade component partials under `resources/views/components/quiz/steps/`. No new classes, services, or DB tables — the step registry is a private array method on the existing component (ponytail: a `WizardStep` value object or a generic reusable wizard trait would be speculative abstraction for a single 7-slot quiz; skip it).
- Session key: `quiz.wizard` (array snapshot of step/state/answers/restaurantId). Hydrated in `mount()`, written after every mutating action, cleared in `restart()` and `going()`.
- Tasks are a linear dependency chain (001 → 002/003 → 004) rather than a wide parallel fan-out: all four tasks edit the same single Blade file, so sequencing avoids merge conflicts between autonomous workers. 002 and 003 may run in parallel with each other (both depend only on 001, touch disjoint regions: thumb-zone footer vs. header).

## Risks & Mitigations
- **Same-file conflicts across tasks**: mitigated by the linear/narrow dependency chain above.
- **Regression in the existing, well-tested quiz flow**: mitigated by keeping `QuizService`/`QuizAnswers`/`going`/`reject`/result/empty states untouched, and requiring existing `QuizPageTest.php` assertions to keep passing alongside new ones.
- **Session state going stale/conflicting with a completed quiz**: mitigated by clearing the session key on both `restart()` and `going()` (wired in Task 001; Task 003 only adds the header UI, it does not re-add the clear).
- **E2E session contamination (new, from introducing session persistence)**: `SESSION_DRIVER=database` and all quiz Playwright tests share one auth session cookie via `storageState`. Persisted `quiz.wizard` from a test that ends mid-quiz would hydrate into the next test's fresh page load and break assertions like "Step 1 of 5". Mitigated by a `beforeEach` reset added to `tests/e2e/quiz.spec.ts` in Task 004. (Pest feature tests are unaffected — each test gets a fresh application/session.)
- **View-scope method visibility**: helpers the Blade template reads (`effectiveStepNumber`, `effectiveStepTotal`, `currentField`, `canGoBack`) must be `public` — a Livewire/Blade template cannot call `private`/`protected` methods on `$this`. Internal nav helpers (`nextRawStep`, `previousRawStep`, `steps`) stay private. Codified in Tasks 001/002/004.
