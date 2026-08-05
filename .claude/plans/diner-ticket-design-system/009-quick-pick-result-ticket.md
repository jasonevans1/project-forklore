# Task 009: Quick Pick Result → Result Ticket

**Status**: pending
**Depends on**: 003
**Retry count**: 0

## Description
Replace Quick Pick's result-state informational markup with `<x-restaurant-result-ticket>`, keeping the Alpine swipe-to-reject wrapper, the CTA buttons (Going / Save as favorite / Not this one) and all idle/empty states untouched.

## Context
- Related files: `resources/views/pages/⚡pick.blade.php`, existing `tests/Feature/QuickPickPageTest.php`
- Sole owner of this view and `tests/Feature/QuickPickPageTest.php` — safe to run in parallel with
  Tasks 006–008, 010–012.
- **Do not edit `resources/css/app.css`** — Task 001 owns it.
- **Never uppercase literal Blade strings** — `Pick for us`, `Going`, `Not this one`,
  `No restaurants`, `Perfect patio weather` are all asserted case-sensitively. Use the CSS
  `uppercase` utility.

## Exact replacement region — read this before editing
The result block spans lines 190–303. Only lines **224–267** are replaced:

| Lines | Content | Action |
|---|---|---|
| 190 | `@if ($state === 'result' && $this->restaurant)` | keep |
| 191–221 | `x-data` Alpine block: swipe-left-to-reject (`$wire.reject()`), `:style` transform/opacity, `@touchstart`/`@touchmove`/`@touchend` | **KEEP — do not delete or refactor** |
| 222–223 | card body wrapper `<div class="flex flex-1 flex-col justify-center gap-4 px-6 pt-8">` | keep the wrapper (or let the component's own padding replace it) |
| **224–267** | badge → heading → cuisine tags → price/distance meta → tagline → address | **REPLACE with `<x-restaurant-result-ticket>`** |
| 270–296 | CTA group: Going, conditional Save as favorite, Not this one | keep verbatim |
| 298–301 | swipe hint `← Swipe left to skip` | keep |

The plan previously described the region as "roughly lines 190–225", which would have deleted the
Alpine swipe gesture. No test covers the swipe (it is client-side Alpine), so removing it fails
silently in production.

```blade
<x-restaurant-result-ticket
    :restaurant="$this->restaurant"
    :badge-label="__('Quick Pick')"
    :tagline="$tagline"
    :distance-label="$distanceLabel"
/>
```

Note `$tagline` is a `public string` defaulting to `''` (not nullable) — the component's
`@if ($tagline)` handles that.

## Do not lose the conditional "Save as favorite" CTA
Lines 280–288 render an extra button only when
`$this->restaurant->source === \App\Enums\RestaurantSource::Places`. It sits inside the CTA group
directly below the region being replaced and is easy to clip. It must survive, and gets a regression
test.

## Requirements (Test Descriptions)
Existing tests in `QuickPickPageTest.php` must continue to pass unchanged (`Pick for us`,
`The Noodle House`, `Thai`, `Noodles`, `No restaurants`, `Perfect patio weather`, `mi`,
`First`/`Second` rejection flow). Add:
- [x] `it renders the result using the restaurant result ticket component`
- [x] `it still shows the save as favorite button for a Places-sourced result`
- [x] `it still shows the going and reject buttons after the restyle`

## Acceptance Criteria
- All requirements have passing tests added to the existing `tests/Feature/QuickPickPageTest.php`;
  all pre-existing tests in that file and in `tests/Feature/PlacesQuickPickTest.php` still pass
  without modification.
- Result state renders via `<x-restaurant-result-ticket>` (Task 003); the Alpine swipe wrapper, the
  swipe hint, CTA buttons and all `wire:click` handlers are unchanged.
- No change to the component's PHP block (`restaurant` computed, `pick`, `going`, `reject`,
  `saveAsFavorite`, geolocation properties).
- `resources/css/app.css` is not modified.
- No literal Blade string is uppercased in source.
- No decrease in test coverage.
- `vendor/bin/pint --dirty --format agent` clean.

## Implementation Notes
- Replaced the informational block (badge/heading/cuisine tags/price+distance/tagline/address) in
  `resources/views/pages/⚡pick.blade.php` with `<x-restaurant-result-ticket>`, kept the surrounding
  card-body wrapper div, the Alpine swipe-to-reject block, CTA buttons, and swipe hint untouched.
- The "save as favorite" and "going/reject buttons" tests passed immediately against the
  pre-existing markup (no over-implementation needed for those two — they were already correct);
  only the ticket-component test was RED before the change, confirmed via `cuisine-tags` marker
  class unique to the shared component.
- Added three new tests to `tests/Feature/QuickPickPageTest.php`; all pre-existing tests in that
  file and `tests/Feature/PlacesQuickPickTest.php` pass unchanged.
- Full parallel suite: 1 unrelated pre-existing failure in `HistoryPageTest` (owned by a different
  parallel task, not touched by this change).
- `vendor/bin/pint --dirty --format agent` clean; `resources/css/app.css` untouched by this task.
