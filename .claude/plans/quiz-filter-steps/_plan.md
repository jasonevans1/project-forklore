# Plan: Guided Quiz Filter Steps

## Created
2026-07-10

## Status
completed

## Objective
Add four hard-filter steps to the Guided Quiz wizard (dine-in/takeout, service level, cuisine, distance) that narrow the favorites pool via query constraints, and add skip-aware logic so the energy and familiarity scoring questions are bypassed for fast-food/fast-casual picks.

## Related Issues
none

## Discovery Notes
- The quiz wizard shell (`resources/views/pages/⚡quiz.blade.php`) was just rebuilt as a skip-aware Livewire SFC with a `steps()` registry (7 fixed slots: 5 real questions + 2 reserved placeholders always skipped). `nextRawStep`/`previousRawStep`/`effectiveStepNumber`/`effectiveStepTotal` all read that registry.
- `QuizService::buildPool()` already does one hard filter (distance, in PHP after fetching favorites) and soft scoring for everything else (`QuizService::scoreAll()`).
- `ServiceLevel`, `ServiceOption`, and `PrimaryCuisine` backed enums already exist on `Restaurant` (added in the prior restaurant-classification-fields feature) — this plan is what puts them to use.
- **Step order** (derived from the ask's own "skip energy/familiarity in step 4 and 7" clue — this is the only ordering under which both numbers line up): 1 dine-in/takeout, 2 service level, 3 cuisine, 4 energy, 5 hunger, 6 distance, 7 familiarity. The 2 reserved placeholder slots go away entirely — every slot is now a real question.
- **shouldSkip design**: introduce `App\Enums\QuizQuestion` (backed enum, one case per step field) with a `shouldSkip(QuizAnswers $answers): bool` method — matches the existing codebase convention of backed enums with instance methods (`ServiceLevel::label()`, etc.) rather than inventing a new Question-class hierarchy. Only `Energy` and `Familiarity` skip, when `$answers->serviceLevel === 'quick_easy'`.
- **Service level tiers** (clarified with user): 5 `ServiceLevel` enum cases, 4 friendly UI labels. "Quick and easy" groups `FastFood` + `FastCasual` under one synthetic tier answer (`quick_easy`), filtered via `whereIn`. The other three tiers map 1:1 (`casual_sit_down`→Casual, `nicer_night_out`→UpscaleCasual, `special_occasion`→FineDining).
- **Cuisine** (clarified with user): the new cuisine step hard-filters on `Restaurant::primary_cuisine`, replacing the old soft `cuisine_tags` scoring bonus entirely (that bonus becomes redundant once non-matches are excluded from the pool). "Surprise me" (`cuisine = null`) skips the filter. Grid shows 16 of the 18 `PrimaryCuisine` cases (excludes `AsianGeneral` and `Other`, which don't fit a clean 4×4 grid) — this specific selection is a low-stakes content default, adjust freely at implementation time.
- **Distance** (clarified with user): the new 4-bucket filter (`under_2_miles` / `2_to_5_miles` / `5_to_15_miles` / `anywhere`) replaces the existing 3-bucket `nearby`/`close`/`anywhere` scheme, including its `NEARBY_MILES`/`CLOSE_MILES` constants. This is a breaking change to `QuizAnswers::$distance` and its existing tests — they get rewritten, not kept alongside.
- **Dine-in/takeout**: filters `Restaurant::service_options` (JSON array of `ServiceOption` values) via `whereJsonContains`. `either` = no constraint.

## Scope

### In Scope
- `App\Enums\QuizQuestion` backed enum with `shouldSkip(QuizAnswers $answers): bool`
- `QuizAnswers` gains `dineInTakeout` and `serviceLevel` fields; `distance` bucket values are replaced
- `QuizService::buildPool()` applies 4 hard query/collection filters: dine-in/takeout, service level, cuisine (primary_cuisine), distance (new buckets)
- Removal of the old soft cuisine-scoring bonus from `QuizService::scoreAll()`
- Quiz wizard Livewire component (`⚡quiz.blade.php`): new step registry driven by `QuizQuestion`, two new public properties, updated `mount()`/`answer()`/`restart()`/`buildAnswers()`/`persistSession()`, removal of reserved-placeholder handling
- Four blade step partials: two new (`dineInTakeout`, `serviceLevel`), two rewritten (`cuisine` as 4×4 grid + Surprise me, `distance` as 4 buckets)
- Test coverage: each filter narrows the pool correctly, "Surprise me" skips the cuisine filter, skip logic bypasses energy/familiarity for fast-food/fast-casual picks, full happy-path and skip-path wizard flows

### Out of Scope
- Any change to the other 3 scoring dimensions' point values (energy/hunger/familiarity scoring math is unchanged, only their position in the flow and skip-eligibility change)
- Weather scoring logic (untouched)
- Tournament, Quick Pick, or Something Happening Tonight modes

## Success Criteria
- [ ] All four filter steps narrow the candidate pool via query constraints, verified by tests
- [ ] "Surprise me" on the cuisine step results in no cuisine constraint applied
- [ ] Fast-food/fast-casual service-level answers skip both the energy and familiarity questions; every other tier shows all 7 steps
- [ ] Full wizard flow (all steps → result) and skip-path flow both pass end-to-end
- [ ] All tests passing
- [ ] Code follows project standards (Pint clean)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | QuizQuestion enum + QuizAnswers new fields | - | completed |
| 002 | QuizService: dine-in/takeout + service level filters | 001 | completed |
| 003 | QuizService: cuisine + distance filters, remove old cuisine scoring | 002 | completed |
| 004 | Wizard front-end: component + all 4 step partials + QuizPageTest rewrite | 001 | completed |
| 005 | QuizService combined-filter integration + QuizServiceTest sweep | 003 | completed |
| 006 | End-to-end wizard flow (happy + skip path) + full-suite green | 004 | completed |

## Architecture Notes
- Tasks 002 and 003 both edit `QuizService::buildPool()` — kept sequential (003 depends on 002) to avoid two workers colliding on the same method.
- **Task 004 owns the entire wizard front-end as one atomic unit** — component rewrite, all four step partials (2 new + 2 rewritten), and the full `QuizPageTest` rewrite. This is deliberate: Livewire renders the component on every `->test()`/`->call()`, and `x-dynamic-component` throws if a step's partial view is missing, so the reordered component and the partials it points at cannot be split across tasks without a render failure. `QuizPageTest` is likewise one file entangled with both step order and partial content. The component mocks `QuizService::topMatch()`/`runnerUp()` (existing pattern), so 004 needs only 001, not the filter internals.
- Tasks 005 (service integration/sweep, off 003) and 006 (component e2e, off 004) are independent leaves and can run in parallel; neither touches the other's test file.
- `QuizQuestion` enum values equal the exact camelCase property/field names used elsewhere (`dineInTakeout`, `serviceLevel`, `cuisine`, `energy`, `hunger`, `distance`, `familiarity`) so `$this->{$field}` dynamic property access, `answer()` field keys, and `x-dynamic-component :component="'quiz.steps.' . $this->currentField()"` all keep working — and blade partial filenames must match these exactly.

## Risks & Mitigations
- **Missing-view render failure (was the biggest trap)**: reordering `steps()` to make step 1 = `dineInTakeout` breaks every `QuizPageTest` render until that partial exists, because `x-dynamic-component` throws on a missing view (the `@if ($this->currentField())` guard only covers null). Mitigated by having task 004 build the component and all four partials together.
- **Breaking existing tests**: the reserved-slot tests, 3-bucket distance tests, and soft cuisine-scoring tests no longer reflect the new design. `QuizPageTest` is far staler than a 3-test cleanup — nearly the whole file (step positions, copy, counts, dead `nearby`/`Italian` payloads) is invalidated by the reorder; task 004 rewrites/removes all of it to green. `QuizServiceTest` stale assertions are removed in task 003 with a final sweep in task 005.
- **SQLite JSON gotcha**: `service_options` is a JSON array column and `whereJsonContains` is unreliable on SQLite (the test/default driver). Task 002 filters `service_options` in PHP (like the existing distance filter) and keeps only the scalar `service_level` as a query `where`/`whereIn`.
- **Cuisine value casing**: the new hard filter compares the `PrimaryCuisine` backed value (lowercase `'italian'`), not the old title-case tag. The cuisine partial emits `$cuisine->value` and all cuisine tests use enum values — mismatched casing would silently exclude everything.
- **Session shape drift**: `persistSession()`/`mount()` must add the 2 new fields or resuming a wizard mid-flight will silently drop them — covered by task 004's requirements.
