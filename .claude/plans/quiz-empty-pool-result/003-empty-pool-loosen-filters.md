# Task 003: Quiz Component — Empty-Pool Ranking + Loosen Action

**Status**: completed
**Depends on**: [002]
**Retry count**: 0

## Description
Rebuild the quiz's `empty` state so it names the most restrictive filter and offers up to 3 ranked "Loosen X" buttons (via `QuizService::filterExclusionCounts()` and `neutralize()` from Tasks 001–002), each recomputing the pool with that one filter relaxed for this attempt only.

## Context
- Related files: `resources/views/pages/⚡quiz.blade.php`, `tests/Feature/QuizPageTest.php`
- `tests/e2e/quiz.spec.ts` hard-codes an `[data-flux-heading]:has-text("No matches found")` locator and a `Start over` button in the empty state — **do not rename or remove either**. Add the filter callout and buttons as new content alongside them.
- Loosening is one-time only per the plan's discovery notes: do **not** overwrite `$this->{$field}` (the stored wizard answer). Build the neutralized `QuizAnswers` via `QuizService::neutralize()` from `buildAnswers()`, call `QuizService::topMatch()` with it, and only use the result to populate `$restaurantId`/`$state` — the underlying stored wizard answers are untouched.
- **Result-consistency (do not skip):** a loosened result is a real result card. If the user then taps "Not this one" (`reject()`) or peeks the runner-up (Task 005), those actions currently call `runnerUp()`/`topMatch()` with `buildAnswers()`, which rebuilds the **original strict** answers — under which the pool is empty — so the user would be wrongly dumped straight to the empty state (or told "no other match") even when the loosened pool has more candidates. To prevent this, add `public ?string $activeLoosenedField = null`. On a successful `loosenFilter()`, set it to `$field`; clear it (`= null`) on a fresh `resolve()` and in `restart()`. Add a private helper `answersForCurrentResult(): QuizAnswers` that returns `buildAnswers()` neutralized by `$activeLoosenedField` when it is set, else plain `buildAnswers()`. `reject()` (Task 005) and `peekRunnerUp()` (Task 005) must use `answersForCurrentResult()` instead of `buildAnswers()` so they operate on the same pool the user is looking at. Do NOT change `buildAnswers()` itself or the step-navigation helpers that call it (`effectiveStepNumber`, `nextRawStep`, etc.) — those must keep reflecting the real answers.
- Add `public array $triedFilterLoosens = []` (field names attempted this session) and `$activeLoosenedField`, persisted the same way other quiz state is (via the existing `persistSession()`/`mount()` pair), and reset both in `restart()`. **`mount()` uses direct array access (`$snapshot['step']`); read the new keys with null-coalescing (`$snapshot['triedFilterLoosens'] ?? []`, `$snapshot['activeLoosenedField'] ?? null`) so an in-flight session written before this change does not throw on the missing key.** On a successful `loosenFilter()`, call `persistSession()` (the current `reject()` does not persist, so add the call in the loosen path explicitly).
- Add a computed method, e.g. `emptyStateFilters(): array`, returning up to 3 `[field => count]` entries from `QuizService::filterExclusionCounts()`, sorted descending by count, excluding zero-count fields and fields already in `$triedFilterLoosens`. Use it both to render the "most restrictive filter" headline copy (the top entry) and the loosen buttons.
- Add `public function loosenFilter(string $field): void`. On a found match: set `$restaurantId`/`$state = 'result'` (persist session). On still-empty: append `$field` to `$triedFilterLoosens` (persist session) and stay in `$state = 'empty'` — the next render's `emptyStateFilters()` naturally re-ranks without the tried field.
- When `emptyStateFilters()` is empty (no filter has any exclusion power left, or the user has zero favorites at all), render a generic fallback message instead of buttons — mirror the tone of Quick Pick's empty-state fallback text.
- Friendly labels for the 4 filter fields (e.g. "dine-in/takeout preference", "service level", "cuisine", "distance") belong in the blade view or a small private helper — follow whatever pattern the existing step-label code in this file already uses.

## Requirements (Test Descriptions)
- [x] `it shows a headline naming the most restrictive filter on the empty-pool screen`
- [x] `it renders up to 3 loosen-filter buttons ranked by exclusion count descending`
- [x] `it still shows the No matches found heading and Start over button on the empty-pool screen`
- [x] `it transitions to the result state when a loosen-filter button finds a match`
- [x] `it stays in the empty state and drops the tried filter from future ranking when the loosened pool is still empty`
- [x] `it does not overwrite the original stored answer when a loosen-filter attempt fails`
- [x] `it shows a generic fallback message when no filter has any exclusion power`
- [x] `it resets triedFilterLoosens and activeLoosenedField when the user starts over`
- [x] `it shows the runner-up from the loosened pool when Not this one is tapped after loosening a filter`

## Acceptance Criteria
- All requirements have passing tests in `tests/Feature/QuizPageTest.php`.
- `tests/e2e/quiz.spec.ts` assertions about the "No matches found" heading and "Start over" button remain valid (do not run Playwright here, but do not break those selectors).
- Code follows code standards; `vendor/bin/pint --dirty --format agent` run after edits.

## Implementation Notes
- Added `$triedFilterLoosens`, `$activeLoosenedField` to the quiz component; persisted/restored via `persistSession()`/`mount()` (null-coalescing reads for backward-compat session snapshots) and reset in `restart()`.
- Added `#[Computed] emptyStateFilters()` (top 3 non-zero, untried `filterExclusionCounts()` entries, desc) and public `filterFieldLabel()` helper (must be public — Blade single-file component templates call `$this->` methods outside class scope, so private methods aren't reachable from the view).
- Added `loosenFilter(string $field)`: builds a neutralized `QuizAnswers` via `QuizService::neutralize()` from `buildAnswers()` (original stored answers untouched), calls `topMatch()`; on hit sets `restaurantId`/`activeLoosenedField`/`state='result'`, on miss appends to `triedFilterLoosens`. Persists session either way.
- Added private `answersForCurrentResult()`: returns `buildAnswers()` neutralized by `activeLoosenedField` when set, else plain `buildAnswers()`. `reject()` now calls this instead of `buildAnswers()` so "Not this one" operates on the same (possibly loosened) pool the user is looking at. `resolve()` clears `activeLoosenedField` on a fresh result.
- Empty-state blade: kept the existing "No matches found" heading and "Start over" button untouched; added a headline naming the top-ranked filter, up to 3 "Loosen X" buttons (`wire:click="loosenFilter('field')"`), and a generic fallback message (mirrors Quick Pick's empty-state tone) when `emptyStateFilters()` is empty.
- 3 pre-existing tests that fully mock `QuizService` (`transitions to the empty state...`, `shows the empty state when the runner-up is also null`, `resets to step 1 when the user starts over from the empty state`) now also stub `filterExclusionCounts()` since the empty-state view calls it.
- All 9 new requirement tests added to `tests/Feature/QuizPageTest.php`; full suite (666 tests) and `pint --dirty` pass.
