# Task 003: Create Forklore Landing Page

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Replace the Laravel-branded `welcome.blade.php` with a Forklore-branded landing page. The page should communicate what Forklore is, show the four decision modes, and direct visitors to log in or register.

## Context
- Related files:
  - `resources/views/welcome.blade.php` — current Laravel starter kit welcome page; full replacement
  - `resources/views/partials/head.blade.php` — reuse for `<head>` (already included via `@include('partials.head')` in auth layouts; check how to use it or inline a minimal head)
  - Auth layout files for styling reference (dark mode patterns, font classes)
- The current welcome page inlines a large Tailwind CSS bundle in a `<style>` block. The new page should use `@vite` like auth pages, not inline CSS.
- Route: `GET /` maps to `home` named route returning `view('welcome')`
- Design requirements:
  - Mobile-first, dark mode support (`dark:` variants)
  - App name "Forklore" prominently displayed
  - Tagline: "End the 'I don't know, what do you want?' conversation."
  - Brief description of the four modes: Quick Pick, Tonight, Guided Quiz, Tournament
  - Login / Register CTAs (conditional on auth state, matching existing auth page button styles)
  - No mentions of Laravel, Laracasts, or Laravel Cloud
  - Font: Instrument Sans (already loaded via bunny.net in head partial)

## Implementation Notes (IMPORTANT)
- **Reuse the head partial**: use `@include('partials.head')` inside `<head>`. It already provides `<title>`, favicons, fonts, `@vite([...])`, and `@fluxAppearance`. Do NOT inline the Tailwind `<style>` bundle that the current welcome page uses.
- **Dark mode**: do NOT hardcode `class="dark"` on `<html>`. The head partial's `@fluxAppearance` handles theme. Use `dark:` variants on elements so both light and dark render correctly.
- **CTAs must be plain `<a>` links**, not interactive Flux components — the guest landing page does not need `@fluxScripts`. If you use any interactive Flux component you MUST add `@fluxScripts` before `</body>`, so prefer plain anchors styled with Tailwind (match the existing welcome page's anchor classes).
- **Preserve the registration conditional**: the register CTA must stay wrapped in `@if (Route::has('register'))` (and login/register inside `@if (Route::has('login'))`). Registration is currently enabled via Fortify, but the conditional must remain so the page degrades correctly if registration is disabled.
- **Auth-state branching**: show Log in / Register for guests (`@guest`) and a Dashboard link for authenticated users (`@auth`), mirroring the existing welcome page's `@auth`/`@else` structure.
- The route is unchanged: `Route::view('/', 'welcome')->name('home')` already returns `view('welcome')`. No route edits needed.

## Testing Notes (IMPORTANT)
- All assertions hit `GET /` via `route('home')` or `$this->get('/')`.
- Guest tests: no `actingAs`. Assert `assertSee('Log in')` / `assertSee('Sign up'|'Register')`, `assertSee('Forklore')`, and `assertDontSee('Laravel')`.
- Authenticated test: `actingAs(User::factory()->create())` then `$this->get('/')` and `assertSee('Dashboard')`. Note: an authenticated user is NOT redirected from `/` (the route has no `auth` middleware), so the page still renders.
- The `assertDontSee('Laravel')` check covers logo SVG removal, ecosystem copy, and the doc/laracasts/cloud links — verify all are gone.

## Requirements (Test Descriptions)
- [ ] `it shows the Forklore app name on the landing page` (guest, `GET /`)
- [ ] `it shows a login link on the landing page for guests` (guest)
- [ ] `it shows a register link on the landing page when registration is enabled` (guest; registration is on by default)
- [ ] `it shows a dashboard link on the landing page for authenticated users` (`actingAs` a user)
- [ ] `it does not mention Laravel on the landing page` (guest; `assertDontSee('Laravel')`)

## Acceptance Criteria
- All requirements have passing tests
- Code follows project standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
