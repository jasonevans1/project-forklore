# Plan: Diner Ticket Design System

## Created
2026-07-30

## Status
completed

## Objective
Replace the app's default Flux/zinc styling with the "Order Up" diner-ticket visual identity (ink ground, mustard accent, condensed display type, monospace ticket metadata, dashed-rule ticket cards) across the marketing page, authenticated shell, and every screen that lists or reveals a restaurant.

## Related Issues
none

## Discovery Notes
- Colors and fonts already flow through one token layer: `resources/css/app.css` (`--color-accent`, `--font-sans`). Most Flux components repaint automatically once tokens change.
- Light/dark already works app-wide via `@fluxAppearance` (toggled from Settings → Appearance). No new light/dark plumbing needed — just new token values.
- `resources/views/layouts/app/header.blade.php` is dead code (unreferenced starter-kit leftover). The real shell is `layouts/app/sidebar.blade.php` via `layouts/app.blade.php`. Left untouched — out of scope.
- Three screens duplicate a near-identical "restaurant list row" pattern (name + meta + border card): `⚡history.blade.php` (59–81), `⚡dashboard.blade.php` Recently Added (96–110), `restaurants/⚡index.blade.php` (43–67). Extraction target: one `<x-ticket-row>` component. They are *not* identical — History is not clickable and carries a mode badge, Dashboard is clickable with a chevron, the index is clickable and carries cuisine badges plus price. The component contract in Architecture Notes covers all three.
- Four screens duplicate a near-identical "single restaurant result reveal" pattern (badge → heading → cuisine tags → price/distance meta → optional tagline/address → CTA buttons): `⚡pick.blade.php` (224–267), `⚡tonight.blade.php` (159–195), `⚡quiz.blade.php` (641–680), `⚡tournament.blade.php` (292–316). The PHP-side data (tagline, distance) is already shared via `app/Concerns/ComputesRestaurantPresentation.php`; the Blade markup is not. Extraction target: one `<x-restaurant-result-ticket>` component. Divergences the component must absorb: Tonight has an `eventLabel` between badge and name (order asserted by an existing test), Tournament uses `$this->winner` and has no tagline/distance, Quiz appends `skippedStepsMessage` after the card.
- Quick Pick and Tonight wrap their result in an Alpine `x-data` swipe-left-to-reject block (`⚡pick.blade.php` 191–221, `⚡tonight.blade.php` 126–156). It sits *inside* the result `@if` and must survive the extraction; no test covers it.
- Fonts load from bunny.net today (`fonts.bunny.net`, Instrument Sans) — same mechanism will carry the new display/mono faces; no new build tooling needed.
- Reference mockup (colors, type, ticket-row pattern) lives outside the repo at `/private/tmp/claude-501/-Users-jasonevans-projects-project-forklore/12cf1347-f02d-4cd7-9443-0e5499262813/scratchpad/forklore-home-directions.html`, `.concept-a` section only.
- User-confirmed scope: quiz/tournament get the *result card* restyled only — question steps, matchup buttons, and progress UI stay as-is. Restaurant CRUD forms, settings, and auth pages are explicitly left to inherit the new tokens passively — no dedicated tasks.

## Scope

### In Scope
- New color tokens (light "ticket counter" + dark "diner at night") and two new font tokens (condensed display, monospace ticket) in `resources/css/app.css`, fonts loaded via bunny.net in `partials/head.blade.php`.
- `welcome.blade.php` rebuilt as the reference implementation.
- Authenticated shell (`layouts/app/sidebar.blade.php`, `x-app-logo`) restyled.
- Dashboard: mode-card grid restyled as numbered ticket rows; "Recently Added" list converted to `<x-ticket-row>`.
- Reusable `<x-ticket-row>` component (compact list row: name, meta, badge) — applied to History, Restaurants index, Dashboard.
- Reusable `<x-restaurant-result-ticket>` component (full reveal: badge, heading, tags, price/distance, tagline/address) — applied to the `result` state of Quick Pick, Tonight, Quiz, Tournament. CTA buttons below the card are unchanged (existing Flux buttons), only the informational card is replaced.

