# Task 002: Skip-Aware Back Navigation

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Add a `back()` action and a thumb-zone back button that returns the wizard to the previous non-skipped step, using the `previousRawStep()` helper from Task 001. The button is hidden on the first effective step since there's nowhere to go back to.

## Context
- Related files: `resources/views/pages/⚡quiz.blade.php` (PHP class and the `questions` state markup).
- Depends on Task 001's `previousRawStep(int $from): ?int` helper and `persistSession()`/session hydration already being in place.
- `back()` should only operate during the `questions` state (result/empty states are unaffected — no back button there). If `previousRawStep($this->step)` returns `null`, `back()` is a no-op.
- Expose a **public** `canGoBack(): bool` helper (returns `$this->previousRawStep($this->step) !== null`) for the view to guard the button's visibility with `@if ($this->canGoBack())`. Do NOT call `previousRawStep()` directly from the Blade template — it is `private` (Task 001) and a Livewire/Blade template cannot call private methods on `$this`. The button must be hidden (not just disabled) on the first effective step.
- Going back must not clear the answer already given for the step being left — only navigate; the field values themselves are untouched so re-visiting a step later shows the previously chosen answer was retained (even though the UI itself doesn't need to visually pre-select it in this shell-only plan).
- Place the button in the "thumb zone" — bottom of the screen, consistent with how `going`/`reject` buttons are positioned in the result state (`px-6 pb-12 pt-6` footer pattern used later in the same file).
- Patterns to follow: existing `wire:click="methodName"` button conventions already used in this file (e.g. `wire:click="restart"`).

## Requirements (Test Descriptions)
- [x] `it returns to the previous step when back is tapped`
- [x] `it skips reserved placeholder slots when navigating back`
- [x] `it does not render a back button on the first effective step`
- [x] `it renders a back button on steps after the first`
- [x] `it persists the updated step to session after navigating back`
- [x] `it retains a previously given answer after navigating back then forward again`

## Acceptance Criteria
- All requirements have passing tests.
- No regression in existing `QuizPageTest.php` assertions.
- Code follows code standards; `vendor/bin/pint --dirty --format agent` run after changes.

## Implementation Notes
- Added public `back()` action and public `canGoBack(): bool` to the quiz Livewire component; `back()` delegates to the existing private `previousRawStep()`/`persistSession()` helpers from Task 001.
- Added a footer button (`wire:click="back"`, text "Back") to the bottom of the `questions` state block, guarded by `@if ($this->canGoBack())`, matching the `px-6 pb-12 pt-6` footer pattern used in the result state.
- One pre-existing unrelated failure observed in `QuizPageTest.php` (`it shows a start-over action in the header during the questions state`) belongs to the concurrently-developed Task 003 (header start-over button) and is outside this task's scope.
