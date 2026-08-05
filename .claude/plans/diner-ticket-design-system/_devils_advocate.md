# Devil's Advocate Review: diner-ticket-design-system

Reviewed against the actual source files (`resources/css/app.css`, `partials/head.blade.php`,
the seven consuming views, and the existing Pest suite in `tests/Feature/`).

---

## Critical (Must fix before building)

### C1 — Task 004 duplicates an existing test file
Task 004 states *"No test file exists yet for this route — create one"* and proposes
`tests/Feature/WelcomePageTest.php`. **`tests/Feature/LandingPageTest.php` already exists** and
already covers 4 of the 4 proposed tests (`Forklore` visible, `Log in`, `Register`,
`Dashboard` for auth users) plus `assertDontSee('Laravel')`.

Creating a second file duplicates coverage and, worse, the worker will never read the existing
one — so the `assertDontSee('Laravel')` guard and the exact-case string expectations are invisible
to them.

**Fix:** point Task 004 at `tests/Feature/LandingPageTest.php`, extend it, and list the existing
assertions as constraints the rebuild must not break.

### C2 — Tasks 005 and 006 collide on `tests/Feature/DashboardTest.php`
Task 005 says *"updates to the existing `tests/Feature/DashboardTest.php`"*; Task 006 says
*"extending the existing `tests/Feature/DashboardTest.php`"*. 005 depends on `001`; 006 depends on
`001, 002` — they can and will run concurrently. Two workers editing the same test file with no
coordination is a guaranteed merge conflict.

Separately, both are pointed at the wrong file: `DashboardTest.php` contains only two route-guard
tests. The mode-card / recently-added assertions live in **`tests/Feature/DashboardVoltTest.php`**,
and the shell already has dedicated tests in **`tests/Feature/SidebarTest.php`** and
**`tests/Feature/AppLogoTest.php`**.

**Fix:** Task 005 owns `SidebarTest.php` + `AppLogoTest.php`. Task 006 owns `DashboardVoltTest.php`.
Neither touches `DashboardTest.php`.

### C3 — Tailwind v4 strips unused `@theme` variables
In Tailwind v4, theme variables declared in `@theme` are only emitted into the compiled CSS **if
they are actually referenced by a generated utility**. `--color-accent` survives today only because
Flux's own stylesheet references it.

The plan's Architecture Notes and Tasks 002/003 describe consuming `--color-ticket-bg`,
`--color-ticket-ink`, `--color-ticket-line`, `--color-ticket-accent`, `--font-mono-ticket` — and a
worker writing `style="background: var(--color-ticket-bg)"` or `bg-[var(--color-ticket-bg)]` in a
Blade file will get an **undefined variable at runtime** with no build error and no test failure.
It fails silently, only visible in a browser. Tasks 002 and 003 are built by different workers from
Task 001, so nobody will catch it.

**Fix:** Task 001 declares the new tokens inside a `@theme static { ... }` block so they are always
emitted regardless of usage detection.

### C4 — `resources/css/app.css` has no declared single owner for tasks 002–012
The plan's Architecture Notes cover file collisions for the Blade views but not for the stylesheet.
The design calls for a dashed perforation rule on both new components; the obvious implementer move
is "add a `.ticket-rule` class to `app.css`" — done independently by both the Task 002 and Task 003
workers, in parallel, in the same file.

**Fix:** Task 001 is the *sole* owner of `resources/css/app.css`. Tasks 002–012 are explicitly
forbidden from editing it and must express the ticket surface with stock Tailwind utilities
(`border-t border-dashed border-ticket-line bg-ticket-bg`), which cover the dashed rule natively —
no custom class needed.

### C5 — Uppercase display type will break ~25 existing case-sensitive assertions
The design applies a condensed **uppercase** display font to headings site-wide. If any worker
implements that by changing the literal Blade string (`Forklore` → `FORKLORE`, `Quick Pick` →
`QUICK PICK`), these existing tests break:

- `LandingPageTest`: `Forklore`, `Log in`, `Register`, `Dashboard`
- `AppLogoTest`: `Forklore`
- `DashboardVoltTest`: `Quick Pick`, `Tonight`, `Guided Quiz`, `Tournament`,
  `"You haven't added any restaurants yet."`
