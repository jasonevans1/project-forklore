# Task 012: Tournament Result → Result Ticket

**Status**: complete
**Depends on**: 003
**Retry count**: 0

## Description
Replace Tournament's champion result-state markup with `<x-restaurant-result-ticket>`, keeping the CTA buttons and the bracket/matchup/empty states untouched.

## Context
- Related files: `resources/views/pages/⚡tournament.blade.php`, existing
  `tests/Feature/TournamentPageTest.php`
- Sole owner of this view and `tests/Feature/TournamentPageTest.php` — safe to run in parallel with
  Tasks 006–011.
- **Do not edit `resources/css/app.css`** — Task 001 owns it.
- **Never uppercase literal Blade strings** — `Start tournament`, `Not enough favorites`, `Going`,
  `Play again`, `The Champion` are asserted case-sensitively. Use the CSS `uppercase` utility.

## Exact replacement region — read this before editing
Only lines **292–316** are replaced:

| Lines | Content | Action |
|---|---|---|
| 289 | `@if ($state === 'result' && $this->winner)` | keep |
| 290–291 | outer div + card body wrapper | keep |
| **292–316** | badge → heading → cuisine tags → meta row (`price · address`) | **REPLACE with `<x-restaurant-result-ticket>`** |
| 319–334 | CTA group: Going, Play again | keep verbatim |

Everything before line 289 is the matchup/bracket UI and is explicitly out of scope — the head-to-head
buttons (lines ~230–283) keep their current styling. `TournamentPageTest` asserts restaurant names in
the matchup state (lines 113–128, 186–187); do not restructure that markup.

## The model property is `winner`, not `restaurant`

```blade
<x-restaurant-result-ticket
    :restaurant="$this->winner"
    :badge-label="__('Tournament Champion')"
/>
```

No `tagline`, `distanceLabel` or `eventLabel` — Tournament computes none of them.

**Expected visual change:** the champion's `address` currently sits inline in the meta row next to
price (`price · address`). Task 003's component renders `address` on its own line below the meta row.
That shape change is intended and approved — it is not a regression to report or work around.

## Requirements (Test Descriptions)
Existing tests in `TournamentPageTest.php` must continue to pass unchanged (`Start tournament`,
`Not enough favorites`, matchup name assertions, `The Champion`, `Going`). Add:
- [x] `it renders the champion using the restaurant result ticket component`
- [x] `it shows the Tournament Champion badge label`
- [x] `it still shows the going and play-again buttons after the restyle`

## Acceptance Criteria
- All requirements have passing tests added to the existing `tests/Feature/TournamentPageTest.php`;
  all pre-existing tests in that file still pass without modification.
- Result state renders via `<x-restaurant-result-ticket>` (Task 003) with `:restaurant="$this->winner"`;
  CTA buttons and `wire:click` handlers unchanged.
- The matchup/bracket markup and the PHP class block are unchanged.
- `resources/css/app.css` is not modified.
- No literal Blade string is uppercased in source.
- No decrease in test coverage.
- `vendor/bin/pint --dirty --format agent` clean.

## Implementation Notes
Replaced the badge/heading/cuisine-tags/meta-row block in the `result` state (previously lines
292–316) with a single `<x-restaurant-result-ticket :restaurant="$this->winner" :badge-label="__('Tournament Champion')" />`
call, wrapped in the existing `flex flex-1 flex-col justify-center` container (dropped the now-redundant
`gap-4` since the ticket component supplies its own internal spacing). CTA buttons (`Going`,
`Play again`) and their `wire:click` handlers were left untouched, as was all matchup/bracket/empty
markup and the PHP class block.

Added 3 new tests to `TournamentPageTest.php` under a new "Result ticket restyle" section, driving the
champion through a full 4-restaurant bracket via mocked `TournamentService`. The first test
(`renders the champion using the restaurant result ticket component`) asserts on
`style="font-family: var(--font-display);"`, a marker unique to the ticket component's heading — this
failed red before the change (old markup had no such style) and passed green after. The other two
(`Tournament Champion` badge, `Going`/`Play again` buttons) passed immediately since that markup was
already present pre-change; noted as pre-existing coverage rather than new behavior, kept as explicit
regression tests per the task's requirement list.

`vendor/bin/pint --dirty --format agent` clean. Full parallel suite has 1 unrelated pre-existing
failure in `HistoryPageTest.php` (Task 007's file, being edited concurrently) — confirmed not caused by
this change by running `tests/Feature/TournamentPageTest.php` in isolation (26/26 passing).
