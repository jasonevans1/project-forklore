# Task 006: Restyle Dashboard

**Status**: complete
**Depends on**: 001, 002
**Retry count**: 0

## Description
Apply the diner treatment to the dashboard: the four decision-mode cards become a numbered ticket-style menu (matching the welcome page's four-mode list), and the "Recently Added" restaurant list is swapped to use `<x-ticket-row>` instead of its current hand-rolled markup.

## Context
- Related files: `resources/views/pages/⚡dashboard.blade.php`, existing
  `tests/Feature/DashboardVoltTest.php`
- Depends on Task 002 for `<x-ticket-row>` (Recently Added list) and Task 001 for tokens (mode-card
  grid).
- Sole owner of `⚡dashboard.blade.php` and `tests/Feature/DashboardVoltTest.php` in this plan.
- **Do not touch `tests/Feature/DashboardTest.php`** — it holds only two route-guard tests and Task
  005 previously targeted it; keep it out of this plan entirely to avoid a concurrent-edit conflict.
  All dashboard content assertions live in `DashboardVoltTest.php`.
- Existing `DashboardVoltTest.php` assertions that must keep passing (all case-sensitive):
  `Quick Pick`, `Tonight`, `Guided Quiz`, `Tournament`, each with its `route(...)`;
  `"You haven't added any restaurants yet."`; `route('restaurants.create')`; the 3-restaurant limit
  test; and the ordering test at lines 103–120 which uses `strpos()` on the raw HTML to assert
  newest-first — **the ticket-row swap must preserve the DOM order of the three names.**
- Keep the existing computed property (`recentRestaurants`) and route links (`wire:navigate`)
  unchanged — this is a markup/styling task only.
- **Do not edit `resources/css/app.css`** — Task 001 owns it.
- **Never uppercase literal Blade strings** — the four mode names above are asserted case-sensitively.
  Use the CSS `uppercase` utility.

## `<x-ticket-row>` usage for Recently Added (per Task 002's fixed contract)
Replace lines 96–110 with:

```blade
<x-ticket-row :name="$restaurant->name" :href="route('restaurants.show', $restaurant)">
    @if (! empty($restaurant->cuisine_tags))
        {{ implode(', ', $restaurant->cuisine_tags) }}
    @endif

    <x-slot:trailing>
        <flux:icon name="chevron-right" class="size-4 shrink-0" />
    </x-slot:trailing>
</x-ticket-row>
```

The chevron goes in the `trailing` slot (not `badgeLabel`, which only renders a `<flux:badge>`), and
`href` is passed to the component rather than wrapping it in a hand-rolled `<a>`.

## Requirements (Test Descriptions)
Existing tests in `DashboardVoltTest.php` must continue to pass unchanged. Add:
- [x] `it renders the recently added list using the ticket row component`
- [x] `it links each recently added row to the restaurant show page`

## Acceptance Criteria
- All requirements have passing tests added to the existing `tests/Feature/DashboardVoltTest.php`;
  all 11 pre-existing tests in that file still pass without modification (especially the
  `strpos`-based ordering test).
- Recently Added list renders via `<x-ticket-row>` (Task 002), not duplicated markup.
- Mode-card grid uses `--font-display` and the accent token consistent with the welcome page
  treatment; respect Task 001's recorded accent decision about small-text accent usage.
- `tests/Feature/DashboardTest.php` is not modified.
- `resources/css/app.css` is not modified.
- No literal Blade string is uppercased in source.
- No decrease in test coverage.
- `vendor/bin/pint --dirty --format agent` clean.

## Implementation Notes
- Mode-card grid replaced with a single-column numbered ticket list (01-04), mirroring the welcome
  page: dashed perforation dividers (`divide-y divide-dashed divide-ticket-line`), top/bottom
  border, `font-mono-ticket` numbers, `font-display uppercase` headings, `text-accent` icon/number
  color.
- Recently Added rows swapped to `<x-ticket-row>` per the fixed contract; cuisine tags in the
  default slot, chevron in the `trailing` slot, `href` passed to the component.
- Added two new tests to `tests/Feature/DashboardVoltTest.php`:
  `it renders the recently added list using the ticket row component` (asserts `bg-ticket-bg` HTML
  from the component) and `it links each recently added row to the restaurant show page`.
- `tests/Feature/DashboardTest.php` and `resources/css/app.css` untouched.
- Full parallel suite: 3 unrelated pre-existing failures in `RestaurantIndexTest` and
  `HistoryPageTest` (Tasks 007/008, in progress concurrently by other agents) — not touched by this
  task; `DashboardVoltTest.php` is 13/13 green.
