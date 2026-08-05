# Task 007: History Page → Ticket Row

**Status**: complete
**Depends on**: 002
**Retry count**: 0

## Description
Replace History's hand-rolled visit-row markup with `<x-ticket-row>`, keeping the existing month-grouping and empty-state logic untouched.

## Context
- Related files: `resources/views/pages/⚡history.blade.php`, existing `tests/Feature/HistoryPageTest.php`
- Existing markup to replace: lines **59–81** (the `<ul>`/`<li>` per-visit row inside each month
  `<section>`). The `<ul>` container stays — `<x-ticket-row>` renders a single root element, so wrap
  it in the existing `<li>`.
- Sole owner of `⚡history.blade.php` and `tests/Feature/HistoryPageTest.php` — safe to run in
  parallel with Tasks 006, 008–012.
- Do NOT change the `visitsByMonth` computed property or the `LIMIT = 50` constant — two existing
  tests read `$component->get('visitsByMonth')` directly.
- Existing `HistoryPageTest.php` assertions that must keep passing (case-sensitive):
  `No visits`, `Noodle Palace`, `Mar`, `Quick Pick`, `June 2025`, `May 2025`,
  `assertDontSee('Secret Spot')`.
- **Do not edit `resources/css/app.css`** — Task 001 owns it.
- **Never uppercase literal Blade strings** — the month heading (`June 2025`), the mode badge
  (`Quick Pick`) and the empty state (`No visits yet`) are all asserted case-sensitively. Use the CSS
  `uppercase` utility.

## Preserve the deleted-restaurant fallback
Line 64 is currently `{{ $visit->restaurant?->name ?? __('Unknown restaurant') }}`. `<x-ticket-row>`
takes an already-resolved string, so the null-safe resolution must move into the caller's
`@foreach`:

```blade
<li>
    <x-ticket-row
        :name="$visit->restaurant?->name ?? __('Unknown restaurant')"
        :badge-label="/* existing match() on $visit->mode_used */"
    >
        {{ $visit->visited_at->format('M j') }}
    </x-ticket-row>
</li>
```

A visit whose restaurant row was deleted is a real state (`Visit` keeps `restaurant_id`), and losing
the `??` fallback produces a blank row rather than an error — silent in tests unless asserted.

## Requirements (Test Descriptions)
Existing tests in `HistoryPageTest.php` must continue to pass unchanged. Add:
- [x] `it renders each visit using the ticket row component`
- [x] `it shows an unknown restaurant fallback when the visit's restaurant has been deleted`

## Acceptance Criteria
- All requirements have passing tests added to the existing `tests/Feature/HistoryPageTest.php`; all
  10 pre-existing tests in that file still pass without modification.
- Per-visit row renders via `<x-ticket-row>` (Task 002), not the old `<li>` markup.
- Month grouping, the 50-visit limit, and the empty state are behaviorally unchanged.
- `resources/css/app.css` is not modified.
- No literal Blade string is uppercased in source.
- No decrease in test coverage.
- `vendor/bin/pint --dirty --format agent` clean.

## Implementation Notes
- `resources/views/pages/⚡history.blade.php`: replaced the hand-rolled `<li>` per-visit markup with
  `<li><x-ticket-row :name="..." :badge-label="...">{{ date }}</x-ticket-row></li>`, keeping the
  outer `<ul>` and `@foreach` unchanged. The `??  __('Unknown restaurant')` fallback moved from the
  old inline `<span>` into the `:name` prop expression, and the existing `match()` on `mode_used`
  moved into `:badge-label`.
- `tests/Feature/HistoryPageTest.php`: added both required tests.
  - `it renders each visit using the ticket row component` asserts `bg-ticket-bg` (the ticket-row
    root class) is present via `assertSeeHtml`.
  - `it shows an unknown restaurant fallback...` — the `visits.restaurant_id` FK is
    `cascadeOnDelete()`, so deleting a restaurant also deletes its visits; `Schema::disableForeign
    KeyConstraints()` is a no-op for SQLite mid-transaction (confirmed empirically), so an actual
    orphaned row can't be produced via a real `->delete()` inside the `RefreshDatabase` transaction.
    Worked around this by deleting the restaurant first (fine, no visit exists yet), then inserting
    the `Visit` via the factory with `PRAGMA defer_foreign_keys = ON` so the FK violation is deferred
    to commit time — which never happens because the test transaction rolls back. This reproduces a
    genuinely orphaned `restaurant_id` without touching the schema.
- No changes to `visitsByMonth`, the `LIMIT` constant, `resources/css/app.css`, or any uppercased
  literal strings.
- `vendor/bin/pint --dirty --format agent`: passed. Full suite: 733 passed (parallel).