### Out of Scope
- Quiz question steps, tournament matchup/bracket UI, progress indicators — anything that isn't the final result reveal.
- Restaurant create/edit forms, Settings pages, auth pages (login/register/2FA) — inherit new tokens passively, no dedicated tasks.
- `layouts/app/header.blade.php` (dead code) — not touched.
- Any new dependency, build tool, or JS framework — pure Blade/Tailwind/Flux.

## Success Criteria
- [ ] `app.css` exposes diner tokens in both light and dark, no visual regression in existing token consumers.
- [ ] All three accent tokens (`--color-accent`, `--color-accent-content`, `--color-accent-foreground`) are explicitly set for both themes, using `#8a5220` for the light accent per the resolved contrast gate.
- [ ] Every pre-existing test in the nine touched test files still passes **without modification** — the design rollout changes no user-facing copy.
- [ ] Welcome, dashboard, history, restaurants index, and all four result screens render the diner ticket treatment.
- [ ] `<x-ticket-row>` and `<x-restaurant-result-ticket>` each have a single implementation reused across all consuming screens (no duplicated markup).
- [ ] All tests passing (`php artisan test --compact --parallel`).
- [ ] Code follows project standards (`vendor/bin/pint --dirty --format agent` clean).

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Diner design tokens + webfonts (+ `DesignTokensTest`) | - | completed |
| 002 | `<x-ticket-row>` component | 001 | completed |
| 003 | `<x-restaurant-result-ticket>` component | 001 | completed |
| 004 | Rebuild welcome page (extends `LandingPageTest`) | 001 | completed |
| 005 | Restyle authenticated shell (extends `SidebarTest`/`AppLogoTest`) | 001 | completed |
| 006 | Restyle dashboard (mode cards + recently-added; extends `DashboardVoltTest`) | 001, 002 | completed |
| 007 | History page → ticket-row | 002 | completed |
| 008 | Restaurants index → ticket-row | 002 | completed |
| 009 | Quick Pick result → result-ticket | 003 | completed |
| 010 | Tonight result → result-ticket | 003 | completed |
| 011 | Quiz result → result-ticket | 003 | completed |
| 012 | Tournament result → result-ticket | 003 | completed |

## Architecture Notes

**Token values** (add to `resources/css/app.css` in a new **`@theme static { ... }`** block, with the
`.dark` overrides going in the existing `@layer theme { .dark { ... } }` block):

```
--font-display: 'Oswald', 'Arial Narrow', sans-serif;      /* condensed caps headings */
--font-mono-ticket: 'JetBrains Mono', ui-monospace, monospace; /* ticket metadata */

--color-page:  #f7f2e8;   /* light ground — "ticket counter" cream */
--color-ink:   #241c12;   /* light text */
--color-accent: #8a5220;  /* light accent — deep amber, 5.08:1 on cream (AA) */

.dark:
--color-page:  #1c1712;   /* dark ground — "diner at night" ink */
--color-ink:   #f2e9d6;
--color-accent: #e3a742;  /* dark accent — bright mustard */
```

**Why `@theme static`.** Tailwind v4 only emits a `@theme` variable into the compiled CSS if a
generated utility actually references it. Downstream tasks may reference these tokens as
`var(--color-ticket-bg)` inside an arbitrary value or inline style, which usage detection does not
see — the variable would be silently undefined at runtime with no build error and no test failure.
`@theme static` always emits. Do not convert the existing `@theme` block (it would emit the whole
zinc scale).

**Three accent tokens, not one.** `app.css` defines `--color-accent` (accent backgrounds),
`--color-accent-content` (accent text/links) and `--color-accent-foreground` (text on accent
backgrounds), plus the global focus ring at `app.css:61` uses `ring-accent`. Task 001 must decide all
three for both themes — replacing only `--color-accent` leaves accent *text* neutral grey while
backgrounds turn amber.

**Contrast gate — resolved.** The originally proposed `#a8672a` measured 3.61:1 on `#f7f2e8` and
3.63:1 on the `#f2e9d6` ticket paper — both fail WCAG AA for normal-size text. **Decision: light
accent darkened to `#8a5220`** (5.08:1 on cream, 6.29:1 white-on-accent, both pass AA). `#e3a742` on
`#1c1712` (dark theme) already passed at 8.25:1 and is unchanged. Because the light accent now passes
AA at normal size, tasks 002–012 may use it for small text/labels, not just large or non-text
elements.

