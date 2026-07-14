# Task 004: Wizard Front-End — Component + All Step Partials + QuizPageTest Rewrite

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Rewrite the entire Guided Quiz wizard front-end as one atomic unit: the Livewire component's step registry + skip
logic, **all four** step partials it renders (2 brand-new + 2 rewritten), and the full `QuizPageTest` rewrite to
match the new 7-step order. This is deliberately a single task because Livewire renders the component on every
`->test()`/`->call()`, so the component and the partials it points at cannot be split across tasks without a
missing-view render failure, and `QuizPageTest` is one file entangled with both the step order and the partial
content. Everything here must land green together.

## Context
- Related files:
  - `resources/views/pages/⚡quiz.blade.php` (the `new class extends Component` block + the progress/header markup)
  - `resources/views/components/quiz/steps/*.blade.php` (energy/hunger/familiarity untouched; cuisine/distance rewritten; dineInTakeout/serviceLevel new)
  - `tests/Feature/QuizPageTest.php`
- Patterns to follow: this component's own existing structure (keep the `Computed` / `Actions` / `Step registry` /
  `Session persistence` / `Private helpers` section comments) and the existing partial markup in
  `hunger.blade.php`/`familiarity.blade.php` (`rounded-2xl border border-zinc-200 p-5 text-left dark:border-zinc-700`,
  `wire:click="answer('field', 'value')"`).
- Final step order (from `_plan.md` Discovery Notes): 1 `dineInTakeout`, 2 `serviceLevel`, 3 `cuisine`, 4 `energy`,
  5 `hunger`, 6 `distance`, 7 `familiarity`. No reserved slots — every slot is a real question.

### Component changes
- Replace `private function steps(): array` (currently `[int => ['field' => ?string, 'skip' => bool]]` with 2
  always-skipped reserved slots) with `[int => QuizQuestion]` for all 7 slots in the order above.
- `currentField()`, `nextRawStep()`, `previousRawStep()`, `effectiveStepNumber()`, `effectiveStepTotal()` read
  `$slot['skip']`/`$slot['field']` today — update them to `$slot->value` and
  `$slot->shouldSkip($this->buildAnswers())`. `buildAnswers()` always returns a complete `QuizAnswers` (every field
  has a default), so `shouldSkip` is safe to call mid-wizard even when properties are still null.
- Add two public properties: `public ?string $dineInTakeout = null;` and `public ?string $serviceLevel = null;`
  (nullable during the wizard; `buildAnswers()` applies the real defaults).
- `mount()`: restore `dineInTakeout`/`serviceLevel` from the session snapshot alongside the existing fields.
- `answer()`: logic unchanged (generic `$this->{$field} = $value`) — confirm it still works with the new fields.
- `restart()`: null out `dineInTakeout`/`serviceLevel` alongside the existing fields.
- `buildAnswers()`: pass `dineInTakeout: $this->dineInTakeout ?? 'either'` and
  `serviceLevel: $this->serviceLevel ?? 'casual_sit_down'` into the `QuizAnswers` constructor.
- `persistSession()`: include the two new fields in the session array.

### Step partials
- **New** `dineInTakeout.blade.php` — 3 buttons: Dine in (`'dine_in'`), Takeout (`'takeout'`), Either is fine (`'either'`).
- **New** `serviceLevel.blade.php` — 4 buttons with friendly labels/values: "Quick and easy" (`'quick_easy'`),
  "Casual sit-down" (`'casual_sit_down'`), "Nicer night out" (`'nicer_night_out'`), "Special occasion" (`'special_occasion'`).
- **Rewrite** `cuisine.blade.php`: keep the prominent "Surprise me" button (`answer('cuisine', null)`) at the top,
  then a `grid grid-cols-4 gap-3` of 16 `PrimaryCuisine` cases using **`$cuisine->value`** for the click payload
  (lowercase backed value — NOT the title-case label) and `$cuisine->label()` for display. Suggested 16 (excludes
  `AsianGeneral` and `Other` for a clean 4×4; adjust freely): American, Italian, Mexican, Chinese, Japanese, Thai,
  Vietnamese, Korean, Indian, Mediterranean, Bbq, Seafood, Pizza, Breakfast, Cafe, BarFood.
- **Rewrite** `distance.blade.php`: 4 buttons — Under 2 mi (`'under_2_miles'`), 2–5 mi (`'2_to_5_miles'`),
  5–15 mi (`'5_to_15_miles'`), Anywhere (`'anywhere'`).
- Filenames must match the `QuizQuestion` case values exactly (camelCase: `dineInTakeout.blade.php`,
  `serviceLevel.blade.php`), because `x-dynamic-component :component="'quiz.steps.' . $this->currentField()"`
  resolves them by that string. All four partials must exist before the reordered component renders, or the very
  first `->test('pages::quiz')` throws "Unable to locate a class or view for component".

### QuizPageTest rewrite (own the whole file — it's much staler than a 3-test cleanup)
The new step order invalidates most of the file. At minimum:
- **Remove**: `it skips reserved placeholder slots when navigating back` (no reserved slots),
  `it advances from raw step 5 to resolving the result instead of a raw step 6 or 7` (resolution now happens after
  step 7), `it reports effective step total of 5 given the current registry` (now 7 by default).
