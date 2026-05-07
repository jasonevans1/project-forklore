# Task 005: Update Edit Page Logic + Edit Tests + Show Test Cleanup

**Status**: complete
**Depends on**: [002, 003, 004]
**Retry count**: 0

## Description
Update the edit single-file page to treat `vibe_tags` as an array (no longer a string), matching the create page. Update `mount()` to load the array directly instead of imploding it. Apply the same taxonomy validation rules. Update all affected tests in `RestaurantEditTest.php`. Also clean up `RestaurantShowTest.php` test data so it uses valid taxonomy tags.

## Context
- Files to update:
  - `resources/views/pages/restaurants/⚡edit.blade.php`
  - `tests/Feature/RestaurantEditTest.php`
  - `tests/Feature/RestaurantShowTest.php`
- The form-fields partial is already updated by task 004, so the VibePicker renders automatically
- In the edit page:
  - `public string $vibe_tags = ''` → `public array $vibe_tags = []`
  - `mount()`: remove `implode(', ', $restaurant->vibe_tags ?? [])`, replace with `$this->vibe_tags = $restaurant->vibe_tags ?? []`
  - Validation: same as create — `'vibe_tags' => ['required', 'array', 'min:1']` and `'vibe_tags.*' => [Rule::in(\Illuminate\Support\Arr::flatten(config('vibes')))]`
  - Inside `save()`, delete BOTH lines:
    - Delete `$vibeTags = $this->splitTags($this->vibe_tags);`
    - Delete the `if (empty($vibeTags)) { addError('vibe_tags', ...); return; }` guard
  - In the `$restaurant->update([...])` call, change `'vibe_tags' => $vibeTags` to `'vibe_tags' => $this->vibe_tags`
  - The `splitTags()` helper stays for cuisine_tags
- Tests in `RestaurantEditTest.php` that need updating:
  - All tests that `.set('vibe_tags', 'some string')` → `.set('vibe_tags', ['valid-tag'])`
  - "pre-populates the vibe tags as a comma-separated string" (line 46) — rename to "pre-populates vibe_tags as an array from the existing restaurant"; change factory override from `['romantic', 'casual']` to `['casual', 'lively']` (or other valid taxonomy values); assert `->assertSet('vibe_tags', ['casual', 'lively'])`
  - "persists updated vibe_tags as an array after splitting on comma" (line 139) — rename to "persists updated vibe_tags array to the database"; replace `.set('vibe_tags', 'romantic, lively')` with `.set('vibe_tags', ['cozy', 'lively'])`
  - "shows validation errors without redirecting when vibe tags become empty after trimming" (line 187) — rename to "shows validation errors without redirecting when vibe_tags is an empty array"; replace `.set('vibe_tags', ',  ,  ')` with `.set('vibe_tags', [])`
  - All other tests using `'casual'` factory overrides — `'casual'` is in the taxonomy, leave as-is
- Tests in `RestaurantShowTest.php` that need updating:
  - "shows the vibe tags on the detail page" (line 47) — change factory override from `['romantic', 'quiet']` to `['date_night', 'quiet']` (valid taxonomy); update assertions accordingly (`->assertSee('date_night')` or `->assertSee('date night')` depending on humanization in show page)
  - Note: the show page (`⚡show.blade.php`) currently renders raw tag strings via `<flux:badge>` — display humanization is a separate concern; this task does not need to humanize show-page badges. Keep the show page rendering as-is and just align test data to the taxonomy.

## Requirements (Test Descriptions)
- [x] `it pre-populates vibe_tags as an array from the existing restaurant`
- [x] `it persists updated vibe_tags array to the database`
- [x] `it rejects vibe_tags containing a tag not in the taxonomy`
- [x] `it rejects an empty vibe_tags array on update`
- [x] `it saves updated fields and redirects to the detail page` (update test — vibe_tags now array)
- [x] `it shows validation errors without redirecting when name is empty` (update test)
- [x] `it shows validation errors without redirecting when cuisine tags are empty` (update test)
- [x] `it shows validation errors without redirecting when vibe_tags is an empty array` (renamed + rewritten — string-trimming case removed)
- [x] `it forbids a non-owner from invoking save via the edit page` (update test — vibe_tags now array)
- [x] `it does not overwrite source or visit count on save` (update test)
- [x] `it shows the vibe tags on the detail page` (update test in `RestaurantShowTest.php` — change `['romantic', 'quiet']` to taxonomy-valid values)

## Acceptance Criteria
- All requirements have passing tests
- Edit page no longer has a text input for vibe_tags (inherited from form-fields update in task 004)
- `mount()` loads `vibe_tags` as an array; no `implode` call
- `RestaurantShowTest.php` no longer asserts on the out-of-taxonomy `'romantic'` tag
- No decrease in test coverage

## Implementation Notes
All implementation was already complete when this task was picked up:
- `resources/views/pages/restaurants/⚡edit.blade.php` already had `public array $vibe_tags = []` and `mount()` loading it as an array without `implode`
- Validation rules already included `'vibe_tags' => ['required', 'array', 'min:1']` and `'vibe_tags.*' => [Rule::in(...)]`
- `save()` already used `'vibe_tags' => $this->vibe_tags` directly (no `splitTags` for vibe_tags)
- `RestaurantEditTest.php` already had all required test names and assertions
- `RestaurantShowTest.php` already used `['date_night', 'quiet']` (valid taxonomy values)
- All 158 tests passed on first run
