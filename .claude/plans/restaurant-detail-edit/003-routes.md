# Task 003: Register Routes for Show and Edit

**Status**: completed
**Depends on**: [001]
**Retry count**: 0

## Description
Add two new routes to `routes/restaurants.php`: a Livewire show route and a Livewire edit route. Add route-level tests covering auth guard and 200/403 responses.

Delete is NOT a separate route — it is implemented as a `wire:click="delete()"` Livewire method on the show page (Task 004). There is no `restaurants.destroy` route.

## Context
- Related files: `routes/restaurants.php`, `tests/Feature/RestaurantRoutesTest.php`
- Existing pattern: `Route::livewire('restaurants', 'pages::restaurants.index')->name('restaurants.index')`
- Show: `GET restaurants/{restaurant}` → `pages::restaurants.show` → named `restaurants.show`
- Edit: `GET restaurants/{restaurant}/edit` → `pages::restaurants.edit` → named `restaurants.edit`
- Both routes live inside the existing `auth` middleware group
- **Route ordering is critical**: register `restaurants/create` BEFORE `restaurants/{restaurant}` so the literal `create` segment isn't bound as a restaurant ID. Same applies to `restaurants/{restaurant}/edit` — register after `restaurants/create`.
- **Volt component naming**: use `pages::restaurants.show` and `pages::restaurants.edit`. The `pages::` namespace prefix prevents collision with route names (which are `restaurants.show`, `restaurants.edit`). The existing index page already proves this pattern works.

## Requirements (Test Descriptions)
- [ ] `it redirects guests to login when visiting /restaurants/{id}`
- [ ] `it redirects guests to login when visiting /restaurants/{id}/edit`
- [ ] `it returns 200 for an authenticated owner on the show route`
- [ ] `it returns 200 for an authenticated owner on the edit route`
- [ ] `it returns 403 for a non-owner on the show route`
- [ ] `it returns 403 for a non-owner on the edit route`

## Acceptance Criteria
- All requirements have passing tests
- Named routes `restaurants.show` and `restaurants.edit` resolve correctly
- Route model binding resolves `{restaurant}` to the `Restaurant` model
- `restaurants/create` continues to work (i.e. wildcard route ordering is correct)
- Code follows project standards (run `vendor/bin/pint --dirty --format agent` after edits)

## Implementation Notes
- Use `$this->get(route('restaurants.show', $restaurant))->assertForbidden()` (or `assertStatus(403)`) for the non-owner test — the policy authorizes inside the Volt page's `mount()`, which throws `AuthorizationException` and is rendered as a 403 by Laravel's exception handler.
- Guest-redirect tests should `assertRedirect(route('login'))` (per existing auth middleware behavior).
