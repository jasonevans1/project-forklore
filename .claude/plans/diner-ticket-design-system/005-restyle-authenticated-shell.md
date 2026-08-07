# Task 005: Restyle Authenticated Shell

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Apply the diner tokens to the logged-in app shell — the sidebar, its header/logo, and the mobile top bar — so every authenticated page feels like the same app as the rebuilt welcome page, without touching page content.

## Context
- Related files:
  - `resources/views/layouts/app/sidebar.blade.php` — the actual active shell
  - `resources/views/components/app-logo.blade.php`
  - existing `tests/Feature/SidebarTest.php` and `tests/Feature/AppLogoTest.php`
- `resources/views/layouts/app/header.blade.php` is dead/unreferenced starter-kit code and must NOT
  be touched.
- **Test file ownership** — this task extends `tests/Feature/SidebarTest.php` and
  `tests/Feature/AppLogoTest.php`, which already exist and are exactly about the shell.
  **Do not touch `tests/Feature/DashboardTest.php` or `tests/Feature/DashboardVoltTest.php`** —
  Task 006 owns those and runs concurrently with this task. Editing them here causes a merge
  conflict.
- Existing assertions that must keep passing:
  - `AppLogoTest`: `assertSee('Forklore')`, `assertDontSee('Laravel Starter Kit')`
  - `SidebarTest`: `assertDontSee('github.com/laravel/livewire-starter-kit')`,
    `assertDontSee('laravel.com/docs/starter-kits')`
- Patterns to follow: Flux sidebar/header components already read `--color-*` tokens automatically;
  this task is mostly swapping the hardcoded `border-zinc-200`/`bg-zinc-50`/`dark:bg-zinc-900`
  classes (`sidebar.blade.php` lines 6–7) for the new tokens.
- **`x-app-logo` gotcha**: it renders `<flux:sidebar.brand name="Forklore" {{ $attributes }} />` —
  the wordmark is a Flux **prop**, not slot content, so `--font-display` cannot be applied to markup
  inside the component. Pass a font class through `$attributes` from the caller (or add it to the
  component's own attribute merge) and let it inherit. Do not replace `flux:sidebar.brand` with
  hand-rolled markup.
- The hardcoded `<html … class="dark">` lives on **`layouts/app/sidebar.blade.php` line 2** (not
  `layouts/app.blade.php`, which is a 5-line wrapper). Leave it as-is — `@fluxAppearance` in
  `partials/head.blade.php` resolves the real preference at runtime.
- **Do not edit `resources/css/app.css`** — Task 001 owns it.
- **Never uppercase literal Blade strings** — `AppLogoTest` asserts `Forklore` and the sidebar item
  labels are user-visible copy. Use the CSS `uppercase` utility.

## Requirements (Test Descriptions)
Existing tests in `SidebarTest.php` and `AppLogoTest.php` must continue to pass unchanged. Add to
`SidebarTest.php`:
- [x] `it renders all six sidebar navigation items with their routes`

(Dashboard, Quick Pick, Tonight, Quiz, Tournament, History — asserting both the label and
`route(...)` for each. This guards the restyle against accidentally dropping a nav item.)

## Acceptance Criteria
- The new requirement has a passing test in `tests/Feature/SidebarTest.php`; all pre-existing tests
  in `SidebarTest.php` and `AppLogoTest.php` still pass without modification.
- `tests/Feature/DashboardTest.php` and `tests/Feature/DashboardVoltTest.php` are **not** modified.
- `resources/views/layouts/app/sidebar.blade.php` uses `--color-page`/`--color-ink`/`--color-accent`
  (via generated utilities) instead of hardcoded `zinc-*` classes.
- The `x-app-logo` wordmark renders in `--font-display` via attribute pass-through.
- `resources/views/layouts/app/header.blade.php` is not modified.
- `resources/css/app.css` is not modified.
- No literal Blade string is uppercased in source.
- No decrease in test coverage.
- `vendor/bin/pint --dirty --format agent` clean.

## Implementation Notes
- The new regression test (`it renders all six sidebar navigation items with their routes`) passed
  immediately on first run — the six nav items and their routes already existed in
  `sidebar.blade.php`; this test's job is to guard the restyle, not add new behavior, so a
  same-request GREEN is expected (not over-implementation).
- Restyle changes in `resources/views/layouts/app/sidebar.blade.php`:
  - `<body>`: `bg-white dark:bg-zinc-800` → `bg-page`
  - `<flux:sidebar>`: `border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900` →
    `border-ticket-line bg-page`
  - mobile `<flux:header>`: added `border-b border-ticket-line bg-page` (previously unstyled)
  - `<x-app-logo>`: added `class="font-display"`, relying on `$attributes` pass-through in
    `app-logo.blade.php` (already echoes `{{ $attributes }}` on `flux:sidebar.brand`/`flux:brand`)
    and CSS inheritance — Flux's `sidebar/brand.blade.php` merges the class onto the wrapping `<a>`,
    and the wordmark `<div>` inside has no explicit `font-family`, so it inherits `--font-display`.
    No edit to `app-logo.blade.php` was needed.
  - `--color-page`/`--color-ink`/`--color-ticket-line` already had light/dark values defined in
    `resources/css/app.css` (Task 001), including a `.dark` override for `--color-page`/`--color-ink`,
    so no `dark:` prefixes were needed on the new utility classes.
  - `layouts/app/header.blade.php` and `resources/css/app.css` were not touched.
- Full parallel suite: 715 passed / 1 pre-existing unrelated failure
  (`EventModelTest > isActiveNow returns true when the event occurs at the current time`), confirmed
  present on `git stash` (before this task's changes) — a flaky time-boundary test unrelated to this
  task, left as-is per "do not touch other tests" scope.
