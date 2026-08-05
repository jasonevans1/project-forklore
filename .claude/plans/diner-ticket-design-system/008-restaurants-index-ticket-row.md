# Task 008: Restaurants Index → Ticket Row

**Status**: complete
**Depends on**: 002
**Retry count**: 0

## Description
Replace the Restaurants index page's `<flux:card>`-based list with `<x-ticket-row>`, keeping the existing sort/query logic and empty state untouched.

## Context
- Related files: `resources/views/pages/restaurants/⚡index.blade.php`, existing
  `tests/Feature/RestaurantIndexTest.php`
- Existing markup to replace: lines **43–67** (the `<flux:card>` per-restaurant block). The
  `flex flex-col` container stays.
- Sole owner of this view and `tests/Feature/RestaurantIndexTest.php` — safe to run in parallel with
  Tasks 006, 007, 009–012.
- Do NOT change the `restaurants` computed property (`Restaurant::ownedBy(...)->orderBy('name')`).
- Existing `RestaurantIndexTest.php` assertions that must keep passing (case-sensitive):
  `No restaurants yet`, `Pasta Palace`, `Sushi Garden`, `Italian`, `Pizza`, **`$$$`**,
  `My Restaurant`, `assertDontSee('Other Person Place')`, `route('restaurants.create')`.
- **Do not edit `resources/css/app.css`** — Task 001 owns it.
- **Never uppercase literal Blade strings** — the empty state and cuisine tag values are asserted
  case-sensitively. Use the CSS `uppercase` utility. Note cuisine tags are user data; uppercasing
  them in source is impossible anyway, but do not apply a CSS `uppercase` to the tag badges either,
  or the *visual* result stops matching what the user typed.

## `<x-ticket-row>` usage (per Task 002's fixed contract)
This row carries **two** pieces of metadata — cuisine tag badges *and* price — so both go in the
default slot, not in `badgeLabel`:

```blade
<x-ticket-row :name="$restaurant->name" :href="route('restaurants.show', $restaurant)">
    @if (! empty($restaurant->cuisine_tags))
        <div class="flex flex-wrap gap-2">
            @foreach ($restaurant->cuisine_tags as $tag)
                <flux:badge size="sm">{{ $tag }}</flux:badge>
            @endforeach
        </div>
    @endif

    @if ($restaurant->price_level !== null)
        <span>{{ str_repeat('$', $restaurant->price_level) }}</span>
    @endif
</x-ticket-row>
```

**Price must render as one contiguous string.** `RestaurantIndexTest.php:50` asserts
`assertSee('$$$')` — splitting the dollar signs into per-glyph spans for a ticket flourish breaks the
test even though the page looks correct.

The whole row becomes the link via `href` (previously only the name was wrapped in an `<a>`) — that
is an intentional improvement to the tap target on mobile.

## Requirements (Test Descriptions)
Existing tests in `RestaurantIndexTest.php` must continue to pass unchanged. Add:
- [x] `it renders each restaurant using the ticket row component`
- [x] `it links each row to the restaurant show page`

## Acceptance Criteria
- All requirements have passing tests added to the existing `tests/Feature/RestaurantIndexTest.php`;
  all pre-existing tests in that file still pass without modification — in particular the `$$$`
  assertion.
- Per-restaurant row renders via `<x-ticket-row>` (Task 002), not `<flux:card>`.
- Sort order, ownership scoping, and the empty state are behaviorally unchanged.
- `resources/css/app.css` is not modified.
- No literal Blade string is uppercased in source.
- No decrease in test coverage.
- `vendor/bin/pint --dirty --format agent` clean.

## Implementation Notes
- Replaced the `<flux:card>` per-restaurant block with `<x-ticket-row>` exactly per the plan's usage
  example. Cuisine tag badges and price string both live in the default slot.
- `it links each row to the restaurant show page` passed immediately without any implementation
  change — the pre-existing markup already wrapped a `href="{{ route(...) }}"` matching the
  assertion, so `<x-ticket-row>`'s `href` prop preserves that behavior automatically. No
  over-implementation occurred; this was simply already covered by the row's `href` contract.
- `it renders each restaurant using the ticket row component` asserts on the `bg-ticket-bg` class
  from the ticket-row component's root element (RED without the component, GREEN after swap).
- All 8 pre-existing + new tests in `RestaurantIndexTest.php` pass; `$$$` assertion intact since
  price renders as a single `<span>` string, not per-glyph spans.
- Full parallel suite showed one unrelated failure in `HistoryPageTest.php` — that file is being
  edited concurrently by the sibling Task 007 agent (confirmed via `git status`), not touched by
  this task.
- `vendor/bin/pint --dirty --format agent` clean.
