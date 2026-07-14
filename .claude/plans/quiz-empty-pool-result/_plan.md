# Plan: Quiz Empty-Pool Handling and Result Screen

## Created
2026-07-13

## Status
completed

## Objective
Give the Guided Quiz a filter-aware empty-pool recovery screen and bring its result card to full parity with Quick Pick (distance, weather tagline, non-committal runner-up peek).

## Related Issues
none

## Discovery Notes
- `⚡quiz.blade.php` already has `questions` / `result` / `empty` states. `QuizService::topMatch()` and `runnerUp()` already exist and are exercised by `reject()`, which correctly re-scores excluding the rejected pick and falls back to `empty` — this part of the ask was already built and is covered by existing tests.
- The 4 hard filters (`dineInTakeout`, `serviceLevel`, `cuisine`, `distance`) are applied in `QuizService::buildPool()`. The 3 soft-scored answers (`energy`, `hunger`, `familiarity`) never exclude candidates, so they're irrelevant to empty-pool recovery.
- Quick Pick (`⚡pick.blade.php`) already computes a weather-aware tagline and a Haversine distance label; the Quiz result card has neither today, and the calculation logic is currently only implemented once (in `pick.blade.php`'s private methods) — extracting it avoids duplicating ~70 lines of weather/geometry logic.
- `tests/e2e/quiz.spec.ts` hard-asserts the empty state renders an h1 reading exactly "No matches found" and a "Start over" button — both must stay intact; new copy/buttons are additive only.
- Clarified with user: the new "show runner-up" link is a **non-committal peek** (reveals the runner-up's name without changing the displayed pick), distinct from "Not this one" which still does the real swap+rescore. Loosening a filter from the empty screen is **one-time only** (doesn't overwrite the stored answer) and, if still empty, **re-shows the empty screen re-ranked** (the just-tried filter drops out of consideration for this session).

## Scope

### In Scope
- `QuizService::filterExclusionCounts()` — per-filter exclusion counts against the base favorites pool.
- `QuizService::neutralize()` — returns a `QuizAnswers` clone with one filter field reset to its "no filter" value.
- Empty-pool screen: names the most restrictive filter, offers up to 3 ranked "Loosen X" buttons, tracks tried filters so a repeat empty result re-ranks instead of repeating, falls back to a generic message when no filter has any exclusion power.
- Result card: distance label + weather-aware tagline (via a shared trait extracted from Quick Pick's page component), plus a "Show runner-up" peek link.
- Feature tests (Pest) for all of the above.

### Out of Scope
- Any change to `tests/e2e/quiz.spec.ts` (existing assertions must keep passing unmodified).
- Persisting a loosened filter across the session (explicitly one-time only per user decision).
- Changes to Quick Pick's own behavior (only its duplicated calculation logic is extracted, not its logic).

## Success Criteria
- [x] Empty-pool screen identifies the most restrictive filter and offers ranked "Loosen X" buttons that recompute the pool.
- [x] Result card shows name, cuisine, distance, weather tagline, a non-committal "Show runner-up" peek, and the existing Going/Not this one actions.
- [x] "Not this one" still re-runs scoring excluding the rejected pick and falls back to empty when nothing remains.
- [x] All tests passing (`php artisan test --compact --parallel`).
- [x] Code follows project standards (Pint clean).

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | QuizService: per-filter exclusion counts | - | completed |
| 002 | QuizService: neutralize a filter answer | 001 | completed |
| 003 | Quiz component: empty-pool ranking + loosen action | 002 | completed |
| 004 | Extract shared tagline/distance trait from Quick Pick | - | completed |
| 005 | Quiz result card: distance, tagline, show-runner-up peek | 003, 004 | completed |

## Architecture Notes
- New trait lives at `app/Concerns/ComputesRestaurantPresentation.php`, matching the existing `app/Concerns/` home for shared traits (`ValidatesEventFields.php` et al.). Both `⚡pick.blade.php` and `⚡quiz.blade.php` page classes `use` it.
- Filter neutral values: `dineInTakeout` → `either`, `cuisine` → `null`, `distance` → `anywhere` (all pre-existing "no filter" answer values). `serviceLevel` has no existing neutral answer, so `QuizService` gains a private sentinel (e.g. `no_preference`) that matches none of the 4 service-level `match()` arms in `buildPool()`, and `neutralize()` is the only place that produces it.
- Task 001 and 002 both touch `QuizService.php`; Task 002 depends on 001 to avoid two autonomous workers editing the same file concurrently. Same reasoning applies to 003 → 005 (both touch `⚡quiz.blade.php`).
- Ranking in the empty-pool screen is computed live from `QuizService::filterExclusionCounts()` each render, filtered to exclude fields already in a `triedFilterLoosens` session-backed list and fields with a zero count, then capped to the top 3 by count.
- `filterExclusionCounts()` measures each filter as a PHP predicate over a single loaded base pool (`favorites()->get()`), because `buildPool()`'s `serviceLevel`/`cuisine` filters are SQL query constraints that can't be reused as-is; `buildPool()` itself is left unchanged to avoid a risky SQL→PHP rewrite (Task 001).
- A loosened result must stay pool-consistent: the component tracks `$activeLoosenedField` (set on a successful `loosenFilter()`, cleared on a fresh `resolve()`/`restart()`) and a `answersForCurrentResult()` helper applies it so `reject()`/`peekRunnerUp()` operate on the loosened pool, not the original strict (empty) pool (Tasks 003, 005). `mount()` reads the new session keys with null-coalescing so in-flight sessions don't break.

## Risks & Mitigations
- Changing empty-state copy could break `tests/e2e/quiz.spec.ts`'s exact-heading assertion: mitigated by keeping the "No matches found" heading and "Start over" button unchanged and only adding new copy/buttons around them.
- Extracting Quick Pick's tagline/distance logic could regress Quick Pick: mitigated by `tests/Feature/QuickPickPageTest.php` already covering that output, run as part of Task 004's TDD cycle before any Quiz-side usage.
