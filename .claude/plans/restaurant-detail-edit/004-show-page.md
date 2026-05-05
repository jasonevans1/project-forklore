# Task 004: Detail (Show) Page with Delete Action

**Status**: completed
**Depends on**: [001, 002, 003]
**Retry count**: 0

## Description
Create the Volt page at `resources/views/pages/restaurants/⚡show.blade.php`. It displays editable fields, `visit_count`, and a formatted `last_visited_at`. Edit and delete buttons live in the thumb zone (bottom of viewport, fixed or sticky). Delete uses a `flux:modal` for confirmation and calls a `delete()` Livewire method that re-authorizes via `RestaurantPolicy` before deleting and redirecting to the index.

## Context
- Related files:
  - `resources/views/pages/restaurants/⚡show.blade.php` (new)
  - `resources/views/pages/restaurants/⚡index.blade.php` (pattern reference)
  - `app/Models/Restaurant.php` (fields: name, address, cuisine_tags, vibe_tags, price_level, patio_quality, indoor_vibe_when_cold, avg_duration_minutes, last_visited_at, visit_count)
  - `app/Policies/RestaurantPolicy.php` (Task 001)
  - `routes/restaurants.php` (Task 003 for named routes)
- Patterns: existing Volt pages use `mount()` to receive route-model-bound parameter; call `$this->authorize('view', $restaurant)` in `mount()` before populating state.
- Thumb zone: a `fixed bottom-0` bar with Edit (link button → `restaurants.edit` with `wire:navigate`) and Delete (opens `flux:modal`) — consistent with mobile-first convention.
- Tags displayed as Flux badges (matches existing index page pattern).
- Add link from index card to detail page (update `⚡index.blade.php` so card or a primary CTA in the card navigates to `restaurants.show` with `wire:navigate`).

## Requirements (Test Descriptions)
- [ ] `it shows the restaurant name on the detail page`
- [ ] `it shows the cuisine tags on the detail page`
- [ ] `it shows the vibe tags on the detail page`
- [ ] `it shows the price level on the detail page`
- [ ] `it shows the patio quality on the detail page`
- [ ] `it shows the indoor vibe on the detail page`
- [ ] `it shows the visit count on the detail page`
- [ ] `it shows the last visited date on the detail page`
- [ ] `it shows never when last visited date is null on the detail page`
- [ ] `it shows a link to the edit page on the detail page`
- [ ] `it shows the restaurant address when present on the detail page`
- [ ] `it deletes the restaurant from the database and redirects to the index when the owner calls delete`
- [ ] `it forbids a non-owner from invoking the delete method`

## Acceptance Criteria
- All requirements have passing tests
- Authorization checked in `mount()` for view AND re-checked in `delete()` for delete
- Index cards link to detail page with `wire:navigate`
- Mobile-first: thumb zone buttons accessible on small screens
- Code follows project standards (run `vendor/bin/pint --dirty --format agent` after PHP edits)

## Implementation Notes
- **Authorization test mechanics**:
  - For initial-load `view` authorization, use `$this->actingAs($otherUser)->get(route('restaurants.show', $restaurant))->assertForbidden()`.
  - For the `delete()` method authorization, use `Livewire::test('pages::restaurants.show', ['restaurant' => $restaurant])->call('delete')` and assert via `expect(fn () => ...)->toThrow(AuthorizationException::class)` OR Livewire's `assertForbidden()` helper. Note that `Livewire::test()` will *also* trigger `mount()`, which already throws — so for the non-owner delete test, the throw will happen on instantiation. Acceptable.
- **Delete behavior**: call `$this->authorize('delete', $this->restaurant)` then `$this->restaurant->delete()` (instance method, not `Restaurant::destroy()`). Then `Flux::toast(variant: 'success', text: __('Restaurant deleted.'))` and `$this->redirect(route('restaurants.index'), navigate: true)`. Test must `assertDatabaseMissing('restaurants', ['id' => $id])` and `assertRedirect(route('restaurants.index'))`.
- **State storage**: prefer storing scalar fields plus `public int $restaurantId` on the component, OR allow Livewire to serialize the `Restaurant` model as a public property — both are valid in Livewire 4. If using the model directly, accept that it reloads from DB each request.
- **Date formatting**: project uses `CarbonImmutable` globally (`AppServiceProvider::configureDefaults`). `last_visited_at` is cast to datetime — use `->format('d M Y')` for display. Show `__('Never')` when null.
- **Modal interaction is browser-only**: tests should call `delete()` directly via `Livewire::test`. Modal confirmation UX is verified at the e2e level (out of scope for this task).
- **wire:navigate**: links from index → show, show → edit, and back-buttons should use `wire:navigate` for SPA-style transitions, matching the existing index pattern.
- **Fields displayed**: name, address (when present), cuisine_tags, vibe_tags, price_level, patio_quality, indoor_vibe_when_cold, avg_duration_minutes, visit_count, last_visited_at. Do NOT display `lat`, `lng`, or `source` (they aren't user-facing for the personal-favorites use case).