- **Update every wizard-walking test** (`->call('answer', ...)` chains) to prepend
  `->call('answer','dineInTakeout','either')->call('answer','serviceLevel','casual_sit_down')` and then answer in
  the new order (cuisine → energy → hunger → distance → familiarity) to reach the result.
- **Update copy/step-position assertions**: `shows the energy level question on step 1` (energy is now step 4),
  `shows a progress indicator` (`5` → `7`), `shows the hunger/familiarity/distance/cuisine question on step N`,
  `advances to step N after answering ...`, `renders the cuisine question ... on the last effective step` (cuisine
  is now step 3, familiarity is last), `displays step and total counts ...` (`Step 5 of 5` → new counts),
  `still shows all 5 question option buttons ...` (drop the dead `answer('distance','nearby')` and
  `answer('cuisine','Italian')` — distance buckets and cuisine values changed).
- The suite must be **green** when this task completes — no red left for a later task.

## Requirements (Test Descriptions)
- [x] `it starts on step 1`
- [x] `it advances through all 7 steps in order when service level is casual_sit_down`
- [x] `it skips step 4 (energy) and lands on step 5 (hunger) when service level is quick_easy`
- [x] `it skips step 7 (familiarity) and resolves the result when service level is quick_easy and all other steps are answered`
- [x] `it reports effective step total of 7 when service level is casual_sit_down`
- [x] `it reports effective step total of 5 when service level is quick_easy`
- [x] `it restores dineInTakeout and serviceLevel from session when the component remounts`
- [x] `it clears dineInTakeout and serviceLevel when start-over is triggered`
- [x] `it returns to the previous non-skipped step when back is tapped past a skipped question`
- [x] `it renders the dine-in/takeout question with dine in, takeout, and either is fine options`
- [x] `it renders the service level question with all 4 friendly labels`
- [x] `it renders 16 cuisine options plus a prominent Surprise me button in a 4x4 grid`
- [x] `it renders the distance question with 4 buckets: under 2mi, 2-5mi, 5-15mi, anywhere`
- [x] `it advances past the cuisine step and applies no cuisine constraint when Surprise me is tapped`

## Acceptance Criteria
- All requirements have passing tests in `tests/Feature/QuizPageTest.php`
- The whole `QuizPageTest.php` file is green (all pre-existing tests rewritten/removed to match the new flow — no stale reds)
- Views exist and are checked with `view()->exists(...)` per the existing test pattern for the new/rewritten partials
- `vendor/bin/pint --dirty --format agent` run and clean
- No decrease in test coverage

## Implementation Notes
- Rewrote `resources/views/pages/⚡quiz.blade.php`: `steps()` now returns `[int => QuizQuestion]` for the 7-slot
  order (dineInTakeout, serviceLevel, cuisine, energy, hunger, distance, familiarity). `currentField()`,
  `effectiveStepNumber()`, `effectiveStepTotal()`, `nextRawStep()`, `previousRawStep()` all call
  `$slot->shouldSkip($this->buildAnswers())` instead of reading a `skip` array key. Added `$dineInTakeout` /
  `$serviceLevel` public properties, wired through `mount()`, `restart()`, `persistSession()`, and `buildAnswers()`.
- Added `resources/views/components/quiz/steps/dineInTakeout.blade.php` (3 options) and `serviceLevel.blade.php`
  (4 options), matching the existing `hunger.blade.php`/`familiarity.blade.php` button markup pattern.
- Rewrote `cuisine.blade.php`: prominent "Surprise me" button (`answer('cuisine', null)`) followed by a
  `grid grid-cols-4 gap-3` of 16 `PrimaryCuisine` cases (excludes `AsianGeneral`/`Other`), using `$cuisine->value`
  for the click payload and `$cuisine->label()` for display.
- Rewrote `distance.blade.php` with the new 4-bucket values (`under_2_miles`, `2_to_5_miles`, `5_to_15_miles`,
  `anywhere`) replacing the old 3-bucket `nearby`/`close`/`anywhere` set.
- Fully rewrote `tests/Feature/QuizPageTest.php` for the new 7-step order; added two file-local helper functions
  (`answerIntakeSteps()`, `completeAllSteps()`) following the existing repo convention of plain test-file helper
  functions (see `VisitLoggingTest.php`, `QuickPickServiceTest.php`, etc.). Removed the 3 tests called out in the
  task (reserved-slot back nav, raw-step-5-resolves, effective-total-of-5-by-default) since they no longer apply
  with no reserved slots.
- Updated the two other test files that drive the quiz component through `answer()` calls
  (`tests/Feature/VisitLoggingTest.php`, `tests/Feature/TurnIndicatorTest.php`) to prepend
  `dineInTakeout`/`serviceLevel` answers and reorder to the new step sequence — otherwise their old 5-call chains
  under-walked the now-7-step wizard and `going()` threw `ModelNotFoundException` on a still-null `restaurantId`.
  These weren't explicitly in scope per the task file but were a direct regression from the component rewrite.
- `QuizService::buildPool()` still has the old 3-bucket distance filter and old cuisine matching (out of scope,
  handled by a parallel task) — the component passes through whatever bucket/cuisine values the new partials
  produce regardless.
- Full suite: `ddev exec php artisan test --compact --parallel` → 629 passed. `ddev exec vendor/bin/pint --dirty --format agent` → clean.
