# Task 003: `<x-restaurant-result-ticket>` Component

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Build the reusable "single restaurant reveal" component that replaces the near-identical result-state markup currently duplicated across Quick Pick, Tonight, Quiz, and Tournament (badge → optional event label → heading → cuisine tags → price/distance → optional tagline/address). Presentation only — each page keeps its own CTA buttons (Going/reject/play again/etc.) below the component; this component contains no `wire:click` behavior.

Four downstream tasks (009–012) consume this **in parallel**, so the prop signature below is a fixed contract — build exactly this, do not narrow it.

## Context
- Related files: new `resources/views/components/restaurant-result-ticket.blade.php`, new `tests/Feature/RestaurantResultTicketComponentTest.php`
- The four near-duplicate blocks it replaces (informational block only, excluding the surrounding
  Alpine wrapper and the CTA group):
  - `resources/views/pages/⚡pick.blade.php` lines **224–267**
  - `resources/views/pages/⚡tonight.blade.php` lines **159–195**
  - `resources/views/pages/⚡quiz.blade.php` lines **640–681**
  - `resources/views/pages/⚡tournament.blade.php` lines **292–316**
- The PHP-side data (tagline, distance label) is already centralized in
  `app/Concerns/ComputesRestaurantPresentation.php` — this task only touches the Blade presentation
  layer, do not duplicate or move that logic.
- Patterns to follow: same anonymous-component convention as Task 002, same `$this->blade(...)`
  testing approach Task 002 establishes.
- Uses tokens from Task 001: `--color-ticket-*` for the card surface, `--font-display` for the
  restaurant name heading, `--font-mono-ticket` for price/distance metadata.
- **Do not edit `resources/css/app.css`** — Task 001 owns it.
- **Never uppercase literal Blade strings** — CSS `uppercase` utility only (Task 001's rule).

## Fixed prop contract (tasks 009–012 build against this — do not change it)

```blade
@props([
    'restaurant',              // Restaurant model instance
    'badgeLabel',              // string — 'Quick Pick' | 'Tonight' | 'Quiz Pick' | 'Tournament Champion'
    'eventLabel' => null,      // string|null — Tonight only; renders BETWEEN badge and name
    'tagline' => null,         // string|null — Quick Pick + Quiz
    'distanceLabel' => null,   // string|null — Quick Pick + Quiz
])
```

### `eventLabel` is not optional to implement
`⚡tonight.blade.php` renders `$eventLabel` **between the badge and the restaurant name**
(lines 163–168), and `tests/Feature/TonightPageTest.php:61` asserts
`assertSeeInOrder(['Trivia starts at 7pm', 'The Tap Room'])`. Without this prop, Task 010 is blocked
behind a change to this component after 009/011/012 already consume it. Render it between badge and
heading.

### Render order (fixed — an existing test depends on it)
1. `badgeLabel` badge
2. `eventLabel` (when provided)
3. restaurant name heading
4. cuisine tag badges (when `$restaurant->cuisine_tags` is non-empty)
5. meta row: `str_repeat('$', $restaurant->price_level)` when non-null, then `distanceLabel` when
   provided, separated by an `aria-hidden` middot
6. `tagline` (when truthy — note callers may pass `''`, not `null`; `@if ($tagline)` handles both)
7. `$restaurant->address` (when present)

Price must render as **one contiguous string** (`$$$`), not per-glyph spans —
`RestaurantIndexTest:50` and the result-screen tests use plain `assertSee('$$$')`.

Additional constraints:
- Reads only `name`, `cuisine_tags`, `price_level`, `address` off the model. No relations, no queries,
  no `$restaurant->id` — keep it usable with any `Restaurant` instance.
- No `wire:click` or other behavioral attributes — it is consumed by four different Livewire
  components which each own their own CTA group.
- Merge `{{ $attributes }}` onto the root so callers can add layout classes.
- Tournament passes `:restaurant="$this->winner"` (its model property is `winner`, not `restaurant`)
  and passes no `tagline`/`distanceLabel`/`eventLabel`. Its `address` currently sits inline in the
  meta row; under this component it moves to its own line — that shape change is expected and
  approved, not a bug.

## Requirements (Test Descriptions)
- [x] `it renders the restaurant name`
- [x] `it renders the badge label passed in`
- [x] `it renders the event label between the badge and the restaurant name when provided`
- [x] `it omits the event label when none is provided`
- [x] `it renders cuisine tag badges when the restaurant has cuisine tags`
- [x] `it omits the cuisine tags block when the restaurant has no cuisine tags`
- [x] `it renders the price level as one contiguous run of dollar signs when present`
- [x] `it omits the price level when it is null`
- [x] `it renders the distance label when provided`
- [x] `it renders the tagline when provided`
- [x] `it renders nothing for the tagline when passed an empty string`
- [x] `it renders the address when present`
- [x] `it omits the address when the restaurant has none`

## Acceptance Criteria
- All requirements have passing tests in a new `tests/Feature/RestaurantResultTicketComponentTest.php`
  using `$this->blade(...)` with a factory-built (`->make()` is sufficient) `Restaurant`.
- The prop contract and render order above are implemented exactly — 009–012 are blocked on them, and
  the event-label ordering is asserted by an existing Tonight test.
- No `wire:click`/behavioral attributes inside the component.
- `resources/css/app.css` is not modified.
- No literal Blade string is uppercased in source.
- No decrease in test coverage.
- `vendor/bin/pint --dirty --format agent` clean.

## Implementation Notes
- Built `resources/views/components/restaurant-result-ticket.blade.php` as a single anonymous
  component matching the fixed prop contract exactly (`restaurant`, `badgeLabel`, `eventLabel`,
  `tagline`, `distanceLabel`), following the `@props([...])` + single-root-element convention from
  Task 002's `ticket-row.blade.php` and `restaurants/form-fields.blade.php`.
- Render order matches spec: badge → eventLabel → heading (`var(--font-display)`) → cuisine tag
  `flux:badge`s (wrapped in a `.cuisine-tags` div, used only as a test hook, not styling) → meta row
  (`var(--font-mono-ticket)`) with contiguous `str_repeat('$', ...)` price + aria-hidden middot +
  distanceLabel → tagline (italic) → address.
- Root `<div>` merges `{{ $attributes }}` via `$attributes->merge(['class' => 'flex flex-col gap-4'])`
  so callers can layer on layout classes without losing the base stack layout.
- No `wire:click`/behavioral attributes; reads only `name`, `cuisine_tags`, `price_level`, `address`
  off the model.
- All 13 tests written first and confirmed RED (`Unable to locate a class or view for component`)
  before the component file existed; the single implementation pass turned all 13 GREEN with no
  further changes needed, so no per-requirement iteration was required beyond the initial RED/GREEN
  cycle.
- Full parallel suite run: 713 passed, 1 pre-existing failure (`EventModelTest > isActiveNow returns
  true when the event occurs at the current time`) — reproduced identically on the base commit via
  `git stash`, confirming it's an unrelated time-boundary flake, not caused by this task.
- `vendor/bin/pint --dirty --format agent` → passed (no PHP files touched by this task; test file is
  plain Pest/PHP and was already clean).
- `resources/css/app.css` untouched; no literal Blade strings uppercased.
