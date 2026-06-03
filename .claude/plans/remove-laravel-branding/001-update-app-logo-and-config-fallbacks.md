# Task 001: Update App Logo and Config Fallbacks

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Replace "Laravel Starter Kit" brand name in the app logo component with "Forklore", and update the `'Laravel'` fallback string in the head partial and auth layout files to `'Forklore'`.

## Context
- Related files:
  - `resources/views/components/app-logo.blade.php` — contains two `flux:sidebar.brand` / `flux:brand` components with `name="Laravel Starter Kit"`
  - `resources/views/partials/head.blade.php` — uses `config('app.name', 'Laravel')` in `<title>`
  - `resources/views/layouts/auth/card.blade.php` — uses `config('app.name', 'Laravel')` in sr-only span
  - `resources/views/layouts/auth/simple.blade.php` — uses `config('app.name', 'Laravel')` in sr-only span
  - `resources/views/layouts/auth/split.blade.php` — uses `config('app.name', 'Laravel')` in two places
- The `APP_NAME=Forklore` is already set in `.env`, so the fallback changes are defensive but correct to update

## Testing Notes (IMPORTANT)
- The `app-logo.blade.php` brand only renders inside the **authenticated app layout** (`layouts/app/sidebar.blade.php`), which is rendered by Livewire pages like the dashboard. It does NOT render on the guest welcome page.
- Tests must authenticate a user and hit an authenticated route that renders the sidebar. Follow the existing pattern in `tests/Feature/DashboardTest.php`:
  ```php
  $user = User::factory()->create();
  $this->actingAs($user);
  $response = $this->get(route('dashboard'));
  $response->assertOk();
  ```
  Then assert against the rendered HTML. Do NOT render `<x-app-logo>` in isolation — Flux sidebar/brand components require the surrounding layout context.
- The `config('app.name', 'Laravel')` fallback change in `head.blade.php` and the auth layouts is **defensive only and is NOT independently testable**: `APP_NAME=Forklore` is set, so the fallback string never renders. Do not attempt to write a failing test for the fallback value — make the edit, and rely on the brand-name test for coverage. Verify no test regresses.

## Requirements (Test Descriptions)
- [ ] `it displays Forklore as the sidebar brand name` (authenticated, via `route('dashboard')`)
- [ ] `it does not display Laravel Starter Kit anywhere in the authenticated layout` (authenticated, via `route('dashboard')`; use `assertDontSee('Laravel Starter Kit')`)

## Acceptance Criteria
- All requirements have passing tests
- Code follows project standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
