# Task 001: Add Restaurant Routes + Stub Volt Components

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create a new `routes/restaurants.php` file with authenticated, verified routes for the restaurant index and create pages. Require the file from `routes/web.php` (same pattern used for `routes/settings.php` — `bootstrap/app.php` does NOT need to change). Also create minimal Volt component stub files at `resources/views/pages/restaurants/⚡index.blade.php` and `resources/views/pages/restaurants/⚡create.blade.php` so route hits return 200. Tasks 002 and 003 will replace those stubs with the real implementations.

## Context
- Related files: `routes/web.php`, `routes/settings.php` (route registration pattern), `resources/views/pages/settings/⚡appearance.blade.php` (minimal Volt stub example), `resources/views/dashboard.blade.php` (layout wrapper example)
- Patterns to follow:
  - `routes/settings.php` uses `Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit')`
  - `routes/web.php` requires `settings.php` at the bottom with `require __DIR__.'/settings.php';`
  - Volt alias `pages::restaurants.index` maps to `resources/views/pages/restaurants/⚡index.blade.php` (the `⚡` prefix and `pages::` alias are wired by `laravel/pao`)
- Route names: `restaurants.index`, `restaurants.create`
- Both routes require `auth` AND `verified` middleware (matches `dashboard` route in `routes/web.php`)
- Stub Volt component template body must wrap content in `<x-layouts::app :title="...">` so the page renders inside the application chrome (sidebar + html shell). Mirror the structure of `resources/views/pages/settings/⚡appearance.blade.php`.

## Requirements (Test Descriptions)
Tests live in `tests/Feature/RestaurantRoutesTest.php`. Other test files (Task 004/005) MUST NOT duplicate these.
- [ ] `guests are redirected to login when visiting /restaurants`
- [ ] `guests are redirected to login when visiting /restaurants/create`
- [ ] `authenticated verified users receive 200 from /restaurants`
- [ ] `authenticated verified users receive 200 from /restaurants/create`
- [ ] `unverified users are redirected from /restaurants` (parity with `dashboard` middleware)

## Acceptance Criteria
- All requirements have passing tests in `tests/Feature/RestaurantRoutesTest.php`
- `php artisan route:list --name=restaurants` shows `restaurants.index` and `restaurants.create` with `auth,verified` middleware
- Both Volt stub files exist and render `<x-layouts::app :title="$title">{{ slot content }}</x-layouts::app>` with at least one identifying element (e.g., `<flux:heading>Restaurants</flux:heading>`) so 002/003 can confirm they are replacing the stub.
- Pint clean (`vendor/bin/pint --dirty --format agent`)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
