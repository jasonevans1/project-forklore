# Plan: Guided Quiz Hunger Scoring — Duration Basis

## Created
2026-07-13

## Status
completed

## Objective
Change the Guided Quiz's hunger scoring dimension from `price_level` to `avg_duration_minutes`, with new answer values (`quick_bite` / `full_meal` / `feast`) and matching UI copy.

## Related Issues
none

## Discovery Notes
- `QuizService`, `QuizAnswers`, `QuizQuestion`, and the full filter-then-score wizard (energy, hunger, familiarity, weather, `topMatch()`/`runnerUp()`) already exist and are fully tested — built by the prior `quiz-filter-steps` plan. This plan does **not** rebuild any of that.
- The only gap identified against this request: hunger currently scores `price_level` against answers `light`/`moderate`/`hungry` (`QuizService::scoreAll()` lines ~193–203). The request wants `avg_duration_minutes` scored against `quick_bite`/`full_meal`/`feast` — confirmed with user: **replace**, not combine with price.
- `avg_duration_minutes` is an existing nullable `unsignedSmallInteger` column on `restaurants` (already used by `QuickPickService` for a different mode). `RestaurantFactory` seeds it from `[45, 60, 75, 90, 120]`.
- Ideal-duration mapping: `quick_bite` → 45, `full_meal` → 75, `feast` → 120 (spread across the factory's bucket range, mirroring the existing `idealPrice` match-per-answer pattern). Gap-based bonus formula: `HUNGER_MATCH_BONUS - intdiv($gap * 2, 5)`, scaled so it lands in the same -5..+25 range as the price formula it replaces. `$r->avg_duration_minutes === null` skips the bonus entirely, mirroring the existing null-guard on `price_level`.
- The `match()` keeps a `default => 75` (full_meal) arm — same pattern the price code already uses for its `default => 2`. This means unrelated tests that pass `'moderate'` as a throwaway value just to advance the wizard (`TurnIndicatorTest`, `TurnBiasTest`, `VisitLoggingTest`, most of `QuizPageTest`) keep passing untouched; only tests that assert real hunger content need changes.
- Two files render literal hunger option labels and must stay in lockstep: `resources/views/components/quiz/steps/hunger.blade.php` and `tests/e2e/quiz.spec.ts` (which shares the `'Moderate'` label text between the energy and hunger steps for its default-fill helper, and separately asserts `'Light bite'`/`'Very hungry'` label text). Both are bundled into one frontend task to avoid drift. `QuizPageTest`'s one content-asserting hunger check (`wire:click="answer('hunger', 'light')"` / `assertSee('🥗 Light bite')`) is bundled in too, same reason.
- Backend (`QuizAnswers` default + `QuizService` scoring + `QuizServiceTest`) and frontend (blade partial + e2e spec + the one `QuizPageTest` content assertion) touch entirely disjoint files — no dependency between the two tasks.

## Scope

### In Scope
- `QuizAnswers::$hunger` docblock + default value updated to the new value set (default becomes `full_meal`)
- `QuizService::scoreAll()` hunger branch: score `avg_duration_minutes` instead of `price_level`, using the ideal-duration-per-answer + gap formula described above
- `tests/Feature/QuizServiceTest.php`: rewrite the two hunger-scoring tests (currently asserting on `price_level`) to assert on `avg_duration_minutes` with the new answer values; update `neutralAnswers()`'s hunger default
- `resources/views/components/quiz/steps/hunger.blade.php`: new option values/labels for `quick_bite` / `full_meal` / `feast`
- `tests/Feature/QuizPageTest.php`: update the one test asserting literal hunger option content (`wire:click`/label text)
- `tests/e2e/quiz.spec.ts`: update the shared `'Moderate'` default-fill label and the `'Light bite'`/`'Very hungry'` content assertions to match the new copy

### Out of Scope
- Energy, familiarity, distance, cuisine, service-level, or dine-in/takeout scoring/filtering — unchanged
- Weather integration, `topMatch()`/`runnerUp()`, pool-building/filter sequencing — unchanged
- Any change to `QuickPickService` or `TournamentService`, which also read `price_level`/`avg_duration_minutes` for unrelated modes

## Success Criteria
- [ ] Hunger scoring is computed from `avg_duration_minutes`, not `price_level`
- [ ] `quick_bite`/`full_meal`/`feast` are the only recognized hunger answer values in UI copy; unrecognized values (e.g. stray `'moderate'` in unrelated tests) fall back gracefully via the `default` match arm
- [ ] `hunger.blade.php` and `quiz.spec.ts` render/assert the same option labels (no drift)
- [ ] All tests passing
- [ ] Code follows project standards (Pint clean)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Backend: QuizAnswers default + QuizService duration-based hunger scoring + QuizServiceTest rewrite | - | completed |
| 002 | Frontend: hunger step partial copy + QuizPageTest content assertion + e2e spec updates | - | completed |

## Architecture Notes
- Both tasks are independent leaves — no shared files, run in parallel.
- Keep the `HUNGER_MATCH_BONUS` constant name (already generic, not price-specific) — only its doc comment and the branch logic change.

## Risks & Mitigations
- **Silent scoring mismatch** if the blade option `value=` attributes drift from the values `QuizService::scoreAll()` matches on (`quick_bite`/`full_meal`/`feast`) — mitigated by task 001 asserting the exact string values in `QuizServiceTest`, and task 002 wiring the same strings into `wire:click="answer('hunger', '...')"`.
- **e2e/unit drift**: `quiz.spec.ts` duplicates label text independently of the Blade partial (Playwright can't import PHP). Task 002 owns both files together specifically to prevent this.
- **Randomized-duration test flakiness**: `RestaurantFactory` randomizes `avg_duration_minutes` from `[45, 60, 75, 90, 120]`, so every restaurant in the untouched energy/familiarity tests now earns a *random* hunger bonus under the new `full_meal` default. The `intdiv($gap*2, 5)` scaling keeps that bonus spread to 18 points (`{7,13,19,25}`), safely below `ENERGY_MATCH_BONUS` (30) and the familiarity bonuses — so those pre-existing tests stay green. Task 001 must not steepen the multiplier (would flip energy tests) and must set `avg_duration_minutes` explicitly on both restaurants in the new hunger tests (else non-deterministic).
