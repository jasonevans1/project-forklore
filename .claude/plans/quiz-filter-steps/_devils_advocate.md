# Devil's Advocate Review: quiz-filter-steps

## Critical (Must fix before building)

### C1 — Tasks 004/005 have a circular render dependency; task 004 cannot leave a green suite
This is the big one, and it hits all four of the reviewer's focus areas at once.

- The wizard blade renders the current step with
  `<x-dynamic-component :component="'quiz.steps.' . $this->currentField()" />`. The `@if ($this->currentField())`
  guard only protects against a *null* field — it does **not** protect against a *missing view*. An
  `x-dynamic-component` pointing at a non-existent anonymous component throws
  `InvalidArgumentException: Unable to locate a class or view for component [quiz.steps.dineInTakeout]`.
- Livewire's `->test('pages::quiz')` and every `->call(...)` perform a full server-side render. So the moment
  task 004 reorders `steps()` to make step 1 = `dineInTakeout`, the very first render in *every* `QuizPageTest`
  case throws, because the `dineInTakeout`/`serviceLevel` partials don't exist until task 005.
- Task 004 explicitly says "a step whose view is missing is fine to leave temporarily broken/stubbed ... assert on
  component state via `->call('answer', ...)`/`->assertSet(...)`, not on rendered content." That is **false** for
  Livewire — state assertions still trigger a render. Task 004's own tests cannot pass without the partials.
- Conversely, task 005's partial tests (e.g. "renders the dine-in/takeout question", "renders 16 cuisine options")
  walk the wizard, which requires task 004's new step order. So 005 needs 004 and 004 needs 005 — a real cycle.
- There is also a rework/churn problem even if the cycle were broken: `QuizPageTest` is a single file whose tests
  are entangled with both the component's step order (owned by 004) and the cuisine/distance partial *content*
  (owned by 005). Splitting ownership of that file across two tasks forces 004 to fix cuisine/distance-content
  tests against the *old* content, then 005 to re-fix them against the *new* content.

**Fix applied:** merge the entire wizard front-end into one task. Task 004 now owns the component rewrite,
**all four** step partials (2 new + 2 rewritten), and the full `QuizPageTest` rewrite — so it renders and lands
green atomically. Task 005 is repurposed to the QuizService combined-filter integration + `QuizServiceTest`
final sweep. Task 006 becomes the end-to-end wizard capstone depending on 004.

### C2 — The existing QuizPageTest suite is far more stale than the plan admits
The plan (task 006) names only 3 tests to remove and ~2 to update. In reality the new step order
(`dineInTakeout`, `serviceLevel`, `cuisine`, `energy`, `hunger`, `distance`, `familiarity`) invalidates roughly
the whole file: `shows the energy level question on step 1`, `shows a progress indicator` (asserts `5`),
`shows the hunger/familiarity/distance/cuisine question on step N`, `advances to step N after answering ...`,
`renders the cuisine question ... on the last effective step`, `reports effective step total of 5`,
`advances from raw step 5 to resolving`, `skips reserved placeholder slots`, `displays step and total counts`
(asserts `Step 5 of 5`), and `still shows all 5 question option buttons` (asserts `answer('distance','nearby')`
and `answer('cuisine','Italian')` — both dead values). A worker who thinks only 3 tests are stale will leave a
red suite.

**Fix applied:** task 004 now explicitly owns rewriting/removing every stale `QuizPageTest` case, with the full
new step→partial map and new copy strings called out.

## Important (Should fix before building)

### I1 — `whereJsonContains` on SQLite is a gotcha; prefer a PHP-side filter for `service_options`
The app runs on SQLite (default + test connection) and there is **no** existing `whereJsonContains` usage in the
codebase to prove it works here. `service_options` is an `array`-cast JSON text column. `whereJsonContains` on
SQLite depends on the JSON1 extension and Laravel's SQLite grammar; it's a well-known source of
"does not support JSON contains" surprises. The existing `buildPool()` already filters distance in PHP after
`->get()`.

