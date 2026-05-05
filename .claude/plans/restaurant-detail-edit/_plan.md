# Plan: Restaurant Detail and Edit Screens

## Created
2026-05-04

## Status
completed

## Objective
Build a detail view at `/restaurants/{id}` and an edit form at `/restaurants/{id}/edit`, reusing extracted form-field partial from the create page, with a `RestaurantPolicy` enforcing ownership on view/update/delete operations. Delete is a Livewire method on the show page (no separate destroy route).

## Related Issues
none

## Scope

### In Scope
- `RestaurantPolicy` (view, update, delete) registered via Laravel's automatic policy discovery
- Anonymous Blade partial extracted from create form so edit and create share identical fields
- Detail/show page: editable fields, `visit_count`, `last_visited_at`, edit + delete CTA buttons in thumb zone
- Edit page: pre-populated form using the shared partial, saves changes, redirects to detail
- Delete action on the show page: confirms via `flux:modal`, deletes, redirects to index
- Route additions: `restaurants.show`, `restaurants.edit` (delete is NOT a separate route)
- Feature tests covering each page's happy path, validation, and 403 for non-owners

### Out of Scope
- Inline editing
- Visit logging / incrementing `visit_count`
- Search or filtering on the index page
- Google Places data enrichment
- Soft-delete (hard-delete for now; revisit when Visit model is fleshed out)

## Success Criteria
- [ ] Authenticated owner can view their restaurant detail
- [ ] Authenticated owner can edit and save all fields
- [ ] Authenticated owner can delete their restaurant (confirms via modal)
- [ ] Non-owner receives 403 on show, edit, and delete invocation
- [ ] Unauthenticated users are redirected to login
- [ ] Create and Edit forms share the same field partial with no duplication
- [ ] All tests passing
- [ ] Code follows project standards (Pint clean)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | RestaurantPolicy – ownership authorization | none | completed |
| 002 | Extract restaurant form fields into a Blade partial | none | completed |
| 003 | Register routes for show and edit | 001 | completed |
| 004 | Detail (show) page with delete action | 001, 002, 003 | completed |
| 005 | Edit page with pre-populated form | 001, 002, 003 | completed |

## Architecture Notes
- Pages use Volt-style inline Livewire classes in `resources/views/pages/restaurants/⚡{name}.blade.php`
- Routes declared in `routes/restaurants.php` using `Route::livewire()` for show/edit. There is NO `restaurants.destroy` route — delete is a `wire:click="delete()"` Livewire method on the show component.
- Anonymous Blade partial lives in `resources/views/components/restaurants/form-fields.blade.php` (no class needed). Anonymous components do not inherit `use` imports from the calling Volt file — enums must be FQCN-referenced or imported via `@php use ... @endphp`.
- Policy auto-discovered by Laravel; no manual registration required in Laravel 13.
- Volt component names use the `pages::` namespace prefix (e.g. `pages::restaurants.show`); route names omit it (e.g. `restaurants.show`). The `::` separator avoids collision — verified in the existing index page setup.
- Authorization is checked twice on edit/delete: once at `mount()` (load) and once again in the action method (`save()`/`delete()`). For Livewire-method auth tests, expect `AuthorizationException` rather than HTTP 403.
- Route ordering: register `restaurants/create` BEFORE `restaurants/{restaurant}` (and `restaurants/{restaurant}/edit`) so the `create` literal isn't bound as a model ID.
- `wire:navigate` is used on all internal links (index → show → edit and back) to match the existing index page UX.
- Tags displayed as comma-joined strings on the edit form (same pre-processing reverse of create); Flux badges on the show page.

## Risks & Mitigations
- (Resolved) `Route::livewire()` only registers GET; we don't need a DELETE route since delete is a Livewire method.
- Anonymous Blade component scope: enum imports won't carry from the parent Volt file — handled by FQCN in the partial.
- Authorization check timing: tests must distinguish initial-load 403 (HTTP) from Livewire-method `AuthorizationException` (PHP exception).
