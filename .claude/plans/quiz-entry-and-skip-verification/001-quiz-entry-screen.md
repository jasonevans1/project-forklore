# Task 001: Add Intro/Entry Screen to the Guided Quiz

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add a "Start quiz" entry screen ahead of the wizard so `/quiz` no longer drops straight into step 1. The screen shows a single primary button in the thumb zone and copy that sets expectations ("5-7 quick questions"). Resuming a session already in progress (mid-wizard, on a result, or on the empty state) must skip the intro automatically — only a genuinely fresh visit shows it.

## Context
- Single file to edit: `resources/views/pages/⚡quiz.blade.php` (Livewire single-file component — PHP class + Blade view in one file)
- Tests to edit: `tests/Feature/QuizPageTest.php`
- The component's `state` property already has 3 values: `'questions' | 'result' | 'empty'`. Do **not** add a 4th `state` value for the intro — many existing tests assert `assertSet('state', 'questions')` on flows that must keep working unchanged. Instead add a new `public bool $introDismissed = false;` property that gates only the intro sub-view *within* the existing `@if ($state === 'questions')` block.
- `mount()` (lines ~64-84) restores from `session('quiz.wizard')` when present. When no snapshot exists, all properties keep their class defaults — `introDismissed` stays `false`, so the intro shows. When a snapshot exists, restore `introDismissed` from it (default to `true` if the key is missing, since any snapshot means the wizard was already reached).
- `persistSession()` (lines ~393-409) must include `introDismissed` in the saved array.
- Add a `startQuiz(): void` action that sets `$this->introDismissed = true;` and calls `$this->persistSession();`. No other side effects — step/state are already correct at their defaults (`step = 1`, `state = 'questions'`).
- `restart()` (lines ~279-296) rebuilds the wizard back to step 1 mid-app (from the header or the empty state) — it must **not** show the intro again. Explicitly set `$this->introDismissed = true;` inside `restart()`.
- Blade structure today: `@if ($state === 'questions')` wraps the header (turn indicator + start-over), progress bar, current step partial, and back button, all in one block (lines ~497-543). Split this into `@if (! $introDismissed) ... @else ... @endif` — the intro branch replaces the whole existing contents, the else branch is the existing markup unchanged.
- Intro screen content: a heading, one line of body copy setting expectations of "5-7 quick questions", and a primary full-width button (`class="w-full py-4 text-base font-semibold"`, matching the `Going` button's thumb-zone styling elsewhere in this file) wired to `wire:click="startQuiz"`, labeled "Start quiz". Wrap the button in a bottom-anchored `class="px-6 pb-12 pt-6"` container, matching the existing Back-button wrapper pattern in this file.
- Gating the wizard behind `introDismissed` breaks any existing test that mounts the component fresh (or navigates fresh) and immediately asserts wizard content without calling `startQuiz` (or an action that implies it) first. After implementing, fix these specific existing tests in `tests/Feature/QuizPageTest.php` by inserting a `->call('startQuiz')` immediately after `->test('pages::quiz')` and before any content assertion:
  - `it('shows a progress indicator', ...)`
  - `it('shows the service level question on step 2', ...)`
  - `it('shows a start-over action in the header during the questions state', ...)`
  - `it('renders a back button on steps after the first', ...)`
  - `it('renders the dine-in/takeout question with dine in, takeout, and either is fine options', ...)`
  - `it('renders the service level question with all 4 friendly labels', ...)`
  - `it('displays step and total counts derived from the effective step total, not a hardcoded number', ...)`
  - `it('shows Step 1 of 7 on the first step for a casual-bound flow', ...)`
  - `it('still shows all 7 question option buttons with unchanged copy after extraction', ...)` — here the `->assertSeeHtml(...)` is chained directly onto `Livewire::actingAs($this->user)->test('pages::quiz')` before being passed into `answerIntakeSteps()`; insert `->call('startQuiz')` in that same chain, before the `assertSeeHtml`.
  - Also update the shared `answerIntakeSteps()` helper (used by nearly every other test) to call `->call('startQuiz')` as its first action, before the two existing `->call('answer', ...)` calls. This transitively fixes every test that goes through `answerIntakeSteps()`/`completeAllSteps()`.
- Do not touch `tests/e2e/quiz.spec.ts` in this task — that's Task 003's scope.

## Requirements (Test Descriptions)
- [x] `it shows the intro screen with a Start quiz button on a fresh visit to the quiz page`
- [x] `it does not show the wizard question content before Start quiz is tapped`
- [x] `it shows copy mentioning 5 to 7 quick questions on the intro screen`
- [x] `it transitions to step 1 of the wizard when Start quiz is tapped`
- [x] `it skips the intro screen and resumes directly into the wizard when a session snapshot already exists`
- [x] `it defaults introDismissed to true and skips the intro when a session snapshot exists without the introDismissed key` — manually seed `session('quiz.wizard', [...])` with all the existing keys but NO `introDismissed` key (simulating a session persisted before this feature shipped), mount, and assert the wizard renders (not the intro). This locks in the `?? true` fallback in `mount()` so an in-flight session during deploy is not bounced back to the intro.
- [x] `it persists introDismissed to session immediately after Start quiz is tapped`

## Acceptance Criteria
- All requirements above have passing tests
- The full existing `tests/Feature/QuizPageTest.php` suite passes (`php artisan test --compact tests/Feature/QuizPageTest.php`) — the ~9 tests and the `answerIntakeSteps()` helper listed above are updated, not deleted
- `vendor/bin/pint --dirty --format agent` run and clean
- No decrease in test coverage

## Implementation Notes
Implemented exactly as spec'd in the Context section: added `public bool $introDismissed = false;`, restored it in `mount()` with `?? true` fallback, added `startQuiz()` action, set `introDismissed = true` in `restart()`, included the key in `persistSession()`, and split the `@if ($state === 'questions')` block into `@if (! $introDismissed) ... @else ... @endif` with the intro screen (heading, "5-7 quick questions" copy, thumb-zone `Start quiz` button) as the new branch.

Updated `answerIntakeSteps()` and the 9 listed tests in `tests/Feature/QuizPageTest.php` to call `->call('startQuiz')` before asserting wizard content, per the task's exact list.

One regression outside the task's listed scope: `tests/Feature/TurnIndicatorTest.php` had 2 tests (`it shows a turn indicator on the quiz page when a partner exists`, `it shows "Your turn" on quiz page when the partner last picked`) that mounted the quiz page fresh and asserted on the turn-indicator header, which is now behind the intro gate. Fixed by adding `->call('startQuiz')` to both, consistent with the same pattern used elsewhere — required to keep the full test suite green per the acceptance criteria.

Full suite: `php artisan test --compact --parallel` → 681 passed. `vendor/bin/pint --dirty --format agent` → passed, no changes needed.
