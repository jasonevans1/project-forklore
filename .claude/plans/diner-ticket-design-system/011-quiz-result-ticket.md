# Task 011: Quiz Result → Result Ticket

**Status**: pending
**Depends on**: 003
**Retry count**: 0

## Description
Replace Guided Quiz's result-state informational markup with `<x-restaurant-result-ticket>`. Everything else in this 755-line file — the entire wizard, all seven question steps, the progress indicator, the skipped-steps message, the runner-up peek, and the filter-loosening empty state — stays exactly as it is.

## Context
- Related files: `resources/views/pages/⚡quiz.blade.php`, existing `tests/Feature/QuizPageTest.php`
  (~1,090 lines, the largest test file in the project)
- Sole owner of this view and `tests/Feature/QuizPageTest.php` — safe to run in parallel with
  Tasks 006–010, 012.
- **Do not edit `resources/css/app.css`** — Task 001 owns it.
- **Never uppercase literal Blade strings** — `Start quiz`, `Going`, `Not this one`,
  `Show runner-up`, `Start over`, `No matches found`, `Step 3 of 5`, `Add more favorites`,
  `🎉 Lively`, `🥪 Quick bite` and dozens more are asserted case-sensitively. Use the CSS `uppercase`
  utility.

## Exact replacement region — read this before editing
Only lines **640–681** are replaced:

| Lines | Content | Action |
|---|---|---|
| 638 | `@if ($state === 'result' && $this->restaurant)` | keep |
| 639 | outer `<div class="flex flex-1 flex-col">` | keep |
| 640 | card body wrapper | keep |
| **641–680** | badge → heading → cuisine tags → price/distance meta → tagline → address | **REPLACE with `<x-restaurant-result-ticket>`** |
| 682–686 | `@if ($this->skippedStepsMessage)` block | **KEEP verbatim**, immediately after the component |
| 689–719 | CTA group: Going, Not this one, Show runner-up, `$peekedRunnerUpName` display | keep verbatim |

The previously stated range "638–687" wrongly included the skipped-steps block, contradicting the
instruction to leave it in place.

```blade
<x-restaurant-result-ticket
    :restaurant="$this->restaurant"
    :badge-label="__('Quiz Pick')"
    :tagline="$tagline"
    :distance-label="$distanceLabel"
/>
```

## Forbidden regions — do not touch, refactor, reformat or "tidy"
This file is the largest in the plan and has by far the most test coverage behind it. Anything
outside lines 641–680 is out of scope, specifically:

- **Lines 1–~500 (the PHP class block)** — quiz state machine, `answer()`, `back()`, `restart()`,
  `peekRunnerUp()`, `loosenFilter()`, `skippedStepsMessage`, `emptyStateFilters()`,
  `filterFieldLabel()`, `effectiveStepNumber()`, `effectiveStepTotal()`, `currentField()`,
  `canGoBack()`.
- **The intro/idle state** — `Start quiz`, `5-7 quick questions`.
- **The progress indicator (lines 608–614)** — `QuizPageTest` asserts exact strings
  `Step 1 of 7`, `Step 3 of 5`, `Step 5 of 7`, `Step 7 of 7`, and `assertDontSee('Step 1 of 5')`.
  The bar classes (`bg-zinc-800 dark:bg-zinc-100`) are deliberately left on the old palette by the
  approved scope — **do not restyle them**.
- **The step host (lines 616–620)** and every `resources/views/components/quiz/steps/*.blade.php`
  file — `QuizPageTest` asserts raw HTML including `assertSeeHtml('grid grid-cols-4 gap-3')` and
  ~30 `wire:click="answer(...)"` strings.
- **The Back button (622–631)** — `assertDontSee('Back')` / `assertSee('Back')` tests.
- **The empty state (726–753)** — `loosenFilter` buttons, `assertSeeHtmlInOrder`, `No matches found`,
  `Add more favorites`, `Start over`.

If the result block cannot be replaced without touching one of these, stop and escalate rather than
widening the diff.

## Requirements (Test Descriptions)
Existing tests in `QuizPageTest.php` must continue to pass unchanged. Add:
- [x] `it renders the result using the restaurant result ticket component`
- [x] `it still shows the skipped-steps message below the result ticket`
- [x] `it still shows the going, reject, and runner-up buttons after the restyle`

## Acceptance Criteria
- All requirements have passing tests added to the existing `tests/Feature/QuizPageTest.php`; **every**
  pre-existing test in that file still passes without modification.
- Result state renders via `<x-restaurant-result-ticket>` (Task 003); CTA buttons, runner-up peek and
  the skipped-steps message are unchanged in behavior and remain in their current relative order.
- The PHP class block of `⚡quiz.blade.php` is byte-for-byte unchanged.
- No file under `resources/views/components/quiz/` is modified.
- `resources/css/app.css` is not modified.
- No literal Blade string is uppercased in source.
- No decrease in test coverage.
- `vendor/bin/pint --dirty --format agent` clean.

## Implementation Notes
Replaced the badge/heading/cuisine-tags/price-distance/tagline/address block inside
`@if ($state === 'result' && $this->restaurant)` with a single
`<x-restaurant-result-ticket :restaurant="$this->restaurant" :badge-label="__('Quiz Pick')"
:tagline="$tagline" :distance-label="$distanceLabel" />` call. The skipped-steps message and CTA
group (Going / Not this one / Show runner-up / peeked runner-up name) were left byte-for-byte
untouched immediately after the component, in the same relative order.

Added 3 new tests to `tests/Feature/QuizPageTest.php` under a "Restaurant result ticket restyle"
section, reusing the existing `completeAllSteps()` / `answerIntakeSteps()` helpers. Only the first
test (`renders the result using the restaurant result ticket component`, asserting the
`cuisine-tags` marker class from the component) was RED before the implementation — the other two
already passed against the pre-existing markup since CTA/skipped-message behavior was unchanged,
confirming no regression.

All 90 pre-existing + new tests in `QuizPageTest.php` pass, plus the 13
`RestaurantResultTicketComponentTest.php` tests (103 total). `vendor/bin/pint --dirty --format
agent` reports clean. A full parallel suite run shows one unrelated failure in
`HistoryPageTest.php` caused by a concurrently-running sibling task (007) editing
`⚡history.blade.php` — not touched by this task.
