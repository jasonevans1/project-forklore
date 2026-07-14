# Task 001: Backend — Duration-Based Hunger Scoring

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Change `QuizService`'s hunger scoring branch to score `Restaurant::avg_duration_minutes` instead of `Restaurant::price_level`, using new answer values `quick_bite` / `full_meal` / `feast`. Update `QuizAnswers`'s default and docblock, and rewrite the existing price-based hunger tests in `QuizServiceTest` to assert on duration.

## Context
- `app/Services/QuizService.php` (lines ~193–203 in the current `scoreAll()` method): the hunger branch currently does
  ```php
  $idealPrice = match ($answers->hunger) {
      'light' => 1,
      'hungry' => 4,
      default => 2,
  };

  if ($r->price_level !== null) {
      $gap = abs($r->price_level - $idealPrice);
      $score += self::HUNGER_MATCH_BONUS - ($gap * 10);
  }
  ```
  Replace with an equivalent structure keyed on `avg_duration_minutes`:
  - `quick_bite` → ideal 45, `full_meal` → ideal 75 (also the `default` arm), `feast` → ideal 120.
  - Guard on `$r->avg_duration_minutes !== null` (same null-guard pattern as the code being replaced).
  - `$gap = abs($r->avg_duration_minutes - $idealDuration);`
  - `$score += self::HUNGER_MATCH_BONUS - intdiv($gap * 2, 5);` — this scaling keeps the bonus in roughly the same -5..+25 range as the price formula it replaces (verify with the factory's duration buckets `[45, 60, 75, 90, 120]` while writing tests).
  - **Do not use a steeper multiplier than this formula.** This is load-bearing: `RestaurantFactory` randomizes `avg_duration_minutes` from `[45, 60, 75, 90, 120]` on every restaurant, so the untouched energy/familiarity tests (which use `neutralAnswers()`, now defaulting hunger to `full_meal` → ideal 75) each get a *random* hunger bonus per restaurant. Across those buckets this formula produces bonuses of `{7, 13, 19, 25}` — an 18-point spread, which stays safely under `ENERGY_MATCH_BONUS` (30) and the familiarity bonuses (20–50). If the hunger spread ever reaches or exceeds 30, the pre-existing energy test (matching-vibe restaurant should win by +30) can tie or flip and start failing intermittently — a failure that looks unrelated to this change. Keep the spread well under 30.
  - Update the `HUNGER_MATCH_BONUS` doc comment (currently "Bonus/penalty scaling for price_level vs hunger answer.") to reference duration instead of price.
  - Update the inline comment above the match (currently "Hunger — ideal price_level per answer: light=1, moderate=2, hungry=4").
- `app/Services/QuizAnswers.php`: the `$hunger` property docblock (`'light' | 'moderate' | 'hungry'`) and the constructor default (`public string $hunger = 'moderate'`) both need updating to the new value set — default becomes `'full_meal'`.
- `tests/Feature/QuizServiceTest.php`:
  - `neutralAnswers()` helper (line ~48): `hunger: $overrides['hunger'] ?? 'moderate'` → change the fallback to `'full_meal'`.
  - The "Hunger scoring" test block (lines ~148–172) has two tests keyed on `price_level` (`hunger=hungry` favors high `price_level`, `hunger=light` favors low `price_level`). Rewrite both to use `avg_duration_minutes` and the new answer values (e.g. `hunger=feast` should favor a restaurant with `avg_duration_minutes` close to 120 over one close to 45; `hunger=quick_bite` the reverse). Keep restaurants' `vibe_tags` neutral (`['casual']`) like the existing tests do, so energy scoring doesn't interfere.
  - **Set `avg_duration_minutes` explicitly on BOTH restaurants in every hunger test.** The factory randomizes it from `[45, 60, 75, 90, 120]`, so leaving either restaurant to the factory default makes the assertion non-deterministic (both could land on the same duration, or the "wrong" one could land closer to ideal). `price_level` no longer participates in any `QuizService` scoring, so you do not need to set it.
  - For `it does not apply a hunger bonus when avg_duration_minutes is null`: make it deterministic by pitting a `avg_duration_minutes => null` restaurant (score stays at base, no bonus) against one whose duration is far enough from ideal to earn a *penalty* under the same answer (e.g. under `hunger=feast`, ideal 120, a restaurant at 45 nets `-5`). Assert the null restaurant wins — proving no bonus is applied to null, while the non-null one is penalized. Keep both `vibe_tags => ['casual']` so energy/familiarity/weather stay neutral.
- Do not touch `QuickPickService` or `TournamentService` — both read `price_level` for unrelated modes and are out of scope.
- This task does not touch any Blade view or `QuizPageTest`/`quiz.spec.ts` — those are task 002's file set entirely. No shared files between the two tasks.

## Requirements (Test Descriptions)
- [x] `it scores a long avg_duration_minutes restaurant higher when hunger=feast`
- [x] `it scores a short avg_duration_minutes restaurant higher when hunger=quick_bite`
- [x] `it does not apply a hunger bonus when avg_duration_minutes is null`
- [x] `it falls back to the full_meal ideal duration for an unrecognized hunger value`

## Acceptance Criteria
- All requirements have passing tests
- No remaining references to `price_level` inside the hunger-scoring branch of `QuizService::scoreAll()`
- `vendor/bin/pint --dirty --format agent` clean
- `php artisan test --compact --filter=QuizServiceTest` passes

## Implementation Notes
- `QuizService::scoreAll()` hunger branch now keys off `avg_duration_minutes` (`quick_bite`=45, `full_meal`=75/default, `feast`=120) using `HUNGER_MATCH_BONUS - intdiv($gap * 2, 5)`, guarded on `avg_duration_minutes !== null`.
- `QuizAnswers::$hunger` default changed to `'full_meal'`; docblock updated to the new value set.
- `QuizServiceTest`: `neutralAnswers()` hunger fallback changed to `'full_meal'`; the two price-based hunger tests replaced with duration-based equivalents, plus two new tests (null-duration no-bonus, unrecognized-value fallback) per requirements.
- Full `QuizServiceTest` suite (34 tests) run 5x consecutively with no flakiness; other existing tests (energy/familiarity) rely on the random duration bonus spread `{7,13,19,25}` staying under `ENERGY_MATCH_BONUS` (30), as specified in the task — held true across repeated runs.
