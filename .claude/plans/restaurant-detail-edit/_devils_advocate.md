# Devil's Advocate Review: restaurant-detail-edit

## Critical (Must fix before building)

### C1. Task 003 specifies `Route::delete()` but the show page is still wired by `Route::livewire()` (GET only)
`Route::livewire()` only registers `Route::get()` (verified in `vendor/livewire/livewire/src/Mechanisms/HandleRouting/HandleRouting.php`). The plan says "Destroy: `DELETE restaurants/{restaurant}` — handled as a Livewire action on the show page" while *also* saying the route can be `Route::delete()` pointing to a controller "OR" handled via `wire:click`. These are incompatible — pick one.

The chosen approach (per `_plan.md`: "use `wire:click="delete()"` so no separate controller is needed") means **there is no `DELETE` route at all** — the action is a Livewire method call hitting the standard `/livewire/update` endpoint. The task description, however, asks the worker to register a destroy route AND to test that "named routes `restaurants.show` and `restaurants.edit` resolve correctly" — note `restaurants.destroy` is missing from that bullet, signalling the route shouldn't exist, but the task title says "Register Routes for Show, Edit, **and Destroy**".

Affected tasks: 003, 004.

**Fix**: Update Task 003 to register *only* show + edit routes. Remove all references to a destroy route. Update Task 004 description so the delete CTA is unambiguously a `wire:click` Livewire method, with no link to any `restaurants.destroy` route. Update `_plan.md` Task table description to "Register routes for show and edit". Remove the "Risks & Mitigations" bullet about `Route::delete()` conflicting with `Route::livewire()` since we're not using `Route::delete()`.

### C2. Volt component name `pages::restaurants.show` collides with route name `restaurants.show`
The plan flags this as a risk in `_plan.md` but never resolves it. Livewire components are registered under the namespace `pages::` (the `::` separator avoids collision with route names). Verified: existing routes use `pages::restaurants.index` as component name + `restaurants.index` as route name and they coexist fine. So **this is actually a non-issue** — the risk note is wrong. But the framing in task 003 ("use distinct Volt component name and verify no conflict") will make a worker waste cycles renaming things.

Affected tasks: 003, 004, 005.

**Fix**: Remove the misleading risk note from `_plan.md`. Confirm in tasks 004/005 that component names follow the existing pattern: `pages::restaurants.show` and `pages::restaurants.edit`.

### C3. Route model binding to soft-deleted / non-existent records returns 404, but tests assert 403 for non-owners
For non-owner tests in 003, 004, 005: route model binding will succeed (the model exists), then `mount()` calls `$this->authorize()` which throws `AuthorizationException`. Laravel's exception renderer converts that to a **403** response — good. But there's a subtle Livewire behavior: when `authorize()` is called inside `mount()` *during initial page load*, the exception bubbles up to Laravel's HTTP exception handler (returns 403 page). When called inside a `wire:click` *update request*, Livewire serializes it differently — the response is HTTP 200 with a JSON payload containing the error.

This means for Task 004's "it returns 403 when a non-owner attempts to delete via the show page" test, doing `Livewire::test(...)->call('delete')` will throw an `AuthorizationException` directly in the test (PHPUnit catches it). The test must use `expectException(AuthorizationException::class)` or `assertForbidden()` after a `actingAs` + browser-style test — *not* `assertStatus(403)`.

Affected tasks: 003, 004, 005.

**Fix**: Add an Implementation Note to tasks 004 and 005 specifying that:
- For initial-page-load authorization tests, use `$this->get(route(...))->assertForbidden()` (or `assertStatus(403)`).
- For Livewire `wire:click` authorization tests, use `Livewire::test(...)->call('delete')->assertForbidden()` (Livewire 4 supports this) OR `expect(fn () => Livewire::test(...)->call('delete'))->toThrow(AuthorizationException::class)`.

### C4. Task 002 misses the `RestaurantSource` import after extraction and `IndoorVibe`/`PatioQuality` enum-case rendering needs `use` statements in the partial
The current `⚡create.blade.php` uses `PatioQuality::cases()` and `IndoorVibe::cases()` directly inside the Blade `@foreach` loops, relying on the `use` statements at the top of the Volt component file. **Anonymous Blade components (`resources/views/components/...`) do not inherit those `use` imports** — they're a separate compilation context. The partial must either:
- Use FQCN inline: `@foreach (\App\Enums\PatioQuality::cases() as $case)`, or
- Add a `@php use App\Enums\PatioQuality; use App\Enums\IndoorVibe; @endphp` block at the top.

Affected task: 002.

