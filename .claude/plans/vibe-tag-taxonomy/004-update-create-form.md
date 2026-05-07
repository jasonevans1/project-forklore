# Task 004: Update Form-Fields Partial + Create Page + Create Tests

**Status**: completed
**Depends on**: [002, 003]
**Retry count**: 0

## Description
Replace the freeform `vibe_tags` text input in the shared form-fields partial with the `VibePicker` component. Update the create Volt page to treat `vibe_tags` as an array, tighten validation to reject out-of-taxonomy tags, and update all affected tests in `RestaurantCreateTest.php`.

## Context
- Files to update:
  - `resources/views/components/restaurants/form-fields.blade.php`
  - `resources/views/pages/restaurants/⚡create.blade.php`
  - `tests/Feature/RestaurantCreateTest.php`
- The shared form-fields partial is used by both create and edit pages; the edit page (task 005) will benefit from this partial change automatically
- In the create page:
  - `public string $vibe_tags = ''` → `public array $vibe_tags = []`
  - Validation rules to update:
    - Replace `'vibe_tags' => ['required', 'string', 'max:500']` with `'vibe_tags' => ['required', 'array', 'min:1']`
    - Add `'vibe_tags.*' => [Rule::in(\Illuminate\Support\Arr::flatten(config('vibes')))]`
  - Inside `save()`, delete BOTH lines that touch vibe_tags via `splitTags`:
    - Delete `$vibeTags = $this->splitTags($this->vibe_tags);`
    - Delete the `if (empty($vibeTags)) { $this->addError('vibe_tags', __('At least one vibe tag is required.')); return; }` guard
  - In the `Restaurant::create([...])` call, change `'vibe_tags' => $vibeTags` to `'vibe_tags' => $this->vibe_tags`
  - The `splitTags()` helper method stays (still used for cuisine_tags)
- In `form-fields.blade.php`: delete the entire `{{-- Vibe Tags --}}` `<flux:input wire:model="vibe_tags" ...>` block and replace with:
  ```blade
  {{-- Vibe Tags --}}
  <div>
      <flux:label class="mb-2">{{ __('Vibe tags') }}</flux:label>
      <livewire:vibe-picker wire:model="vibe_tags" />
      @error('vibe_tags') <flux:error :message="$message" /> @enderror
      @error('vibe_tags.*') <flux:error :message="$message" /> @enderror
  </div>
  ```
  (The exact Flux error component name may vary — verify against existing patterns in the project; alternatively use `<div class="text-red-500 text-sm">{{ $message }}</div>` if Flux provides no `flux:error` component.)
- Existing tests in `RestaurantCreateTest.php` set `vibe_tags` as a string — update each to pass an array of valid tags (e.g. `['casual']`)
- The "splits comma-separated vibe_tags into an array on save" test becomes "saves the vibe_tags array directly to the database"
- The test using `'romantic, casual'` must change to valid taxonomy tags (e.g. `['casual', 'lively']`)

## Requirements (Test Descriptions)
- [ ] `it saves a restaurant with valid vibe tags selected from the taxonomy`
- [ ] `it rejects vibe_tags not in the taxonomy with a validation error`
- [ ] `it rejects an empty vibe_tags array with a validation error`
- [ ] `it saves the vibe_tags array directly to the database without splitting`
- [ ] `it requires name to save a restaurant` (update test — vibe_tags now array)
- [ ] `it requires non-empty cuisine_tags to save a restaurant` (update test — vibe_tags now array)
- [ ] `it requires non-empty vibe_tags to save a restaurant` (update test — vibe_tags now empty array)
- [ ] `it saves a restaurant with valid data and redirects to the index` (update test)
- [ ] `it sets the owner to the authenticated user on save` (update test)
- [ ] `it sets source to favorite on save` (update test)
- [ ] `it splits comma-separated cuisine_tags into an array on save` (update test — vibe_tags now array)
- [ ] `it surfaces validation errors without redirecting` (update test)
- [ ] `it saves optional fields when provided` (update test — vibe_tags now array)

## Acceptance Criteria
- All requirements have passing tests
- `form-fields.blade.php` no longer contains a `<flux:input>` for vibe_tags
- `VibePicker` is embedded via `<livewire:vibe-picker wire:model="vibe_tags" />`
- The `RestaurantFormFieldsTest` test that asserts the create page renders form fields still passes (it does not assert on vibe_tags input specifically)
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
