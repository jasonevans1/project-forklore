# Task 004: Rebuild Welcome Page

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Rebuild `welcome.blade.php` against the new diner-ticket tokens as the reference implementation for the rest of the rollout — the marquee wordmark, ticket-style CTA buttons, and the four decision modes presented as numbered menu-ticket rows, per the approved Concept A mockup.

## Context
- Related files: `resources/views/welcome.blade.php`, existing `tests/Feature/LandingPageTest.php`
- Reference mockup: `/private/tmp/claude-501/-Users-jasonevans-projects-project-forklore/12cf1347-f02d-4cd7-9443-0e5499262813/scratchpad/forklore-home-directions.html`, `.concept-a` section — matches this page's exact current copy (wordmark, tagline, four mode descriptions, footer line), so it's a close port, not a redesign.
- Route is `Route::view('/', 'welcome')` in `routes/web.php`.
- **A test file already exists**: `tests/Feature/LandingPageTest.php` (5 tests). Extend it — do NOT
  create a second `WelcomePageTest.php`. Its existing assertions are hard constraints:
  - `assertSee('Forklore')`
  - `assertSee('Log in')` for guests
  - `assertSee('Register')` when registration is enabled
  - `assertSee('Dashboard')` for authenticated users
  - `assertDontSee('Laravel')` — do not let a mockup port introduce the word anywhere
- Keep the existing `@guest`/`@auth` branching and `Route::has(...)` guards intact; only the visual
  treatment changes.
- Sole owner of `welcome.blade.php` and `tests/Feature/LandingPageTest.php` in this plan.
- **Do not edit `resources/css/app.css`** — Task 001 owns it.
- **Never uppercase literal Blade strings.** All five existing assertions above are case-sensitive.
  The condensed display face is rendered uppercase with the CSS `uppercase` utility. Writing
  `FORKLORE` / `LOG IN` / `REGISTER` in the template breaks `LandingPageTest` immediately.

## Requirements (Test Descriptions)
Existing tests in `LandingPageTest.php` must continue to pass unchanged. Add:
- [x] `it lists all four decision modes with their descriptions`
- [x] `it shows the footer tagline`

## Acceptance Criteria
- All requirements have passing tests added to the existing `tests/Feature/LandingPageTest.php`; all
  five pre-existing tests in that file still pass without modification.
- Page uses `--color-page`/`--color-ink`/`--color-accent` tokens (via the generated Tailwind
  utilities `bg-page`, `text-ink`, `text-accent`, …) instead of the current hardcoded
  `bg-white dark:bg-zinc-950` / `text-zinc-*` classes, and `--font-display`/`--font-mono-ticket` for
  headings and metadata.
- Respect Task 001's recorded accent decision — if it restricted `--color-accent` to large/non-text
  usage, do not use it for small body copy here.
- Renders correctly in both light and dark (`@fluxAppearance`) — spot check, no dedicated test needed
  since theme switching isn't page-specific logic. Note this page has no hardcoded `class="dark"` on
  `<html>` (unlike `layouts/app/sidebar.blade.php`), so it renders light until `@fluxAppearance`
  resolves the user's preference. Leave that as-is.
- `resources/css/app.css` is not modified.
- No decrease in test coverage.
- `vendor/bin/pint --dirty --format agent` clean.

## Implementation Notes
- Rebuilt `welcome.blade.php` on `bg-page`/`text-ink`/`bg-accent`/`text-accent-foreground` tokens
  (auto-generated Tailwind utilities from Task 001's `@theme` tokens) in place of the old
  `bg-white dark:bg-zinc-950` / `text-zinc-*` classes.
- Wordmark and headings use `font-display` (uppercase via CSS utility, never uppercased in the
  Blade literal itself, keeping `assertSee('Forklore')` intact). Metadata/footer use
  `font-mono-ticket`.
- Four decision modes rendered as a `@foreach` over an inline array of numbered rows
  (`01`–`04`) with dashed perforation dividers (`divide-dashed divide-ticket-line`), replacing the
  old 2x2 card grid — matches the plan's "numbered menu-ticket rows" framing.
- Copy for the four modes matches CLAUDE.md's canonical descriptions exactly (not the old page's
  paraphrased versions), since the new test asserts the canonical text.
- Did not add a new component; the `<x-ticket-row>` component (Task 002, still in progress) has a
  fixed prop contract for clickable list rows (name/href/badge) that doesn't fit these
  non-clickable, numbered, two-line mode rows, so plain markup was used instead.
- `resources/css/app.css` untouched.
- Full parallel suite: 2 pre-existing failures unrelated to this task's file ownership —
  `TicketRowComponentTest` (Task 002 component not yet built) and a time-based flaky assertion in
  `EventModelTest`. Neither touches `welcome.blade.php` or `LandingPageTest.php`.
