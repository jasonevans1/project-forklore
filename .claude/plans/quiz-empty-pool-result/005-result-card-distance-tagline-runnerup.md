# Task 005: Quiz Result Card — Distance, Tagline, Show-Runner-Up Peek

**Status**: completed
**Depends on**: [003, 004]
**Retry count**: 0

## Description
Bring the quiz result card to parity with Quick Pick's by adding a distance label and weather-aware tagline (via the trait from Task 004), and add a non-committal "Show runner-up" link that reveals the runner-up's name without changing the displayed pick.

## Context
- Related files: `resources/views/pages/⚡quiz.blade.php`, `tests/Feature/QuizPageTest.php`
- `use App\Concerns\ComputesRestaurantPresentation;` on the quiz page class; call its `resolveTagline()`/`resolveDistanceLabel()` the same way `⚡pick.blade.php` does, populating `public string $tagline = ''` and `public ?string $distanceLabel = null` whenever a restaurant is resolved into `$state = 'result'` — that's on the initial `resolve()`, on a successful `loosenFilter()` (Task 003), and on `reject()`'s runner-up swap.
- "Show runner-up" is a **peek, not a swap**: add `public ?string $peekedRunnerUpName = null` and `public function peekRunnerUp(): void` that calls `QuizService::runnerUp()` with the current answers/weather/current restaurant and sets `$peekedRunnerUpName` to its name, or to a "no other match" indicator when `runnerUp()` returns null. It must **not** change `$restaurantId`, `$state`, `$tagline`, or `$distanceLabel`.
- **Pool consistency with loosened results (from Task 003):** `peekRunnerUp()` AND the existing `reject()` must build their answers via the `answersForCurrentResult()` helper introduced in Task 003 (which applies `$activeLoosenedField` when set), NOT via `buildAnswers()` directly. Without this, peeking/rejecting a loosened result scores against the original strict (empty) pool and wrongly reports "no other match" / jumps to the empty state. Update `reject()` to call `answersForCurrentResult()`; the pre-existing `reject()` tests (which never loosen) must still pass because `answersForCurrentResult()` equals `buildAnswers()` when no filter is active.
- Reset `$peekedRunnerUpName = null` whenever the displayed restaurant actually changes (`reject()`'s successful swap, `loosenFilter()`'s successful match, `restart()`), so a stale peeked name never lingers on a different card.
- Blade changes: add the distance + tagline markup to the `result` block (mirror `⚡pick.blade.php`'s meta-row and italic tagline text), and add a "Show runner-up" link/button wired to `wire:click="peekRunnerUp"` that displays `$peekedRunnerUpName` inline once set. Keep "Going" and "Not this one" exactly as they are today, acting on the currently displayed (non-peeked) restaurant.

## Requirements (Test Descriptions)
- [x] `it shows a distance label on the quiz result card when coordinates are available`
- [x] `it shows no distance label on the quiz result card when coordinates are unavailable`
- [x] `it shows a weather-aware tagline on the quiz result card`
- [x] `it reveals the runner-up name when Show runner-up is tapped`
- [x] `it does not change the displayed restaurant when Show runner-up is tapped`
- [x] `it shows a no-other-match indicator when Show runner-up is tapped and no runner-up exists`
- [x] `it clears the peeked runner-up name after Not this one swaps to a new restaurant`
- [x] `it populates the tagline and distance label after Not this one swaps to the runner-up`

## Acceptance Criteria
- All requirements have passing tests in `tests/Feature/QuizPageTest.php`.
- Every pre-existing test in `tests/Feature/QuizPageTest.php` (including the "Not this one" runner-up-swap tests) still passes unmodified.
- Code follows code standards; `vendor/bin/pint --dirty --format agent` run after edits.

## Implementation Notes
- Confirmed `ComputesRestaurantPresentation`'s real signatures are `resolveTagline(Restaurant $restaurant): string` and `resolveDistanceLabel(Restaurant $restaurant): ?string` (no `?WeatherData` parameter — the trait pulls `$this->lat`/`$this->lng` itself), so the quiz page class calls them exactly like `⚡pick.blade.php` does.
- Added `public string $tagline`, `public ?string $distanceLabel`, `public ?string $peekedRunnerUpName` to the quiz page class; `use ComputesRestaurantPresentation;` added to the component class.
- `resolve()`, `loosenFilter()`'s successful-match branch, and `reject()`'s successful-swap branch now populate `$tagline`/`$distanceLabel` for the newly-displayed restaurant.
- `loosenFilter()`'s successful match, `reject()`'s successful swap, and `restart()` all reset `$peekedRunnerUpName = null`.
- Added `peekRunnerUp()`: calls `QuizService::runnerUp()` with `answersForCurrentResult()` (Task 003's neutralize-aware helper), current weather, and the currently displayed restaurant; sets `$peekedRunnerUpName` to the runner-up's name or `__('No other match found')` when null. Does not touch `$restaurantId`/`$state`/`$tagline`/`$distanceLabel`.
- Blade: added price/distance meta row + italic tagline block mirroring `⚡pick.blade.php`, and a ghost "Show runner-up" button (`wire:click="peekRunnerUp"`) with an inline "Runner-up: {name}" text once `$peekedRunnerUpName` is set. "Going" and "Not this one" unchanged.
- All 8 new tests + all 66 pre-existing `QuizPageTest` tests pass (74 total); full suite (674 tests) passes; `vendor/bin/pint --dirty --format agent` clean.