- `HistoryPageTest`: `No visits`, `June 2025`, `May 2025`, `Quick Pick`, `Mar`
- `RestaurantIndexTest`: `No restaurants yet`, `$$$`
- `TonightPageTest`: `What's happening`, `Going`, `Not this one`, `Nothing happening`
- `QuizPageTest` / `QuickPickPageTest` / `TournamentPageTest`: `Going`, `Start over`,
  `No matches found`, `Not enough favorites`, `The Champion`, …

**Fix:** hard rule in Task 001's Architecture Notes and repeated in every markup task — uppercase is
applied with the CSS `uppercase` utility only. **Never** change a literal string in Blade.

### C6 — `<x-ticket-row>` contract is too vague for 006/007/008 to build against in parallel
Task 002 specifies "props for name, optional badge label, and optional metadata text/slot". The
three real callers need materially different things:

| Caller | name | meta | trailing | clickable |
|---|---|---|---|---|
| History (`⚡history.blade.php` 59–81) | `$visit->restaurant?->name ?? 'Unknown restaurant'` | `visited_at->format('M j')` | `<flux:badge>` mode label | no |
| Dashboard Recently Added (`⚡dashboard.blade.php` 97–110) | `$restaurant->name` | `implode(', ', cuisine_tags)` | `<flux:icon name="chevron-right">` | yes, `restaurants.show` |
| Restaurants index (`restaurants/⚡index.blade.php` 43–67) | `$restaurant->name` | cuisine tag **badges** + `str_repeat('$', price_level)` | none | yes, `restaurants.show` |

A single `badgeLabel` string can't render the dashboard's chevron icon. A single meta *string* can't
render the index's badge row plus price. And "callers wrap it in their own `<a>`" means two of three
callers re-implement the link + hover + focus-ring markup the component exists to de-duplicate.

