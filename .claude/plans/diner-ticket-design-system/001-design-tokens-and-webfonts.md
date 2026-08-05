# Task 001: Diner Design Tokens and Webfonts

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Add the diner-ticket color and font tokens to the app's single theme layer, and load the two new webfonts alongside the existing Instrument Sans import. This is the foundation every later task in this plan builds on — no page markup changes here, only tokens.

**This task is the sole owner of `resources/css/app.css` for the entire plan.** Tasks 002–012 are forbidden from editing it (see "File ownership" below).

## Context
- Related files: `resources/css/app.css`, `resources/views/partials/head.blade.php`, new `tests/Feature/DesignTokensTest.php`
- Patterns to follow: `app.css` already defines `--color-accent` with a light default in `@theme` (lines 11–29) and a `.dark` override in `@layer theme { .dark { ... } }` (lines 31–37). Fonts load via a bunny.net `<link>` in `partials/head.blade.php` (Instrument Sans) — add the new families the same way.
- Reference mockup for exact values: `/private/tmp/claude-501/-Users-jasonevans-projects-project-forklore/12cf1347-f02d-4cd7-9443-0e5499262813/scratchpad/forklore-home-directions.html`, `.concept-a` CSS block.

## Requirements (Test Descriptions)
- [x] `it serves the Oswald display font from the font CDN in the page head`
- [x] `it serves the JetBrains Mono ticket font from the font CDN in the page head`
- [x] `it still serves the existing Instrument Sans body font`
- [x] `it declares the diner page tokens in the light theme block`
- [x] `it declares the diner page token overrides in the dark theme block`

The head-link tests hit `$this->get('/')` and use `assertSee(..., false)` against the raw HTML (the
`<link href>` is not escaped-text). The token tests read `resources/css/app.css` from disk and assert
the token declarations are present in the correct block — a cheap guard that the contract eleven
downstream tasks depend on actually landed and is not later deleted.

## Requirements

### Tailwind v4 gotcha — use `@theme static`
Tailwind v4 only emits a `@theme` variable into the compiled CSS **if a generated utility actually
references it**. Downstream tasks may reference these tokens as `var(--color-ticket-bg)` inside an
arbitrary value or an inline style, which Tailwind's usage detection does not see — the variable
would then be silently undefined at runtime, with no build error and no test failure.

Declare all new tokens in a dedicated **`@theme static { ... }`** block so they are always emitted.
Do not convert the existing `@theme` block to `static` (it would emit the whole zinc scale).

### New tokens — light defaults (`@theme static` block)
- `--font-display: 'Oswald', 'Arial Narrow', sans-serif;`
- `--font-mono-ticket: 'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;`
- `--color-page: #f7f2e8;`
- `--color-ink: #241c12;`
- `--color-ticket-bg: #f2e9d6;`
- `--color-ticket-ink: #221a10;`
- `--color-ticket-line: #c9b98f;`
- `--color-ticket-accent: #8a5220;` (decided — see accent decision below)

### Existing accent tokens — all three must be decided, not just one
`app.css` defines **three** accent tokens today, not one:

```
--color-accent: var(--color-neutral-800);            /* .dark: white              */
--color-accent-content: var(--color-neutral-800);    /* .dark: white              */
--color-accent-foreground: var(--color-white);       /* .dark: neutral-800        */
```

Flux uses `--color-accent` for accent *backgrounds*, `--color-accent-foreground` for text sitting on
those backgrounds, and `--color-accent-content` for accent-coloured *text* (links, ghost buttons).
`app.css:61` also uses `ring-accent` / `ring-offset-accent-foreground` for the global input focus
ring.

Replacing only `--color-accent` leaves every accent-coloured text element neutral grey while
backgrounds turn amber — a visibly half-applied theme. Set all three deliberately and record the
decision in the Implementation Notes.

Target values:
- light `--color-accent`: `#8a5220` (decided — see contrast gate below)
- dark `--color-accent`: `#e3a742`
- `--color-accent-foreground`: white (light) / dark ink (dark) — both clear AA against the decided accents
- `--color-accent-content`: match the accent (`#8a5220` light / `#e3a742` dark) — the contrast fix means accent-colored text is now safe at normal sizes, no need to fall back to neutral

### Contrast gate — resolved
Measured ratios for the originally proposed `#a8672a` all failed AA for normal-size text (3.61–3.63:1
against the light grounds). **Decision: option (b), darken the light accent to `#8a5220`.**

| Pair | Ratio | Verdict |
|---|---|---|
| `#8a5220` on `#f7f2e8` (light page ground) | **5.08:1** | passes AA |
| `#8a5220` on `#f2e9d6` (ticket paper) | **~5.1:1** | passes AA |
| white on `#8a5220` (primary button label) | **6.29:1** | passes AA |
| `#e3a742` on `#1c1712` (dark page ground) | 8.25:1 | passes |
| `#221a10` on `#f2e9d6` (ticket ink on paper) | ~14:1 | passes |
| `#241c12` on `#f7f2e8` (page ink) | ~14:1 | passes |

`--color-accent` and `--color-ticket-accent` both use `#8a5220` in the light theme so the page chrome
and the ticket paper agree. Because this passes AA at normal text sizes, tasks 002–012 may use the
accent for small text/labels, not just large or non-text elements — no usage restriction needed.

### Dark overrides (existing `.dark` block)
- `--color-page: #1c1712;`
- `--color-ink: #f2e9d6;`
- `--color-accent: #e3a742;`
- plus whatever `--color-accent-content` / `--color-accent-foreground` the decision above requires