**Uppercase is CSS-only — never change literal Blade strings.** The display face renders uppercase
via the Tailwind `uppercase` utility. Roughly 25 existing Pest assertions match exact-case strings
across `LandingPageTest`, `AppLogoTest`, `DashboardVoltTest`, `HistoryPageTest`,
`RestaurantIndexTest`, `TonightPageTest`, `QuizPageTest`, `QuickPickPageTest` and
`TournamentPageTest` (`Forklore`, `Quick Pick`, `Log in`, `Going`, `June 2025`, `No visits`, `$$$`,
`Step 3 of 5`, …). Rewriting the source text to uppercase breaks all of them for an effect CSS
already provides.

**`resources/css/app.css` has exactly one owner: Task 001.** Tasks 002–012 must not edit it. The
ticket surface is expressible in stock Tailwind — the dashed perforation rule is
`border-t border-dashed border-ticket-line`. Two components adding the same custom class in parallel
is an unresolvable conflict.

**Ticket paper is theme-independent.** The ticket-row and result-ticket components render on a fixed cream/ink "paper" palette (`--color-ticket-bg: #f2e9d6`, `--color-ticket-ink: #221a10`, `--color-ticket-line: #c9b98f` dashed rule, `--color-ticket-accent: #8a5220`) regardless of app light/dark mode — like a physical printed ticket sitting on either a bright or dark counter. Only the page chrome (background, sidebar, header) switches with `.dark`. This keeps the two components simple (no dark-mode branching inside them) and preserves the diner identity's character in both themes.

**Typography line.** `--font-display` (condensed, uppercase, letter-spaced) and `--font-mono-ticket` are used only for: page/section headings, the ticket-row/result-ticket components, and ticket-style metadata (numbers, timestamps, badges). Form labels, input text, settings body copy, and validation/error messages stay on the existing `--font-sans` — do not apply the ticket treatment to anything the user types into or reads as a paragraph.

**Component locations and fixed prop contracts** (anonymous Blade components in
`resources/views/components/`). These signatures are contracts — three tasks consume `ticket-row` and
four consume `restaurant-result-ticket`, all in parallel, so they cannot be narrowed later without
blocking a sibling worker.

```blade
{{-- ticket-row.blade.php --}}
@props([
    'name',                 // string, required — already-resolved display text
    'href' => null,         // string|null — when set the root renders as <a href wire:navigate>
    'badgeLabel' => null,   // string|null — convenience: renders a <flux:badge> in the trailing area
])
{{-- $slot     => metadata area beneath the name; arbitrary markup (tags, dates, price) --}}
{{-- $trailing => optional named slot for the right-hand area; overrides badgeLabel --}}
```

`href` lives in the component because two of three callers link the whole row (Dashboard, Restaurants
index) — without it, the link + hover + focus-ring markup gets re-implemented per caller. The
`trailing` slot exists because Dashboard needs a chevron *icon*, not a badge; the default slot must
accept markup because Restaurants index carries both cuisine tag badges and a price.

```blade
{{-- restaurant-result-ticket.blade.php --}}
@props([
    'restaurant',              // Restaurant model instance
    'badgeLabel',              // string
    'eventLabel' => null,      // string|null — Tonight only; renders BETWEEN badge and name
    'tagline' => null,         // string|null
    'distanceLabel' => null,   // string|null
])
```

`eventLabel` is mandatory to implement: Tonight renders it between the badge and the name, and
`TonightPageTest:61` asserts `assertSeeInOrder(['Trivia starts at 7pm', 'The Tap Room'])`. Render
order is fixed at badge → eventLabel → name → cuisine tags → price/distance → tagline → address.
Price renders as one contiguous `$$$` string (several tests use plain `assertSee('$$$')`). Pure
presentation — no `wire:click` inside; callers keep their own CTA buttons below it. Tournament passes
`:restaurant="$this->winner"`.

