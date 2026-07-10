# Task 004: Extract Question Partials and Wire Skip-Aware Progress

**Status**: completed
**Depends on**: 001, 002, 003
**Retry count**: 0

## Description
Extract each of the 5 existing inline `@if ($step === N)` question blocks into its own Blade partial rendered by the parent wizard, and update the progress indicator to use Task 001's effective-step helpers instead of the hardcoded "of 5" text (which happens to still read "of 5" today, but must now be derived from the registry, not a literal).

## Context
- Related files: `resources/views/pages/⚡quiz.blade.php` (the `questions` state markup), new files under `resources/views/components/quiz/steps/`, and `tests/e2e/quiz.spec.ts` (must be updated — see the e2e note below).
- **E2E session-contamination fix (required).** `SESSION_DRIVER=database` and every quiz Playwright test shares one authenticated session cookie via `tests/e2e/.auth/user.json` (`storageState`). Task 001 now persists `quiz.wizard` server-side. Tests that advance mid-quiz and don't finish (e.g. "progress bar advances to step 4", the loading-indicator test, the runner-up/reject tests that leave a `result` state) will leave stale wizard state in the shared server session, so the NEXT test's `page.goto('/quiz')` hydrates that leftover step/state instead of a clean "Step 1 of 5" / energy question — breaking the existing green e2e suite. Fix: add a `test.beforeEach` in `tests/e2e/quiz.spec.ts` (inside the authenticated `describe`s) that navigates to `/quiz` and, if a start-over control is present (the header entry point from Task 003 during `questions`, or the empty-state "Start over"), clicks it to reset the wizard to a clean step 1 before the test body runs. Verify the full `quiz.spec.ts` still passes after the refactor. Note the existing text assertions (`Step 1 of 5`, `Step 2 of 5`, …) must keep rendering identically — the derived `effectiveStepNumber`/`effectiveStepTotal` values equal the old literals today, so preserve the exact `Step :step of :total` output ("Step 1 of 5", not "Step 1 of 7").
- Existing partial convention in this codebase: anonymous Blade components under `resources/views/components/{feature}/...`, included via `<x-feature.partial />` — see `resources/views/components/restaurants/form-fields.blade.php` used from `resources/views/pages/restaurants/⚡create.blade.php`. Follow the same pattern: `resources/views/components/quiz/steps/energy.blade.php`, `hunger.blade.php`, `familiarity.blade.php`, `distance.blade.php`, `cuisine.blade.php`, each containing exactly the content currently inside its corresponding `@if ($step === N) ... @endif` block (heading, subtext, option buttons), unchanged.
- Parent view renders the right partial by field name off the Task 001 registry, e.g. `<x-dynamic-component :component="'quiz.steps.' . $this->currentField()" />`, using the **public** `currentField()` helper added in Task 001 (do NOT reach into the private `steps()` array from the template — a Blade template can't call private methods on `$this`). Alternatively a `@switch`/match on `$this->currentField()` including each partial explicitly. Prefer whichever is fewer lines; do not build a generic partial-resolution abstraction beyond what 5 known partials need (ponytail: a dynamic-component lookup is fine since it's one line; don't add a registry-driven view-resolver class).
- Replace the progress bar loop (`@foreach (range(1, 5) as $i)`) and the `{{ __('Step :step of 5', ...) }}` text so both derive from the **public** `$this->effectiveStepTotal()` / `$this->effectiveStepNumber($step)` helpers rather than the literal `5`. The rendered text must stay exactly "Step 1 of 5" etc. (values are equal to the old literals today) so existing feature and e2e assertions keep passing.
- Do not touch the result or empty state markup, or any `QuizService` interaction.

## Requirements (Test Descriptions)
- [x] `it renders the energy question from its own partial on step 1`
- [x] `it renders the cuisine question from its own partial on the last effective step`
- [x] `it displays step and total counts derived from the effective step total, not a hardcoded number`
- [x] `it still shows all 5 question option buttons with unchanged copy after extraction`
- [x] `tests/e2e/quiz.spec.ts` gains a `beforeEach` reset so persisted wizard state does not leak between tests, and the full spec passes after the refactor (verified manually / in CI, not a Pest test).

## Acceptance Criteria
- All requirements have passing tests.
- All pre-existing `QuizPageTest.php` assertions (including `assertSee` on question copy) still pass unmodified.
- Code follows code standards; `vendor/bin/pint --dirty --format agent` run after changes.

## Implementation Notes
- Created 5 anonymous Blade component partials under `resources/views/components/quiz/steps/` (energy, hunger, familiarity, distance, cuisine), each containing the unchanged heading/subtext/option markup previously inline in `⚡quiz.blade.php`.
- Parent view now renders `<x-dynamic-component :component="'quiz.steps.' . $this->currentField()" />` guarded by `@if ($this->currentField())` — the guard preserves existing behavior for the always-skipped reserved slots 6/7 (`currentField()` returns null there), which a test exercises directly via `->set('step', 7)`.
- Progress bar loop now iterates `range(1, $this->effectiveStepTotal())` and compares against `$this->effectiveStepNumber($step)`; the "Step X of Y" text now uses `__('Step :step of :total', ...)` built from both effective-step helpers instead of the literal `5`.
- `it still shows all 5 question option buttons with unchanged copy after extraction` passed immediately (RED phase) since it validates pre-existing behavior — noted as a regression guard for the extraction rather than newly-introduced behavior, per TDD over-implementation note.
- Added a `test.beforeEach` to both authenticated `describe` blocks in `tests/e2e/quiz.spec.ts` that navigates to `/quiz` and clicks "Start over" if visible, to reset the server-side `quiz.wizard` session state between tests (all quiz e2e tests share one auth session cookie). Not run in this environment (no Playwright/browser harness available here); syntax-checked with `node --check`.

## Post-implementation fixes (orchestrator, after a real Playwright run became available)
- Running the actual e2e suite surfaced two real regressions the worker's syntax-only check couldn't catch:
  1. **`fullyParallel: true` + shared `storageState`**: all quiz tests authenticate via the same session cookie, so now that `quiz.wizard` persists server-side, concurrent parallel workers raced on the same session. Fixed by adding `test.describe.configure({ mode: 'serial' })` to both `guided quiz` describe blocks.
  2. **Reset couldn't escape the result state**: the original `beforeEach` only clicked "Start over" if visible, but the result card has no such control (by design/scope) — a test ending on the result state left it un-resettable, and even a "spam Not this one" fallback isn't safe since `reject()`/`runnerUp()` can oscillate between the same two restaurants forever with no guaranteed path to empty. Fixed by replacing the whole reset with a single deterministic call directly into the Livewire component's `restart` action via `page.evaluate(() => window.Livewire.first().call('restart'))`, which works from any state (questions/result/empty) in one round trip.
- Verified: `npx playwright test tests/e2e/quiz.spec.ts --project=chromium --workers=1` passes 27/29 (2 pre-existing skips) reproducibly across multiple runs. The `mobile` Playwright project uses WebKit, which isn't installed in this environment (`ms-playwright/webkit-2311` missing) — a pre-existing environment gap unrelated to this plan; verified via the stashed pre-plan baseline failing identically on WebKit for the same reason.