Whoever picks up 006 or 008 first will discover 002's component doesn't fit and either patch 002
(racing the other consumers) or fork the markup (defeating the plan's own success criterion).

**Fix:** pin the exact signature in Task 002 and repeat it verbatim in 006/007/008 so all four
workers build against the same contract:

```blade
@props([
    'name',
    'href' => null,        // when set, root renders as <a href wire:navigate>
    'badgeLabel' => null,  // convenience: renders a <flux:badge> in the trailing area
])
{{-- $slot    => metadata area under the name (arbitrary markup) --}}
{{-- $trailing => optional right-hand slot; overrides badgeLabel --}}
```

### C7 — `<x-restaurant-result-ticket>` has no slot for Tonight's event label, and an existing test asserts its *order*
`⚡tonight.blade.php` renders `$eventLabel` **between the badge and the restaurant name** (lines
163–168). Task 003's prop list (`restaurant`, `badgeLabel`, `tagline`, `distanceLabel`) has no place
for it, and Task 010's requirements never mention it.

`tests/Feature/TonightPageTest.php:61` asserts
`assertSeeInOrder(['Trivia starts at 7pm', 'The Tap Room'])`. If Task 010's worker renders the event
label *after* the component this test fails; if they drop it, two tests fail. Either way Task 010 is
blocked behind an edit to Task 003's component, which by then is already consumed by 009/011/012.

**Fix:** add an optional `eventLabel` prop to Task 003 rendered between badge and heading, with its
own test, and add the order requirement to Task 010.

---

## Important (Should fix before building)

### I1 — Task 001 ignores `--color-accent-content` and `--color-accent-foreground`
`app.css` lines 26–28 and 33–35 define three accent tokens, not one. Task 001 only replaces
`--color-accent`. Left alone, `--color-accent-content` stays `neutral-800` (light) / `white` (dark),
so every Flux accent-coloured *text* element (links, ghost buttons) stays neutral grey while
backgrounds go amber — an obviously half-applied theme. Task 001 must make an explicit decision on
all three.

### I2 — The stated contrast claim is false; light accent fails WCAG AA for normal text
The plan comments `--color-accent: #a8672a; /* deep amber, AA on cream */`. Measured:

| Pair | Ratio | Verdict |
|---|---|---|
| `#a8672a` on `#f7f2e8` (page, light) | **3.61:1** | fails AA normal text (passes AA-large / non-text) |
| `#a8672a` on `#f2e9d6` (ticket paper) | **3.63:1** | fails AA normal text |
| white on `#a8672a` (primary button label) | **4.50:1** | exactly at the AA threshold — no margin |
| `#e3a742` on `#1c1712` (dark) | 8.25:1 | passes |
| `#221a10` on `#f2e9d6` (ticket ink) | ~14:1 | passes |

The Risks section says "verify both meet WCAG AA … before finalizing in task 001", but Task 001's
Requirements and Acceptance Criteria contain no such check, so it will be skipped. Since this ships
site-wide including the focus ring (`app.css:61` uses `ring-accent`), it needs a concrete decision,
not a note.

**Fix:** Task 001 gets an explicit acceptance criterion with these numbers, and either restricts
`#a8672a` to large/bold text + non-text usage, or adopts a darker light accent
(`#8a5220` ≈ 5.1:1 on cream, 6.3:1 white-on-accent).

### I3 — Task 001 is the one untested task, and eleven tasks depend on it
Everything downstream assumes tokens and font links exist. There is a cheap, non-tautological guard:
the font `<link>` lives in `partials/head.blade.php`, a shared file that any later task could
regress, and it is trivially assertable over an HTTP response. "Verify via Pint/manual QA" is not an
acceptable exception when 11 tasks build on the result — but a full CSS-unit-testing convention
would be over-engineering.

**Fix:** one small `tests/Feature/DesignTokensTest.php` — assert the landing page head serves both
new font families, and assert `app.css` declares the new tokens in both the light and `.dark` blocks.

### I4 — Tasks 002 and 003 cite a `$this->blade(...)` precedent that does not exist
Both say the component tests should follow `tests/Feature/RestaurantFormFieldsTest.php`, "how that
component is tested via `$this->blade(...)`". That file uses
`Livewire::test('pages::restaurants.create')` against the real page. Grepping the whole suite,
**`$this->blade(` appears zero times**. A worker who opens the referenced file for the pattern will
find nothing resembling the instruction and will guess.

**Fix:** state plainly that `$this->blade(...)` (Laravel's `InteractsWithViews`) is a *new* pattern
for this repo, and that it is the intended approach — with a concrete usage example so all component
tests come out consistent.

### I5 — Line ranges in Tasks 009 and 010 would delete the swipe-to-reject Alpine wrapper
Task 009 says "the informational block … roughly lines 190–225". Line 190 is the `@if`, and lines
191–221 are the `x-data` Alpine block implementing swipe-left-to-reject (`$wire.reject()`). The
informational markup is actually **224–267**. Task 010's stated 125–199 has the same problem — the
Alpine wrapper is 126–156, the informational block is **159–195**.

A worker taking the range literally rips out the swipe gesture. No test covers it (it's Alpine), so
it fails silently in production.

**Fix:** correct both ranges and add an explicit "do not remove or modify the `x-data` swipe
wrapper, the swipe hint, or the CTA group" guardrail.

### I6 — Task 009 omits the conditional "Save as favorite" CTA
`⚡pick.blade.php` 280–288 renders an extra button when
`$this->restaurant->source === RestaurantSource::Places`. Task 009's requirements list only
Going/reject/runner-up. Since 009's worker is editing right next to this block, it needs a named
guard and a regression test.

### I7 — Task 007 drops the deleted-restaurant fallback
`⚡history.blade.php:64` is `$visit->restaurant?->name ?? __('Unknown restaurant')`. Task 007's
requirements never mention it, and because `<x-ticket-row>` takes a resolved string, the null-safe
resolution has to move into the caller's `@foreach`. Easy to lose; add it as a requirement + test.

### I8 — Task 008 must keep price rendering as one contiguous string
`tests/Feature/RestaurantIndexTest.php:50` asserts `assertSee('$$$')`. If the ticket redesign renders
price as per-glyph spans (a plausible "ticket" flourish) the assertion breaks even though the page
looks right. Call it out.

### I9 — Task 011's replacement range contradicts its own instruction
Task 011 says the `skippedStepsMessage` block stays outside the component, but gives the range
"638–687", which *includes* it (`⚡quiz.blade.php` 682–686). The informational block to replace is
**640–681**.

### I10 — Task 012 prop-name and address-position mismatch
Tournament uses `$this->winner`, not `$this->restaurant`, and renders `address` **inline in the meta
row** next to price (`⚡tournament.blade.php` 308–316), whereas `<x-restaurant-result-ticket>` renders
it on its own line. The shape change is acceptable but should be stated so it isn't reported as a
bug, and so the worker knows to pass `:restaurant="$this->winner"`.

### I11 — Task 005 has two factual errors about the shell
- It says `layouts/app.blade.php` carries the hardcoded `<html class="dark">`. That file is 5 lines
  and carries no such thing — the hardcoded `class="dark"` is on
  `layouts/app/sidebar.blade.php:2`.
- `x-app-logo` renders `<flux:sidebar.brand name="Forklore" {{ $attributes }} />`. The wordmark is a
  Flux *prop*, not slot content, so `--font-display` cannot be applied to markup inside the
  component — it must be passed through `$attributes` as a class from the caller and inherited.

### I12 — Task 011 needs harder guardrails on the 755-line quiz file
The current wording ("touch only the result block") is a suggestion, not a fence. `⚡quiz.blade.php`
has ~1,090 lines of tests behind it including exact `Step N of M` progress strings, seven
`quiz.steps.*` sub-components, and `loosenFilter` empty-state wiring. Enumerate the forbidden
regions by name so the worker can self-check.

---

## Minor (Nice to address)

- **Light/dark default is inconsistent between shells.** `layouts/app/sidebar.blade.php` hardcodes
  `<html class="dark">`; `welcome.blade.php` has no default class. So a first-time visitor sees a
  light landing page and a dark app until `@fluxAppearance` resolves. Pre-existing, but the new
  theme makes it far more visible (cream vs. ink ground).
- **Token names produce awkward utilities.** `--color-ticket-bg` → `bg-ticket-bg`,
  `--color-ticket-ink` → `text-ticket-ink`. Consider `--color-ticket`/`--color-ticket-fg` for
  `bg-ticket` / `text-ticket-fg`.
- **bunny.net URL format.** Family slugs are lowercase-hyphenated and pipe-joined; the expected URL
  is `https://fonts.bunny.net/css?family=instrument-sans:400,500,600|oswald:500,700|jetbrains-mono:400,500`.
  Worth adding `&display=swap` to avoid FOIT on the condensed display face.
- **No visual regression coverage.** Nothing in the plan catches "renders but looks wrong". Pest 4
  has browser testing available; a single smoke test on the welcome page would be cheap, but is
  arguably out of scope for a styling plan.
- **`--color-ticket-*` only in the light block** means the ticket card is a fixed cream panel on a
  near-black page in dark mode. Deliberate per the plan, but it is the single biggest visual risk in
  the design and there is no task where anyone is asked to look at it and confirm.

---

## Questions for the Team

1. **Is the mustard accent allowed to fail AA for normal-size text?** #a8672a measures 3.6:1 on both
   the cream page and the ticket paper. Options: (a) keep the hue and restrict accent to
   large/bold/non-text only, (b) darken to ~#8a5220 for the light theme, (c) accept AA-large only.
   This is a brand decision, not a code one — I added the check and the numbers to Task 001 but did
   not change the approved colour.
2. **Should `--color-accent-content` become amber too?** That makes every Flux accent text element
   amber-on-cream at 3.6:1. Coupled to Q1.
3. **Cream ticket on a dark page — confirmed?** The Architecture Notes assert it, but nobody has seen
   it rendered. If it looks wrong, the cheapest fix is a `.dark` override of `--color-ticket-*`,
   which contradicts "no dark-mode branching inside the components".
4. **Is the landing page still allowed to default to light while the app defaults to dark?**
   (See Minor #1.)
5. **Does the tournament result losing its inline `price · address` meta row matter?** The shared
   component puts address on its own line. (See I10.)
