# Plan: Remove Laravel Branding

## Created
2026-06-02

## Status
completed

## Objective
Replace all user-visible "Laravel" branding with "Forklore" branding, remove external Laravel/GitHub sidebar links, and replace the welcome landing page with a Forklore-branded page.

## Related Issues
none

## Discovery Notes
- `app-logo.blade.php` hard-codes "Laravel Starter Kit" in two places (sidebar + non-sidebar variants)
- `sidebar.blade.php` lines 38–46 contain Repository (github.com/laravel/livewire-starter-kit) and Documentation (laravel.com/docs) nav items
- `partials/head.blade.php` and auth layouts (card, simple, split) use `config('app.name', 'Laravel')` fallback — `APP_NAME=Forklore` is already set in `.env` so fallbacks aren't currently shown, but they should be updated for correctness
- `welcome.blade.php` is the public landing page (`/`): contains Laravel SVG logo, "Laravel has an incredibly rich ecosystem", links to laravel.com/docs, laracasts.com, and cloud.laravel.com
- User requested: full Forklore-branded landing page (not just text cleanup)

## Scope

### In Scope
- Update `app-logo.blade.php`: "Laravel Starter Kit" → "Forklore"
- Remove Repository and Documentation items from sidebar
- Update `config('app.name', 'Laravel')` fallbacks to `'Forklore'` in head partial and auth layouts
- Replace `welcome.blade.php` with a Forklore-branded landing page (tagline, login/register CTAs, mobile-first, dark mode)

### Out of Scope
- Replacing the app logo icon/SVG graphic (design asset work)
- Changing auth page copy beyond the app name fallback
- Any backend/config changes (APP_NAME is already set)

## Success Criteria
- [ ] Sidebar shows "Forklore" brand name, no "Laravel Starter Kit"
- [ ] Sidebar has no Repository or Documentation links
- [ ] Welcome page shows Forklore branding and no Laravel mentions
- [ ] All tests passing

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Update app logo and config fallbacks | - | pending |
| 002 | Remove sidebar external links | - | pending |
| 003 | Create Forklore landing page | - | pending |

## Architecture Notes
- All changes are Blade view files only — no PHP logic changes
- Mobile-first constraint applies to the new landing page
- Dark mode support required (use Tailwind dark: variants); do NOT hardcode `class="dark"` — `@fluxAppearance` in the head partial handles theme
- Auth pages use `config('app.name')` via blade; fallback change is defensive only
- **Test rendering context**: the sidebar/app-logo (tasks 001, 002) only render inside the authenticated app layout, reached via authenticated Livewire pages (e.g. `route('dashboard')`). Tests for those tasks must `actingAs` a user and hit `route('dashboard')` — see `tests/Feature/DashboardTest.php`. The welcome page (task 003) is a guest route at `/` with no sidebar.

## Risks & Mitigations
- Welcome page redesign scope creep: keep it simple — brand name, tagline, four mode names, login/register buttons
- Dark mode: ensure new landing page uses `dark:` variants to match existing auth page styling
- **Untestable fallback (task 001)**: the `config('app.name', 'Laravel')` → `'Forklore'` fallback edit cannot be proven by a failing test because `APP_NAME=Forklore` is set and the fallback never renders. Make the edit; rely on the brand-name test for coverage and ensure no regressions.
- **Wrong test target (tasks 001/002)**: a worker that asserts against the guest `/` page would get a false pass (no sidebar there). Tasks now specify authenticating and hitting `route('dashboard')`.
- **Flux scripts on guest page (task 003)**: keep CTAs as plain `<a>` links so `@fluxScripts` isn't required; interactive Flux components would silently fail without it.
