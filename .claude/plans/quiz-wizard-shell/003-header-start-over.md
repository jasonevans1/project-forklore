# Task 003: Header Start-Over Action

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Make `restart()` available from a header action during the `questions` state (today it only renders in the `empty` state). Task 001 already wires `clearSession()` into `restart()`, so start-over from mid-quiz already performs a full reset once the header entry point exists — this task is UI-only.

## Context
- Related files: `resources/views/pages/⚡quiz.blade.php` (the `questions` state markup's top/header area). Do NOT re-touch the `restart()` method to add `clearSession()` — Task 001 owns that wiring; adding it again here would double-clear.
- `restart()` already resets `state`, `step`, `restaurantId`, and all answer fields (and, after Task 001, clears the session). This task only adds the header button/markup that calls `wire:click="restart"` during the `questions` state (near the existing turn indicator at the top of that block).
- Keep the existing empty-state "Start over" button working unchanged — this task adds a second entry point, not a replacement.
- Patterns to follow: `flux:button` usage already present in this file for actions (e.g. the empty-state restart button).

## Requirements (Test Descriptions)
- [x] `it shows a start-over action in the header during the questions state`
- [x] `it resets to step 1 when start-over is triggered mid-quiz`
- [x] `it clears previously given answers when start-over is triggered mid-quiz`
- [x] `it clears the persisted session state when start-over is triggered`

## Acceptance Criteria
- All requirements have passing tests.
- Existing empty-state restart test in `QuizPageTest.php` still passes unmodified.
- Code follows code standards; `vendor/bin/pint --dirty --format agent` run after changes.

## Implementation Notes
- Added a `flex items-center justify-between` header row in the `questions` state (replacing the standalone turn-indicator `<p>`) containing the turn indicator on the left and a `flux:button size="sm" variant="ghost" wire:click="restart"` "Start over" action on the right.
- Requirements 2–4 (reset step, clear answers, clear session on mid-quiz restart) passed immediately once the test was written — `restart()` already handled this fully after Task 001's `clearSession()` wiring; this task only needed the new UI entry point, confirmed no over-implementation was needed here.
- Ran `vendor/bin/pint --dirty --format agent` (passed) and full parallel suite: 605 passed.