**Fix**: Add explicit Implementation Note to Task 002 stating the partial must FQCN-reference the enums or include a `@php use ... @endphp` block, because anonymous Blade components don't inherit `use` imports from the parent Volt component.

### C5. Task 005 missing test that updated arrays correctly persist (cuisine_tags / vibe_tags split on save)
Task 005's test list verifies pre-population and a generic "saves updated fields and redirects". But there's no test confirming that the round-trip works: comma-string in form -> array in DB -> comma-string back in form. This is the #1 most likely place a regression will land silently because the create page handles this with a custom `splitTags()` method — the edit page must too. Also: the create page falls through to `addError()` if the split produces an empty array — easy to forget on edit.

Affected task: 005.

**Fix**: Add to Task 005 requirements:
- `it persists updated cuisine_tags as an array after splitting on comma`
- `it persists updated vibe_tags as an array after splitting on comma`
- `it shows a validation error when cuisine_tags becomes empty after trimming`

## Important (Should fix before building)

### I1. Task 002 acceptance criterion "RestaurantCreateTest continues to pass without modification" but the "still saves a restaurant correctly" requirement implies a *new* test
This is contradictory. The acceptance criterion says don't modify the existing suite. The requirement bullet "it still saves a restaurant correctly after the partial extraction" is already covered by the existing "saves a restaurant with valid data and redirects to the index" test in `RestaurantCreateTest`. Adding it would be duplication. The "renders X field on the create page" tests can be done via `Livewire::test('pages::restaurants.create')->assertSee(__('Name'))` etc.

Affected task: 002.

**Fix**: Clarify Task 002 to say the rendering tests are *new* (probably in a `RestaurantFormFieldsTest` or appended to existing test files), and the "still saves correctly" test is satisfied by the existing suite continuing to pass — no new test needed. Remove the duplicate requirement bullet.

### I2. Task 004 and 005 don't define how `mount(Restaurant $restaurant)` interacts with public properties for hydration
Volt-style components use public properties for state. The edit page needs to assign all editable fields from the `$restaurant` parameter into typed public properties in `mount()`. Critically, **Livewire 4 will not auto-hydrate Eloquent models stored as public properties** unless you opt in (and even then, model-as-property is discouraged because of N+1 reload semantics on each request).

Affected tasks: 004, 005.

**Fix**: Add an Implementation Note clarifying:
- Edit page: store individual scalar fields as public properties, not the Restaurant model. Store the restaurant ID as `public int $restaurantId` and re-resolve via `Restaurant::findOrFail($this->restaurantId)` (scoped to owner) inside `save()`. Re-authorize before update.
- Show page: same — keep `public int $restaurantId` and a `#[Computed] restaurant()` accessor, OR accept that the model gets serialized as a Livewire property (Livewire 4 supports this but reloads from DB each request).

### I3. Task 005 does not include `address` and `avg_duration_minutes` in pre-population test list
Both fields are editable per the description but lack pre-population test coverage. They will silently fail to pre-populate if the worker copies the create page defaults instead of the model values.

Affected task: 005.

**Fix**: Add to Task 005 requirements:
- `it pre-populates the address field with the existing restaurant address`
- `it pre-populates the average duration with the existing value`

### I4. No test for redirect target on delete in Task 004
Requirement says "deletes the restaurant and redirects to index when the owner calls delete" — but `assertRedirect(route('restaurants.index'))` should be explicit. Also missing: the success toast assertion (consistency with create page pattern) and confirmation that the row is actually gone from the DB.

Affected task: 004.

**Fix**: Reword the delete requirement to: `it deletes the restaurant from the database and redirects to the index when the owner calls delete`. Add Implementation Note that the test must `assertDatabaseMissing` the restaurant row + `assertRedirect(route('restaurants.index'))`.

### I5. Task 004 missing test that Edit/Delete buttons are NOT shown to non-owners (since they get 403, but if anything goes wrong with auth they shouldn't be exposed)
Defense in depth — if for some reason a future change weakens `mount()` auth, we still want the UI not to expose dangerous CTAs to non-owners. Currently moot because of the 403 in mount, but worth documenting.

Affected task: 004.

**Minor**: Could be skipped given the mount() guard fully blocks rendering.

### I6. `last_visited_at` is a `CarbonImmutable` (per AppServiceProvider's `Date::use(CarbonImmutable::class)`) — formatting must use `format()` not `diffForHumans()` carelessly
Plan says format as `d M Y` or "Never". `Date::use(CarbonImmutable::class)` is set globally so any `->format()` call on the casted attribute works fine — flag this so the worker doesn't try to mutate the immutable instance.

Affected task: 004.

**Fix**: Add Implementation Note: "`last_visited_at` is cast to `datetime` and the project uses `CarbonImmutable` globally — use `->format('d M Y')` for display, never mutating methods."