**Fix applied:** task 002 now filters `service_options` in PHP (`in_array($opt, $r->service_options ?? [], true)`),
consistent with the existing distance filter, and keeps `service_level` as a query-level `where`/`whereIn`
(scalar column, no JSON risk).

### I2 — Cuisine value casing must be the PrimaryCuisine *backed value*, end to end
Old flow passed title-case tags (`'Italian'`) and scored with `strtolower`. The new hard filter is
`where('primary_cuisine', $answers->cuisine)` against a `PrimaryCuisine`-cast column storing lowercase values
(`'italian'`). The new cuisine partial must emit `$cuisine->value` (already specified in task 005/now-004), and
every cuisine test must use the enum value, not the label. Called out explicitly so a worker doesn't reintroduce
`'Italian'`.

### I3 — QuizServiceTest cleanup is split; make the sweep explicit and unavoidable
`QuizServiceTest` currently has `it scores a restaurant with matching cuisine higher ...` (soft cuisine bonus),
`it excludes restaurants beyond the max distance when distance=nearby`, and `it includes all restaurants when
distance=anywhere` (positionally fine, but `nearby` is dead). Task 003 removes/rewrites the first two; the new
task 005 does the final sweep so no stale `nearby`/`close`/`CUISINE_MATCH_BONUS` assertions survive.

### I4 — Distance filter silently no-ops without coordinates; tests must supply lat/lng
`buildPool()` only applies the distance filter when `lat`/`lng` are non-null; otherwise every restaurant passes.
The new-bucket tests must construct `QuizAnswers` with `lat`/`lng` set (as the existing distance tests do) or they
will pass for the wrong reason. Noted in task 003/005.

## Minor (Nice to address)

- **M1 — `buildAnswers()` is now called per-slot inside `effectiveStepTotal()`/`effectiveStepNumber()`/
  `nextRawStep()`/`previousRawStep()` closures.** Each call rebuilds a `QuizAnswers`. It's cheap object
  construction (7 slots × a couple of methods per render), so it's fine — but building the answers object once and
  passing it into `shouldSkip()` reads cleaner. Optional.
- **M2 — Stale scoring answers survive a skip.** If a user answers energy under `casual_sit_down`, taps Back to
  step 2, and switches to `quick_easy`, the energy question is now skipped in the UI but the previously-set
  `energy` value still feeds `scoreAll()`. Scoring math is explicitly out of scope, so this is acceptable; worth a
  one-line note in the component.
- **M3 — Cuisine grid content (16 of 18 cases) is a low-stakes default** already flagged in the plan; no action.

## Questions for the Team

- **Q1 — Focus area 1 (shouldSkip satisfiability): confirmed satisfiable.** `buildAnswers()` coerces every field
  to a non-null default (`serviceLevel ?? 'casual_sit_down'`, etc.), so a complete `QuizAnswers` is always
  constructible at every call site, even mid-wizard with null properties. The only behavioral consequence: before
  the user answers step 2, `serviceLevel` defaults to `casual_sit_down`, so `shouldSkip` returns false and the
  progress bar shows "of 7" on step 1. That's the intended casual-bound default. No fix needed — but the task 006
  requirement "it shows Step 1 of 5 on the first step once service level quick_easy has been answered" is
  self-contradictory (after answering step 2 you're on step 3, not the first step). Reworded to "Step 3 of 5 after
  answering serviceLevel quick_easy" — confirm that's the intended assertion.
- **Q2 — Focus area 2 (002/003 both edit buildPool): sufficient as sequential.** 003 depends on 002, so they never
  run concurrently and there is no merge collision on the shared method. No change needed.
- **Q3 — Focus area 3 (006 deps): the original "003, 005" was not wrong transitively, but 006 is a pure
  component-level e2e that mocks `QuizService::topMatch` (existing pattern), so after the restructure it only needs
  the front-end (004). The real "all four filters combined" verification is a QuizService test and now lives in
  task 005. Confirm you're happy with that placement rather than a full render-through-service e2e.
