# Task 002: `<x-ticket-row>` Component

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Build the reusable compact list-row component that will replace the near-identical hand-rolled markup currently duplicated in History, Restaurants index, and Dashboard's Recently Added list. Three separate downstream tasks (006, 007, 008) consume this component **in parallel**, so the prop signature below is a fixed contract — build exactly this, do not narrow it.

## Context
- Related files: new `resources/views/components/ticket-row.blade.php`, new `tests/Feature/TicketRowComponentTest.php`
- Current duplicated markup this replaces:
  - `resources/views/pages/⚡history.blade.php` lines 59–81 (`<ul>`/`<li>` per-visit row)
  - `resources/views/pages/⚡dashboard.blade.php` lines 96–110 (Recently Added `<a>` row)
  - `resources/views/pages/restaurants/⚡index.blade.php` lines 43–67 (`<flux:card>` per-restaurant block)
- Patterns to follow: `resources/views/components/restaurants/form-fields.blade.php` for how this project structures anonymous Blade components (`@props([...])` at the top, single root element).
- Uses tokens from Task 001: `--color-ticket-bg`, `--color-ticket-ink`, `--color-ticket-line`, `--font-mono-ticket` for numeric/label metadata, `--font-display` for the restaurant name.
- **Do not edit `resources/css/app.css`** — Task 001 owns it. The dashed perforation rule is stock Tailwind: `border-t border-dashed border-ticket-line`.
- **Never uppercase literal Blade strings** — use the CSS `uppercase` utility. See Task 001's uppercase rule; ~25 existing tests assert exact-case text.

## Fixed prop contract (tasks 006/007/008 build against this — do not change it)

```blade
@props([
    'name',                 // string, required — already-resolved display text
    'href' => null,         // string|null — when set the root renders as <a href wire:navigate>
    'badgeLabel' => null,   // string|null — convenience: renders a <flux:badge> in the trailing area
])
{{-- $slot     => metadata area beneath the name; arbitrary markup (tags, dates, price) --}}
{{-- $trailing => optional named slot for the right-hand area; overrides badgeLabel --}}
```

Why each piece is needed — verified against the three real callers:

| Caller | `name` | `$slot` (meta) | trailing | `href` |
|---|---|---|---|---|
| History (007) | `$visit->restaurant?->name ?? __('Unknown restaurant')` | `visited_at->format('M j')` | `badgeLabel` = mode label | none (not clickable) |
| Dashboard (006) | `$restaurant->name` | `implode(', ', $restaurant->cuisine_tags)` | `$trailing` = `<flux:icon name="chevron-right">` | `route('restaurants.show', $restaurant)` |
| Restaurants index (008) | `$restaurant->name` | cuisine tag `<flux:badge>`s **and** `str_repeat('$', $restaurant->price_level)` | none | `route('restaurants.show', $restaurant)` |

A single `badgeLabel` string cannot render the dashboard's chevron icon; a single meta *string*
cannot render the index's badge row plus price. `href` lives in the component so the link, hover and
focus-ring styling exist in one place instead of being re-implemented by two of three callers.

Additional constraints:
- Renders a **single root element** — callers own their own list/grid container (History wraps it in
  `<li>`, the other two in a flex column). Do not emit `<li>` or `<ul>` from the component.
- When `href` is set, the root is `<a href="…" wire:navigate>` and must carry a visible keyboard
  focus style (the row is the whole click target).
- `{{ $attributes }}` must be merged onto the root so callers can add spacing/utility classes.
- Presentation only — no `wire:click`, no model queries, no `Restaurant`-specific logic. Callers pass
  already-resolved strings and markup.

## Requirements (Test Descriptions)
- [x] `it renders the restaurant name`
- [x] `it renders a trailing badge when a badge label is passed`
- [x] `it omits the badge when no badge label is passed`
- [x] `it renders slot content as the metadata line`
- [x] `it renders without error when no metadata slot content is passed`
- [x] `it renders the root as a link with wire navigate when an href is passed`
- [x] `it renders the root as a non-link element when no href is passed`
- [x] `it renders the trailing slot instead of the badge when both are provided`

## Testing approach
Use `$this->blade('<x-ticket-row … />', [...])` (Laravel's `InteractsWithViews`, available on the
base `TestCase`). **This is a new pattern for this repo** — grep confirms `$this->blade(` appears
nowhere in `tests/`, and `RestaurantFormFieldsTest.php` (sometimes cited as the precedent) actually
uses `Livewire::test('pages::restaurants.create')` against the whole page. Establish the pattern
here so Task 003 can mirror it:

```php
it('renders the restaurant name', function () {
    $this->blade('<x-ticket-row name="Noodle Palace" />')
        ->assertSee('Noodle Palace');
});
```

Use `assertSeeInOrder` / `assertSee(..., false)` where raw HTML matters (e.g. `wire:navigate`).

## Acceptance Criteria
- All requirements have passing tests in a new `tests/Feature/TicketRowComponentTest.php`.
- The prop contract above is implemented exactly as written — 006, 007 and 008 are blocked on it.
- Uses `--color-ticket-*` and `--font-mono-ticket`/`--font-display` tokens from Task 001, not ad-hoc
  colors. Respect Task 001's recorded accent decision about small-text accent usage.
- `resources/css/app.css` is not modified.
- No literal Blade string is uppercased in source.
- No decrease in test coverage.
- `vendor/bin/pint --dirty --format agent` clean.

## Implementation Notes
- Root element toggles between `<a href wire:navigate>` and `<div>` via `$tag = $href ? 'a' : 'div'`,
  interpolated as `<{{ $tag }}>` / `</{{ $tag }}>` — a single conditional keeps the root a genuine
  single element (no duplicate branches to keep in sync).
- Uses Tailwind utility classes generated from the Task 001 static tokens rather than inline
  `style="...var(...)"`: `font-display`, `font-mono-ticket`, `bg-ticket-bg`, `text-ticket-ink`,
  `focus-visible:outline-ticket-accent`. Confirmed via `npm run build` that Tailwind emits all of
  these (`.font-display{font-family:var(--font-display)}`, etc.) since Task 001 declared them in
  `@theme static`.
- `$trailing` (named slot) takes priority over `badgeLabel` per the contract table — checked with
  `isset($trailing)` before falling back to the `badgeLabel` `flux:badge`.
- Focus-visible ring uses `outline` + `outline-2` + `outline-ticket-accent` (not `ring-*`) since the
  row's whole area is the click target and an offset ring would clip against sibling rows.
- Two requirements passed immediately on first run without new code: "omits badge" (already
  satisfied by the `@if ($badgeLabel)` guard needed for the previous requirement) and "non-link
  element when no href" (already satisfied by the `$tag` conditional needed for the link
  requirement). Both are natural consequences of the minimal prior GREEN step, not
  over-implementation — noted per TDD process.
- Did not touch `resources/css/app.css` (Task 001's file).
- Pre-existing, unrelated failure observed in full suite run: `EventModelTest > isActiveNow returns
  true when the event occurs at the current time` — fails in isolation on a clean run too, unrelated
  to Event model changes made here (none). Not in scope for this task.
