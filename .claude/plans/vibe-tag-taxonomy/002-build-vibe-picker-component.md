# Task 002: Build VibePicker Livewire Component

**Status**: completed
**Depends on**: [001]
**Retry count**: 0

## Description
Create a reusable Livewire component `VibePicker` that renders all vibe tags from `config/vibes.php` as toggleable chip buttons, grouped by dimension. It uses `#[Modelable]` so parent components bind to it via `wire:model`.

## Context
- Use `php artisan make:livewire VibePicker --no-interaction` to scaffold both files at once.
- Files created:
  - `app/Livewire/VibePicker.php`
  - `resources/views/livewire/vibe-picker.blade.php`
- `#[Livewire\Attributes\Modelable] public array $selected = []` — parent page holds the array via `wire:model="vibe_tags"`. The child property name MUST be `$selected` so parent binding `wire:model="vibe_tags"` proxies to the child correctly.
- `toggle(string $tag): void` — adds or removes the tag from `$selected`. In Blade: `wire:click="toggle('{{ $tag }}')"`.
- The `render()` method should pass `dimensions` to the view: `return view('livewire.vibe-picker', ['dimensions' => config('vibes')])`.
- The view renders dimensions as labeled groups (`<flux:label>` per dimension key, then a `flex flex-wrap gap-2` container of chip buttons).
- Selected chips use `variant="primary"`; unselected use `variant="ghost"` or default. Use `<flux:button type="button" size="sm">` to avoid form submission.
- Chip labels humanize underscore-separated tags via `str_replace('_', ' ', $tag)` (display only — the underlying value remains the canonical token).
- Patterns to follow: existing project conventions (curly braces on all control structures, explicit return types, descriptive method names).

## Requirements (Test Descriptions)
- [ ] `it renders a chip for every tag defined in the vibes config`
- [ ] `it marks a chip as selected when its tag is in the selected array`
- [ ] `it adds a tag to selected when toggle is called and the tag is not yet selected`
- [ ] `it removes a tag from selected when toggle is called and the tag is already selected`
- [ ] `it accepts an initial selected array via wire:model binding from a parent component`
- [ ] `it renders tags grouped under their dimension label (energy, occasion, experience)`
- [ ] `it humanizes underscored tag values for display (date_night renders as "date night")`

## Acceptance Criteria
- All requirements have passing tests
- Component is registered automatically (Livewire auto-discovery resolves `<livewire:vibe-picker />` to `App\Livewire\VibePicker`)
- Chip buttons use `type="button"` so they do not submit the parent `<form>`
- Mobile-first: chips wrap on small screens via `flex flex-wrap`
- Unit test for `toggle()` uses `Livewire::test(VibePicker::class)`; binding test uses an inline test component or asserts via the parent in a feature test
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