**Testing approach.** Page tasks follow the existing pattern (`tests/Feature/*PageTest.php`,
`assertOk()` + `assertSee()`) by **extending the test files that already exist** — see the ownership
table below. The two new components get `$this->blade(...)` render tests; note this is a **new pattern
for this repo** (`$this->blade(` appears nowhere in `tests/` today, and `RestaurantFormFieldsTest.php`
— sometimes cited as the precedent — actually uses `Livewire::test('pages::restaurants.create')`).
Task 002 establishes the pattern; Task 003 mirrors it. Task 001 is no longer untested: it gets a
small `tests/Feature/DesignTokensTest.php` asserting the font `<link>` is served in the page head and
that the tokens are declared in both theme blocks — cheap insurance for a contract eleven tasks
depend on.

## File ownership (parallel-execution safety)

Every task below is the sole owner of the listed files for the duration of this plan. No file appears
in two rows.

| Task | Views / assets owned | Test file owned |
|------|----------------------|-----------------|
| 001 | `resources/css/app.css`, `partials/head.blade.php` | new `DesignTokensTest.php` |
| 002 | new `components/ticket-row.blade.php` | new `TicketRowComponentTest.php` |
| 003 | new `components/restaurant-result-ticket.blade.php` | new `RestaurantResultTicketComponentTest.php` |
| 004 | `welcome.blade.php` | **existing `LandingPageTest.php`** |
| 005 | `layouts/app/sidebar.blade.php`, `components/app-logo.blade.php` | **existing `SidebarTest.php` + `AppLogoTest.php`** |
| 006 | `pages/⚡dashboard.blade.php` | **existing `DashboardVoltTest.php`** |
| 007 | `pages/⚡history.blade.php` | existing `HistoryPageTest.php` |
| 008 | `pages/restaurants/⚡index.blade.php` | existing `RestaurantIndexTest.php` |
| 009 | `pages/⚡pick.blade.php` | existing `QuickPickPageTest.php` |
| 010 | `pages/⚡tonight.blade.php` | existing `TonightPageTest.php` |
| 011 | `pages/⚡quiz.blade.php` | existing `QuizPageTest.php` |
| 012 | `pages/⚡tournament.blade.php` | existing `TournamentPageTest.php` |

`tests/Feature/DashboardTest.php` is owned by **no** task — it holds only two route-guard tests and
both 005 and 006 previously targeted it while running concurrently.

## Risks & Mitigations
- **Contrast/legibility of the mustard accent**: the original `#a8672a` failed AA for normal text on
  both light grounds. Resolved — light accent darkened to `#8a5220` (5.08:1 on cream), passes AA. See
  Architecture Notes.
- **Tailwind v4 drops unused `@theme` variables**: mitigated by `@theme static` in Task 001. Without
  it, a downstream `var(--color-ticket-bg)` reference silently resolves to nothing — no build error,
  no test failure, browser-only.
- **Font weight/availability from bunny.net for Oswald/JetBrains Mono**: expected URL is
  `https://fonts.bunny.net/css?family=instrument-sans:400,500,600|oswald:500,700|jetbrains-mono:400,500&display=swap`
  (lowercase-hyphenated slugs, pipe-joined). Confirm both resolve — a CDN 404 is silent. Fall back to
  the system stack (`"Arial Narrow", sans-serif` / `ui-monospace`) and note it inline if either is
  unavailable; do not add a new font host for one family.
- **Uppercase display type vs. case-sensitive test assertions**: mitigated by the CSS-only uppercase
  rule in Architecture Notes, repeated in every markup task, with each task listing the specific
  strings its existing tests assert.
- **Deleting Alpine behavior by following a line range**: Tasks 009 and 010 had ranges that spanned
  the `x-data` swipe-to-reject wrappers in `⚡pick.blade.php` (191–221) and `⚡tonight.blade.php`
  (126–156). Ranges corrected and both tasks now carry an explicit keep/replace table. No test covers
  the swipe gesture, so a mistake here is browser-only.
- **Parallel tasks touching the same file**: resolved by the ownership table above. Dashboard (006)
  depends on both 001 and 002 since it needs the ticket-row component before its own edit. 007–012
  touch distinct views and distinct test files, safe to run fully in parallel once their single
  dependency lands.