### I7. Task 004 references `flux:modal` but no test verifies modal-driven delete flow
You can't easily test a Flux modal's UX in a Livewire unit test (it's Alpine-driven), but the Livewire-side behavior is just `wire:click="delete"`. The plan's test list is fine — but should explicitly note that modal interaction is verified at the e2e level (Playwright), not here. Otherwise a worker may invent useless `Livewire::test()` modal-state assertions.

Affected task: 004.

**Fix**: Add Implementation Note clarifying that Livewire tests should test the `delete()` method directly; the modal confirmation UX is browser-only and out of scope for these tests.

### I8. Tasks 004/005 don't account for `wire:navigate` between show ↔ edit ↔ index
Existing index page uses `wire:navigate` for SPA-style transitions. The new pages should preserve this (e.g., `<flux:button as="link" href="..." wire:navigate>`). If omitted, full-page reloads break the perceived performance.

Affected tasks: 004, 005.

**Fix**: Add to both tasks: "Use `wire:navigate` on links between index/show/edit for consistency with the existing index page pattern."

### I9. Task 003 route order matters: `/restaurants/{restaurant}` will match `/restaurants/create`
Standard Laravel routing pitfall. If `Route::livewire('restaurants/{restaurant}', ...)` is registered *before* `restaurants/create`, then visiting `/restaurants/create` will try to bind `create` as a Restaurant ID and 404. The existing routes file declares `restaurants/create` before any wildcard would exist, but a worker editing the file might re-order or insert wrong.

Affected task: 003.

**Fix**: Add explicit ordering note in Task 003: "Register `restaurants/{restaurant}` AFTER `restaurants/create` and `restaurants/{restaurant}/edit` AFTER `restaurants/create` to avoid `create` being bound as a restaurant ID."

### I10. `Restaurant::destroy()` vs `$restaurant->delete()`
Plan says "Restaurant::destroy()" — fine, but using the resolved instance (`$this->restaurant->delete()`) is more consistent with Eloquent practices and lets policy/relationship hooks fire. `Restaurant::destroy($id)` does work but skips model events on individual instance, plus you'd need to re-resolve the ID.

Affected task: 004.

**Fix**: Change Task 004 description to use `$this->restaurant->delete()` (after `$this->authorize('delete', $this->restaurant)`).

## Minor (Nice to address)

### M1. Task 001 doesn't specify whether policy methods should accept `Restaurant` or also handle null/unauthenticated users
Laravel policies receive `User $user, Restaurant $restaurant`. For unauthenticated users, the auth middleware already handles redirect — but if any flow bypasses middleware, the policy will get a null user. PHP type hint forces `User` — Laravel itself converts null to false unless `before()` says otherwise. Just confirm signature in implementation note.

### M2. The task plan doesn't address the index page's existing card layout — should the whole card be a clickable link to the detail page, or just a "View" button?
Mobile-first thinking suggests the entire card area should be tappable. Plan says "Add link from index card to detail page" without specifying. A worker will pick something reasonable, but UX consistency with other apps favors making the card itself the interaction target.

### M3. No accessibility note for the delete confirmation modal
`flux:modal` includes ARIA semantics, but the trigger button text matters. "Delete" with no context can be unclear for screen readers — "Delete restaurant" is better. Minor — Flux defaults are decent.

### M4. Pint must be run after edits (per project standards)
Not in any acceptance criteria — implicit in "Code follows project standards" but worth being explicit in Implementation Notes for each task that modifies PHP.

## Questions for the Team

### Q1. Should delete be soft-delete or hard-delete?
The Restaurant migration likely doesn't have `softDeletes()` — but if the long-term plan is to retain visit history (which references restaurant_id), hard-deleting will cascade or orphan visits. The current scope says "soft-deleting/hard-deleting" — pick one. Recommend: hard-delete now (matches scope simplicity), revisit when Visit logging lands.

### Q2. Should the show page expose `lat`/`lng` and `source`?
Plan lists "all model fields" for the detail page but explicitly excludes `source`/`lat`/`lng` from edit. Are these fields worth showing read-only on the detail view? Currently ambiguous in Task 004's requirement list — only specific fields are tested. If they're not shown, "all model fields" is misleading wording.

### Q3. Should edit / delete CTAs require an additional UI confirmation when the restaurant has visits associated?
Visits relationship exists. Deleting a restaurant with visits is a destructive action. Out of scope per "Out of Scope: Visit logging" — but worth flagging.

### Q4. What happens if a worker submits the form with stale data after another tab updated the same restaurant?
No optimistic locking. Edge case — probably fine for a personal app. Not worth solving now.

