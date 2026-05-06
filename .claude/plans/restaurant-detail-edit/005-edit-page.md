# Task 005: Edit Page with Pre-populated Form

**Status**: completed
**Depends on**: [001, 002, 003]
**Retry count**: 0

## Description
Create the Volt page at `resources/views/pages/restaurants/⚡edit.blade.php`. It pre-populates all editable fields from the restaurant record and includes `<x-restaurants.form-fields />`. The `save()` method validates, re-authorizes via `RestaurantPolicy::update`, persists changes, and redirects to the detail page on success.

## Context
- Related files:
  - `resources/views/pages/restaurants/⚡edit.blade.php` (new)
  - `resources/views/pages/restaurants/⚡create.blade.php` (pattern for validation rules and tag-splitting logic)
  - `resources/views/components/restaurants/form-fields.blade.php` (Task 002)
  - `app/Policies/RestaurantPolicy.php` (Task 001)
  - `routes/restaurants.php` (Task 003)
- `mount(Restaurant $restaurant)` receives the route-model-bound restaurant; call `$this->authorize('update', $restaurant)` immediately, then assign scalar values to public properties (joining `cuisine_tags`/`vibe_tags` arrays back into comma strings).
- Editable fields: `name`, `address`, `cuisine_tags`, `vibe_tags`, `price_level`, `patio_quality`, `indoor_vibe_when_cold`, `avg_duration_minutes`
- `cuisine_tags` and `vibe_tags` stored as arrays in DB; pre-populated as comma-joined strings (reverse of create's split).
- `source`, `lat`, `lng`, `visit_count`, `last_visited_at` are NOT editable.
- Validation rules identical to create.
- On success: `Flux::toast(variant: 'success', text: __('Restaurant updated.'))` + redirect to `route('restaurants.show', $restaurant)` with `navigate: true`.
- `save()` MUST re-authorize (`$this->authorize('update', $this->restaurant)`) before updating — never trust the mount-time authorization for the write path.

## Requirements (Test Descriptions)
- [ ] `it pre-populates the name field with the existing restaurant name`
- [ ] `it pre-populates the address field with the existing restaurant address`
- [ ] `it pre-populates the cuisine tags as a comma-separated string`
- [ ] `it pre-populates the vibe tags as a comma-separated string`
- [ ] `it pre-populates the price level with the existing value`
- [ ] `it pre-populates patio quality with the existing value`
- [ ] `it pre-populates indoor vibe with the existing value`
- [ ] `it pre-populates the average duration with the existing value`
- [ ] `it saves updated fields and redirects to the detail page`
- [ ] `it persists updated cuisine_tags as an array after splitting on comma`
- [ ] `it persists updated vibe_tags as an array after splitting on comma`
- [ ] `it shows validation errors without redirecting when name is empty`
- [ ] `it shows validation errors without redirecting when cuisine tags are empty`
- [ ] `it shows validation errors without redirecting when vibe tags become empty after trimming`
- [ ] `it forbids a non-owner from loading the edit page`
- [ ] `it forbids a non-owner from invoking save via the edit page`
- [ ] `it does not overwrite source or visit count on save`

## Acceptance Criteria
- All requirements have passing tests
- Authorization enforced in both `mount()` and `save()`
- Form uses `<x-restaurants.form-fields />` — no field duplication
- Redirects to `restaurants.show` (not index) on success, using `wire:navigate`
- Code follows project standards (run `vendor/bin/pint --dirty --format agent` after PHP edits)

## Implementation Notes
- **Authorization test mechanics**:
  - For load-time auth, use `$this->actingAs($otherUser)->get(route('restaurants.edit', $restaurant))->assertForbidden()`.
  - For `save()` auth, use `expect(fn () => Livewire::test('pages::restaurants.edit', ['restaurant' => $restaurant])->call('save'))->toThrow(AuthorizationException::class)` (note: mount itself will throw for the non-owner case, so the throw fires on instantiation).
- **State storage**: Prefer scalar public properties + `public int $restaurantId`. In `save()`, re-resolve via `Restaurant::findOrFail($this->restaurantId)`, re-authorize, update, redirect. This avoids Livewire model-as-property reload semantics.
- **Tag round-trip**: reuse the same `splitTags()` helper pattern from create. Empty cuisine/vibe arrays after trimming must trigger an `addError()` and prevent save (mirror create page logic exactly).
- **Source/visit_count protection**: ensure `update()` only passes the editable subset of fields. Test by setting up a restaurant with `source = Favorite` and `visit_count = 5`, calling save with new name, and asserting both fields remain unchanged in DB.
- **wire:navigate**: redirect uses `navigate: true`; back link / cancel button uses `wire:navigate`.
- **Validation rules**: copy from create exactly — `required|string|max:255` on name, etc.
