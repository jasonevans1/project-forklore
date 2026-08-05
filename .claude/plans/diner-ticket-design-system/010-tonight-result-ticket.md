# Task 010: Tonight Result → Result Ticket

**Status**: complete
**Depends on**: 003
**Retry count**: 0

## Description
Replace Tonight's result-state informational markup with `<x-restaurant-result-ticket>`, keeping the Alpine swipe-to-reject wrapper, the CTA buttons, and the idle/empty states untouched. The event label must stay **above** the restaurant name — an existing test asserts that order.

## Context
- Related files: `resources/views/pages/⚡tonight.blade.php`, existing `tests/Feature/TonightPageTest.php`
- Sole owner of this view and `tests/Feature/TonightPageTest.php` — safe to run in parallel with
  Tasks 006–009, 011–012.
- **Do not edit `resources/css/app.css`** — Task 001 owns it.
- **Never uppercase literal Blade strings** — `What's happening`, `Going`, `Not this one`,
  `Nothing happening` are asserted case-sensitively. Use the CSS `uppercase` utility.

## Exact replacement region — read this before editing
The result block spans lines 125–221. Only lines **159–195** are replaced:

| Lines | Content | Action |
|---|---|---|
| 125 | `@if ($state === 'result' && $this->restaurant)` | keep |
| 126–156 | `x-data` Alpine block: swipe-left-to-reject (`$wire.reject()`), transform/opacity, touch handlers | **KEEP — do not delete or refactor** |
| 157–158 | card body wrapper | keep |
| **159–195** | badge → `$eventLabel` → heading → cuisine tags → price meta → address | **REPLACE with `<x-restaurant-result-ticket>`** |
| 198–214 | CTA group: Going, Not this one | keep verbatim |
| 216–219 | swipe hint | keep |

The plan previously described the region as "roughly lines 125–199", which would have deleted the
Alpine swipe gesture. No test covers the swipe, so removing it fails silently in production.

## The event label is order-sensitive
`tests/Feature/TonightPageTest.php:61` asserts:

```php
->assertSeeInOrder(['Trivia starts at 7pm', 'The Tap Room']);
```

`$eventLabel` currently renders between the badge and the restaurant name. Pass it through Task 003's
`eventLabel` prop — **do not** render it above or below the component as a separate element, and do
not drop it.

```blade
<x-restaurant-result-ticket
    :restaurant="$this->restaurant"
    :badge-label="__('Tonight')"
    :event-label="$eventLabel"
/>
```

Tonight passes no `tagline` and no `distanceLabel`.

## Requirements (Test Descriptions)
Existing tests in `TonightPageTest.php` must continue to pass unchanged — in particular
`it shows the event detail label above the restaurant name` (the `assertSeeInOrder` above), the
`Going`/`Not this one` result-state tests, and the reject/exclusion flow tests that mock
`TonightService`. Add:
- [x] `it renders the result using the restaurant result ticket component`
- [x] `it still shows the event label above the restaurant name after the restyle`

## Acceptance Criteria
- All requirements have passing tests added to the existing `tests/Feature/TonightPageTest.php`; all
  16 pre-existing tests in that file still pass without modification.
- Result state renders via `<x-restaurant-result-ticket>` (Task 003); the Alpine swipe wrapper, the
  swipe hint, CTA buttons and all `wire:click` handlers are unchanged.
- No change to the component's PHP block.
- `resources/css/app.css` is not modified.
- No literal Blade string is uppercased in source.
- No decrease in test coverage.
- `vendor/bin/pint --dirty --format agent` clean.

## Implementation Notes
Replaced the card-body informational block (badge → event label → heading → cuisine tags → price
meta → address) with `<x-restaurant-result-ticket :restaurant="$this->restaurant" :badge-label="__('Tonight')" :event-label="$eventLabel" />`.
Alpine swipe wrapper, CTA buttons, swipe hint, and idle/empty states were left untouched. Added two
new tests to `tests/Feature/TonightPageTest.php`: one asserts the ticket component's distinguishing
`font-family: var(--font-display);` heading style is present (proxy for "renders via the component"
since `x-restaurant-result-ticket` compiles away and leaves no literal tag name in output), the
other re-asserts the pre-existing event-label-before-name ordering to lock in the restyle. All 18
tests in the file pass; `vendor/bin/pint --dirty --format agent` reports clean for the touched
files.
