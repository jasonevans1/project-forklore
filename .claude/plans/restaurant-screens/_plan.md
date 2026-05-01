# Plan: Restaurant Index and Create Screens

## Created
2026-04-29

## Status
completed

## Objective
Build a mobile-first restaurant index page at `/restaurants` and a create form at `/restaurants/create` using Livewire Volt components and Flux UI, allowing authenticated users to view and manually add their favorite restaurants.

## Related Issues
none

## Scope

### In Scope
- Authenticated route group for `/restaurants` and `/restaurants/create`
- Restaurant index page: card list showing name, cuisine tags, price level; empty state; link to create
- Restaurant create form: all user-editable fields from the migration, single-column layout, large tap targets, sticky bottom submit button, inline validation errors
- Feature tests for list view and create form

### Out of Scope
- Edit / delete restaurant
- Google Places API integration / auto-fill from Places
- Latitude/longitude fields (derived from Places API later, not manually entered)
- `last_visited_at` and `visit_count` (derived, not user-entered)
- Partner visibility (only owner's restaurants shown)

## Success Criteria
- [ ] Unauthenticated users are redirected to login for both routes
- [ ] Authenticated user sees their own restaurants as cards (name, cuisine tags, price level)
- [ ] Empty state shown when user has no restaurants
- [ ] Create form renders all user-editable fields with labels
- [ ] Submitting valid data creates a restaurant owned by the current user and redirects to the index
- [ ] Submitting invalid data shows inline error messages without page reload
- [ ] All tests passing
- [ ] Code follows project standards (Pint clean)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add authenticated restaurant routes + stub Volt components | - | completed |
| 002 | Restaurant index Livewire component | 001 | completed |
| 003 | Restaurant create Livewire form component | 001 | completed |
| 004 | Feature tests — restaurant list (`RestaurantIndexTest.php`) | 002 | completed |
| 005 | Feature tests — restaurant create (`RestaurantCreateTest.php`) | 003 | completed |

## Architecture Notes
- Follow the Volt single-file component pattern used in `resources/views/pages/settings/`
- Routes use `Route::livewire()` as in `routes/settings.php`
- New routes file `routes/restaurants.php` is required from `routes/web.php` (mirrors how `settings.php` is loaded — there is **no** `bootstrap/app.php` change required)
- Volt component aliases: `pages::restaurants.index` → `resources/views/pages/restaurants/⚡index.blade.php`; `pages::restaurants.create` → `resources/views/pages/restaurants/⚡create.blade.php`. The `⚡` prefix and `pages::` alias are wired by `laravel/pao`.
- Both routes use `auth` + `verified` middleware (parity with the `dashboard` route)
- All Volt page templates wrap their content in `<x-layouts::app :title="...">` (the same layout used by `dashboard.blade.php`); without this wrapper, the sidebar/header chrome won't render and the page will be missing `<html>`/`<body>`.
- Use `Restaurant::ownedBy($user)` scope for all queries
- `source` defaults to `RestaurantSource::Favorite` on manual create (not shown to user)
- `cuisine_tags` and `vibe_tags` are stored as JSON arrays; the form accepts a single comma-separated string per field, kept as a `string` Livewire property, and split into an array (`array_filter(array_map('trim', explode(',', $value)))`) only at save time
- Price level rendered as 1–4 dollar signs ($ to $$$$)

### Tests responsibility split (avoid duplication)
- Task 001 only contains route smoke tests (guest redirect + authenticated 200) for both URLs. To make the authenticated 200 tests pass, Task 001 must also write minimal Volt component stub files (empty `class extends Component {}` + a placeholder section). Tasks 002/003 then expand those stubs.
- Tasks 002/003 are component build tasks; they do not own a test file. Their listed "Requirements (Test Descriptions)" are the spec — the actual test code lives in Tasks 004/005 (`RestaurantIndexTest.php` and `RestaurantCreateTest.php`).
- Tasks 004/005 must NOT duplicate Task 001's two route-smoke tests (`guests are redirected...`, `authenticated users can visit...`). Those already live in `tests/Feature/RestaurantRoutesTest.php` from Task 001.

## Risks & Mitigations
- `cuisine_tags`/`vibe_tags` are JSON arrays but the UI needs a simple input: use a plain text field accepting comma-separated values, split on save — keeps the form simple without a custom tag widget
- Sticky bottom button may overlap content on short screens: use `pb-24` padding on the form content area to prevent overlap
- Flux UI 2 has no `<flux:error>` component. Validation errors are rendered automatically inside `<flux:input :label="...">` etc. when a matching `$errors` key is present, OR display them manually via `@error('field') {{ $message }} @enderror`. Do not invent a `<flux:error>` element.
- Livewire SPA navigation: use `$this->redirect(route('restaurants.index'), navigate: true)` from server-side actions; `wire:navigate` is a Blade attribute on `<a>` tags. Test `assertRedirect()` works in both cases because Livewire emits a 200 with a `Livewire-Redirect` header that Pest's HTTP testing follows transparently when invoked via `Livewire::test(...)->assertRedirect(...)`.
