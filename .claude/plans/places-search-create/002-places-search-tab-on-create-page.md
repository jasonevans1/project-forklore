# Task 002: Add Places search tab to the restaurant create page

**Status**: completed
**Depends on**: [001]
**Retry count**: 0

## Description
Extend the restaurant create Volt SFC to include a two-tab layout: a "Search Google" tab where users search Places and select a result, and an "Add manually" tab containing the existing form. Selecting a Places result pre-fills all mappable fields and switches the user to the manual tab to complete the rest.

## Context
- Related files:
  - `resources/views/pages/restaurants/⚡create.blade.php` — Volt SFC to modify
  - `resources/views/components/restaurants/form-fields.blade.php` — shared partial, no changes needed
  - `app/Services/PlacesService.php` — inject and call `textSearch()` (depends on Task 001 for lat/lng/price_level in results)
  - `app/Livewire/VibePicker.php` — child component, no changes needed
  - `tests/Feature/RestaurantCreateTest.php` — existing tests for this component; do NOT break them. In particular `it sets source to favorite on save` asserts the saved source. See the "Source of search-created restaurants" note below.
- The component is tested via `Livewire::test('pages::restaurants.create')` (Volt page route name). New tests go in `tests/Feature/RestaurantCreateTest.php` or a new `tests/Feature/RestaurantPlacesSearchTest.php`.
- Patterns to follow: existing Volt SFC structure in the same file; inject the service into the action method signature (e.g. `public function search(PlacesService $places): void`) — Livewire/Volt resolves it from the container. Do not constructor-inject in a Volt SFC.
- Use `flux:tabs` for the tab switcher and `flux:card` for each search result card
- The `activeTab` property controls which tab is shown (`'search'` | `'manual'`)
- `searchResults` holds the array returned by `PlacesService::textSearch()`. Note `textSearch()` returns `null` (not `[]`) when the quota is exceeded or no API key is configured — treat `null` and `[]` both as "no results" and show the empty state. Do not iterate over null.

### CRITICAL: the existing `save()` method must be updated
The current `save()` (in the SFC) hardcodes `'source' => RestaurantSource::Favorite`, `'lat' => null`, `'lng' => null`, and does NOT pass `places_id` to `Restaurant::create()`. As written, selecting a place and saving would silently drop `places_id`, `lat`, and `lng`. You MUST modify the `Restaurant::create()` array in `save()` to persist `$this->lat`, `$this->lng`, and `$this->places_id` (instead of hardcoded nulls / a missing key). Add validation rules for the new properties: `lat`/`lng` as `nullable|numeric`, `places_id` as `nullable|string|max:255`.

### Source of search-created restaurants
Restaurants created through this create page remain `source = RestaurantSource::Favorite` even when pre-filled from a Places search — the user is deliberately curating a favorite. Do NOT change `save()` to set `source = Places`. Keep the existing `it sets source to favorite on save` test passing. (`places_id` is stored purely for later correlation/event lookups, independent of source.)

### Cuisine type mapping rules
Strip these noise types entirely: `food`, `restaurant`, `establishment`, `point_of_interest`, `meal_takeaway`, `meal_delivery`, `cafe`
For remaining types: replace underscores with spaces, strip a trailing ` restaurant` suffix (e.g. `italian_restaurant` → `italian`), lowercase everything, deduplicate.
Store the result as a comma-separated string in `cuisine_tags` (matching the existing text field format).

### Price level mapping
Use the integer returned by Task 001 directly — no additional mapping needed in the component.

### Form fields pre-filled by selectPlace()
| Component property | Source field |
|--------------------|-------------|
| `name` | `name` |
| `address` | `address` |
| `cuisine_tags` | mapped from `types` (see above) |
| `price_level` | `price_level` (int 1–4 or null) |
| `lat` | `lat` |
| `lng` | `lng` |
| `places_id` | `id` |

Add `lat`, `lng`, and `places_id` as public properties on the component (they are not currently present). Type them as `public ?float $lat = null;`, `public ?float $lng = null;`, `public ?string $places_id = null;`.

### Unique constraint on places_id (production safety)
`places_id` has a **global** UNIQUE index on the `restaurants` table (`$table->string('places_id')->nullable()->unique()` — not scoped to a user). If the selected place already exists in the DB anywhere (e.g. a Places-sourced discovery already cached as a row, or any user previously added this same place), `Restaurant::create()` will throw a `QueryException` (constraint violation) and surface a 500 to the user. Guard against this in `save()`: before creating, check `Restaurant::where('places_id', $this->places_id)->exists()` (only when `places_id` is non-null). If it exists, add a friendly validation error (e.g. `__('This restaurant is already in the system.')`) on the `name` field and return without throwing. Add a test for this case.

## Requirements (Test Descriptions)
- [x] `it shows the search tab by default`
- [x] `it calls PlacesService textSearch with the entered query when search is submitted`
- [x] `it displays each result as a card with name and address`
- [x] `it shows an empty state message when the search returns no results`
- [x] `it shows the empty state when textSearch returns null (quota exceeded or no key)`
- [x] `it pre-fills the name field when a place is selected`
- [x] `it pre-fills the address field when a place is selected`
- [x] `it pre-fills price_level when a place is selected`
- [x] `it leaves price_level null when the selected place has no price level`
- [x] `it stores the places_id when a place is selected`
- [x] `it stores lat and lng when a place is selected`
- [x] `it maps Google types to cuisine_tags omitting noise types`
- [x] `it strips the trailing restaurant suffix from cuisine types`
- [x] `it switches to the manual tab after a place is selected`
- [x] `it persists places_id, lat, and lng to the database when the form is saved after a place search`
- [x] `it keeps source as favorite when saving a place-prefilled restaurant`
- [x] `it shows a validation error instead of throwing when saving a place whose places_id already exists`
- [x] `it still saves a normal manual restaurant with places_id null (regression for the existing save path)`

### Testing notes
- Fake the service with `$this->mock(PlacesService::class)->allows('textSearch')->andReturn([...])`. NEVER let a real HTTP call fire — there is no API key in tests and the 100/day quota must be protected. Follow the mocking style in `tests/Feature/PromotePlacesToFavoriteTest.php`.
- The `selectPlace` mapping/pre-fill tests can drive the component directly (`->set('searchResults', [...])->call('selectPlace', 0)` or by index/id matching the chosen approach) rather than going through a faked search, to keep mapping assertions focused.
- All existing tests in `tests/Feature/RestaurantCreateTest.php` must continue to pass unchanged.

## Acceptance Criteria
- All requirements have passing tests using Livewire testing helpers (fake `PlacesService`, no real HTTP)
- `save()` persists `lat`, `lng`, and `places_id` (no longer hardcoded to null / omitted) and still defaults `source` to `Favorite`
- A duplicate `places_id` produces a validation error, not a 500
- All pre-existing `RestaurantCreateTest.php` tests still pass
- Mobile-first layout: search input and result cards are thumb-friendly on small screens
- Pint reports no style issues
