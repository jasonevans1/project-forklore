# Task 001: Step Registry, Effective-Count Helpers, and Session Persistence

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Give the quiz component a fixed 7-slot step registry (5 real question slots + 2 reserved always-skipped placeholders), computed helpers that translate a raw slot number into the effective "Step X of Y" count, and session-backed persistence so wizard progress survives a page reload. This task only changes the component class (`⚡quiz.blade.php`'s PHP block) and its state handling — it does not change rendered question markup or add the back/start-over UI (those are later tasks).

## Context
- Related files: `resources/views/pages/⚡quiz.blade.php` (Livewire SFC — PHP class at the top of the file), `tests/Feature/QuizPageTest.php`.
- The component currently hardcodes `step` as an int 1–5 and advances via `answer()`, comparing `$this->step < 5`. Replace that hardcoded bound with the registry-driven logic below, without changing the public behavior existing tests rely on (step still starts at 1, still reaches `state = 'result'` after the 5th real answer).
- Step registry (private method, not a new class — see plan's Architecture Notes on avoiding speculative abstraction):
  ```php
  /** @return array<int, array{field: string|null, skip: bool}> */
  private function steps(): array
  {
      return [
          1 => ['field' => 'energy', 'skip' => false],
          2 => ['field' => 'hunger', 'skip' => false],
          3 => ['field' => 'familiarity', 'skip' => false],
          4 => ['field' => 'distance', 'skip' => false],
          5 => ['field' => 'cuisine', 'skip' => false],
          6 => ['field' => null, 'skip' => true], // ponytail: reserved slot, real question TBD
          7 => ['field' => null, 'skip' => true], // ponytail: reserved slot, real question TBD
      ];
  }
  ```
- Effective-count helpers needed. IMPORTANT visibility rule: any helper the **Blade view** calls must be `public` — a Livewire/Blade template cannot call `private`/`protected` methods on `$this` (PHP scope enforcement; the compiled template is a separate scope). This matches the existing codebase convention: view-called helpers like `needsDayOfWeek()`/`needsSpecificDate()` in `⚡create.blade.php` are `public`, while view-only-internal helpers like `resolveTagline()`/`haversineMiles()` in `⚡pick.blade.php` are `private`. Split accordingly:
  - `effectiveStepNumber(int $rawStep): int` — **public** (Task 004's progress indicator calls it from the view). Count of non-skipped slots with key `<= $rawStep`.
  - `effectiveStepTotal(): int` — **public** (Task 004's progress indicator calls it from the view). Count of non-skipped slots in `steps()`.
  - `currentField(): ?string` — **public** helper returning `$this->steps()[$this->step]['field']` (Task 004 renders the right partial from this off the view; add it here so 004 doesn't have to reach into `steps()` from the template). If you instead make `steps()` itself public, that also satisfies the view, but a narrow `currentField()` accessor is preferred.
  - `nextRawStep(int $from): ?int` — may stay `private` (only `answer()` calls it). Next key `> $from` with `skip === false`, or `null` if none remain (signals "resolve now").
  - `previousRawStep(int $from): ?int` — may stay `private` (only Task 002's `back()` action calls it; Task 002 exposes a separate public `canGoBack()` for its view guard). Previous key `< $from` with `skip === false`, or `null` if `$from` is already the first non-skipped slot.
  - `steps()` — may stay `private` if `currentField()` and the effective-count helpers cover every view read; keep the registry itself out of the template.
- Update `answer()` to advance via `nextRawStep($this->step)` instead of the hardcoded `< 5` / increment, calling `resolve()` when `nextRawStep()` returns `null`.
- Session: pick one session key, e.g. `quiz.wizard`, storing an array snapshot of `step`, `state`, `restaurantId`, and the 5 answer fields. Add a `mount(): void` method that hydrates these public properties from `session('quiz.wizard')` when present. Add a private `persistSession(): void` called at the end of `answer()` (and by later tasks' actions), and a private `clearSession(): void`.
  - **This task owns wiring `clearSession()` into both `restart()` and `going()`.** Task 003 only adds the header UI entry point for `restart()`; it does NOT re-add the `clearSession()` call (that would double-clear). Do the `restart()`/`going()` clearing here.
  - Because `answer()` calls `resolve()` (which sets `state = 'result'` and `restaurantId`) before `persistSession()` runs at the end of `answer()`, the persisted snapshot on the final step captures the `result` state — so a reload on the result card restores the result rather than bouncing back to step 5. That is intended. `reject()` is NOT in scope to persist (a reload after "Not this one" may show the original pick, which is an acceptable edge case).
- Patterns to follow: existing PHP standards in this file (constructor promotion N/A here, explicit return types, PHPDoc array shapes per `code-standards.md`).

## Requirements (Test Descriptions)
- [x] `it reports effective step number 1 for raw step 1`
- [x] `it reports effective step total of 5 given the current registry`
- [x] `it advances from raw step 5 to resolving the result instead of a raw step 6 or 7`
- [x] `it restores previously answered fields from session when the component remounts`
- [x] `it restores the current step from session when the component remounts`
- [x] `it persists each answer to session immediately after answering`
- [x] `it does not restore any state when no session data exists yet`
- [x] `it clears the session when the quiz is completed via going`

## Acceptance Criteria
- All requirements have passing tests.
- All pre-existing tests in `tests/Feature/QuizPageTest.php` still pass unmodified (behavior-preserving refactor of the step-advancement mechanism).
- Code follows code standards; `vendor/bin/pint --dirty --format agent` run after changes.

## Implementation Notes
- Added the 7-slot `steps()` registry (private), `currentField()`, `effectiveStepNumber()`, `effectiveStepTotal()` (all public, per view-visibility rule), and `nextRawStep()`/`previousRawStep()` (private) using `collect()` over the registry array.
- `answer()` now advances via `nextRawStep($this->step)`, calling `resolve()` when it returns `null`; ends with `persistSession()`.
- Session key `quiz.wizard` (constant `SESSION_KEY`). `mount()` hydrates public properties when a snapshot exists. `persistSession()`/`clearSession()` are private; `clearSession()` wired into both `restart()` and `going()`.
- `previousRawStep()` is unused by this task (reserved for Task 002's `back()`) — Pint/PHP doesn't flag unused private methods, left as specified.
- All 8 new tests pass; all 24 pre-existing `QuizPageTest` tests pass unmodified; full suite (595 tests) and `pint --dirty` both green.
