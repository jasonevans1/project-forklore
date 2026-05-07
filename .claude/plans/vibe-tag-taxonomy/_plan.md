# Plan: Vibe Tag Taxonomy

## Created
2026-05-05

## Status
completed

## Objective
Replace the freeform `vibe_tags` text input with a structured taxonomy defined in `config/vibes.php` and a reusable Livewire chip-picker component that enforces only valid tags can be selected.

## Related Issues
none

## Scope

### In Scope
- `config/vibes.php` defining three dimensions: energy, occasion, experience
- `VibePicker` Livewire component using `#[Modelable]` to bind to parent `wire:model`
- Replace text input in `resources/views/components/restaurants/form-fields.blade.php`
- Update create and edit Livewire single-file pages: `vibe_tags` becomes `array`, validation updated, `splitTags` call removed from vibe_tags path
- Update `RestaurantFactory` so its default `vibe_tags` only uses valid taxonomy values
- Update `RestaurantSeeder` so its hard-coded `vibe_tags` only use valid taxonomy values (keeps dev/test parity)
- Update all existing tests affected by the string → array type change (including `RestaurantShowTest`, `RestaurantEditTest`, `RestaurantCreateTest`)
- New tests: valid tags save correctly, invalid tags are rejected

### Out of Scope
- `cuisine_tags` (remains freeform text input with comma-splitting)
- Migrating existing production database rows (existing vibe_tags may contain arbitrary strings; no migration needed now — note that on edit, the user must select fresh tags from the picker, so any out-of-taxonomy values get replaced naturally)
- Quick Pick / Quiz / Tournament — not touched by this plan

## Success Criteria
- [ ] `config/vibes.php` defines energy, occasion, experience dimensions
- [ ] `VibePicker` component reads config, toggles chips, binds via `#[Modelable]`
- [ ] Create and edit forms show chip picker; no freeform text input for vibe_tags
- [ ] Valid tags from the taxonomy save correctly on both create and edit
- [ ] Tags not in the taxonomy are rejected with a validation error
- [ ] At least one tag is required (empty array is rejected)
- [ ] All existing tests updated and passing
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Create config/vibes.php with taxonomy | - | completed |
| 002 | Build VibePicker Livewire component | 001 | completed |
| 003 | Update RestaurantFactory and RestaurantSeeder to use valid vibe tags | 001 | completed |
| 004 | Update form-fields partial + create page logic + create tests | 002, 003 | completed |
| 005 | Update edit page logic + edit tests + show test cleanup | 002, 003, 004 | completed |

## Architecture Notes
- `vibe_tags` in both single-file pages changes from `public string $vibe_tags = ''` to `public array $vibe_tags = []`
- `VibePicker` uses `#[Modelable] public array $selected = []` — parent binds with `<livewire:vibe-picker wire:model="vibe_tags" />`
- Validation in pages: `'vibe_tags' => ['required', 'array', 'min:1']` + `'vibe_tags.*' => [Rule::in(Arr::flatten(config('vibes')))]`
- The `splitTags()` helper stays for cuisine_tags (still freeform text), removed from vibe_tags path. Both the local `$vibeTags = $this->splitTags($this->vibe_tags)` line AND the `if (empty($vibeTags)) { addError(...) }` guard get deleted; pass `$this->vibe_tags` directly to the create/update array.
- `mount()` in edit page changes: `$this->vibe_tags = $restaurant->vibe_tags ?? []` (already an array, no implode needed)
- Validation defense: the chip picker only renders valid taxonomy chips, so users can't construct out-of-taxonomy tags through the UI. The `Rule::in` validation is a defensive safety net against tampered Livewire payloads. No user-facing UI is required to surface a `vibe_tags.*` validation failure (it cannot happen via normal flow).

## Risks & Mitigations
- Existing tests set `vibe_tags` as a string: update all affected tests to pass arrays instead
- Factory uses 'romantic' (not in taxonomy): update factory defaults to use valid tags
- Seeder uses 'romantic' and 'seasonal' (not in taxonomy): update seeder so seeded data aligns with the taxonomy
- `RestaurantShowTest` line 47-58 explicitly seeds `['romantic', 'quiet']`: update to use valid taxonomy values
- `RestaurantEditTest` "vibe tags become empty after trimming" test (line 187) tests string-trimming behavior that no longer exists: replace with an empty-array test
- `RestaurantEditTest` "pre-populates the vibe tags as a comma-separated string" test (line 46) seeds `['romantic', 'casual']`: change to valid taxonomy values
- Existing DB rows may have arbitrary vibe_tags strings: out of scope — no migration, existing rows unaffected unless edited (in which case user re-selects from picker)