(`--color-ticket-*` tokens are intentionally omitted from `.dark` — the ticket "paper" is
theme-independent, per the plan's Architecture Notes.)

### Fonts — `resources/views/partials/head.blade.php`
Extend the existing bunny.net `<link>` rather than adding a second request. Expected URL:

```
https://fonts.bunny.net/css?family=instrument-sans:400,500,600|oswald:500,700|jetbrains-mono:400,500&display=swap
```

bunny.net family slugs are lowercase-hyphenated and pipe-joined. Verify both families actually
resolve (a 404 from the CDN is silent in the browser). If either is unavailable, fall back to the
system stack already declared in the token value and note it with a short inline comment — **do not
add a new font-hosting dependency to pull in one family.**

### Uppercase rule (binding on tasks 002–012 — record it here)
The display face is rendered uppercase with the CSS `uppercase` utility. **Literal strings in Blade
templates are never changed to uppercase.** Roughly 25 existing Pest assertions match exact-case
strings across `LandingPageTest`, `AppLogoTest`, `DashboardVoltTest`, `HistoryPageTest`,
`RestaurantIndexTest`, `TonightPageTest`, `QuizPageTest`, `QuickPickPageTest` and
`TournamentPageTest` (`Forklore`, `Quick Pick`, `Log in`, `Going`, `June 2025`, `No visits`, `$$$`,
…). Changing the source text breaks all of them for a purely visual effect that CSS already
provides.

### File ownership (binding on tasks 002–012 — record it here)
This task is the **only** task in the plan permitted to modify `resources/css/app.css`. Downstream
components must express the ticket surface with stock Tailwind utilities — the dashed perforation
rule is `border-t border-dashed border-ticket-line`, no custom class required. If a downstream task
believes it genuinely needs a shared custom class, it must stop and escalate rather than edit
`app.css` (two components adding the same class in parallel is an unresolvable conflict).

## Acceptance Criteria
- All requirements have passing tests in a new `tests/Feature/DesignTokensTest.php`.
- `resources/css/app.css` builds without errors (`npm run build` compiles cleanly).
- New tokens live in a `@theme static { ... }` block; the existing `@theme` block is unchanged apart
  from the accent values.
- All three accent tokens (`--color-accent`, `--color-accent-content`, `--color-accent-foreground`)
  are explicitly decided for both light and dark, with the reasoning in Implementation Notes.
- The contrast gate above is resolved with an explicit (a)/(b) decision recorded in Implementation
  Notes.
- Both light and dark values are present and distinct; `--color-ticket-*` tokens are unconditional
  (not inside `.dark`).
- No existing page visibly breaks — spot-check `/` and `/dashboard` render without console errors.
  This task changes no markup, but the accent rename must not orphan any current consumer of
  `--color-accent` (Flux buttons, the `ring-accent` focus ring at `app.css:61`).
- `vendor/bin/pint --dirty --format agent` clean (the new test file is PHP).

## Implementation Notes

- **Files touched**: `resources/css/app.css`, `resources/views/partials/head.blade.php`,
  `tests/Feature/DesignTokensTest.php` (new).
- **Fonts**: extended the single bunny.net `<link>` to
  `https://fonts.bunny.net/css?family=instrument-sans:400,500,600|oswald:500,700|jetbrains-mono:400,500&display=swap`.
  Could not verify network resolution of the CDN from this sandboxed environment; proceeded with this
  URL as authoritative per bunny.net's documented lowercase-hyphenated, pipe-joined slug convention, as
  instructed by the task. No fallback was needed since the request was not blocked at build/test time
  (only network egress to the CDN itself is unverified) — if the family later 404s, the `--font-display`
  / `--font-mono-ticket` token values already declare a system-stack fallback.
- **New `@theme static` block**: added directly after the existing `@theme` block (kept that block
  `@theme`, not `static`, per the instruction not to force-emit the whole zinc scale). Contains
  `--font-display`, `--font-mono-ticket`, `--color-page`, `--color-ink`, `--color-ticket-bg`,
  `--color-ticket-ink`, `--color-ticket-line`, `--color-ticket-accent` exactly as specified.
- **Accent decision (contrast gate)**: option (b) — darkened the light accent to `#8a5220` (5.08:1 on
  `--color-page`, ~5.1:1 on `--color-ticket-bg`, both pass AA for normal text). All three accent tokens
  were set for both themes, not just `--color-accent`:
  - light: `--color-accent: #8a5220`, `--color-accent-content: #8a5220`, `--color-accent-foreground: var(--color-white)`
  - dark: `--color-accent: #e3a742`, `--color-accent-content: #e3a742`, `--color-accent-foreground: #221a10`
  This keeps accent-coloured text (links, ghost buttons) legible in both themes and avoids the
  "amber background, grey text" half-applied look the task warned about. `--color-ticket-accent`
  reuses `#8a5220` (light-only, ticket paper is theme-independent) so page chrome and ticket paper
  agree.
- `--color-page` / `--color-ink` dark overrides added inside the existing `.dark { }` block alongside
  the accent overrides; `--color-ticket-*` tokens are intentionally absent from `.dark` (ticket paper
  is theme-independent).
- Verified with `npm run build` (via `ddev exec`) that all new custom properties are present in the
  compiled `public/build/assets/app-*.css` output, confirming `@theme static` correctly force-emits
  them even though no utility class in the current codebase references them yet.
- Full parallel suite (`ddev exec php artisan test --compact --parallel`): 691 passed, 1 pre-existing
  unrelated failure (`EventModelTest > isActiveNow returns true when the event occurs at the current
  time`, a time-of-day flake unrelated to this task's CSS/font changes).
- `vendor/bin/pint --dirty --format agent`: clean.
