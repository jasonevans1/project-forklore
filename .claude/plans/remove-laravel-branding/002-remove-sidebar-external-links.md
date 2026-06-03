# Task 002: Remove Sidebar External Links

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Remove the Repository and Documentation nav items from the sidebar. These are starter-kit artifacts pointing to the Laravel GitHub repo and laravel.com docs — they have no place in the Forklore app.

## Context
- Related files:
  - `resources/views/layouts/app/sidebar.blade.php` lines 38–46
- The two items to remove are wrapped in a `<flux:sidebar.nav>` block below `<flux:spacer />`:
  ```blade
  <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
      {{ __('Repository') }}
  </flux:sidebar.item>
  <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
      {{ __('Documentation') }}
  </flux:sidebar.item>
  ```
- The entire second `<flux:sidebar.nav>` block (lines 38–46) should be removed; `<flux:spacer />` should remain

## Testing Notes (IMPORTANT)
- The sidebar only renders inside the **authenticated app layout** and is rendered by Livewire pages like the dashboard. It does NOT render on the guest welcome page (`/`).
- Tests must authenticate a user and hit an authenticated route that renders the sidebar. Follow `tests/Feature/DashboardTest.php`:
  ```php
  $user = User::factory()->create();
  $this->actingAs($user);
  $response = $this->get(route('dashboard'));
  $response->assertOk();
  ```
- Assert on the rendered HTML. Prefer asserting the destination URLs are gone (they are unambiguous) rather than the link text, since "Documentation" could match other content:
  - `$response->assertDontSee('github.com/laravel/livewire-starter-kit')`
  - `$response->assertDontSee('laravel.com/docs/starter-kits')`
- Optionally also assert `assertDontSee('Repository')`.

## Requirements (Test Descriptions)
- [ ] `it does not show a Repository link in the sidebar` (authenticated; assert the `github.com/laravel/livewire-starter-kit` URL is absent)
- [ ] `it does not show a Documentation link in the sidebar` (authenticated; assert the `laravel.com/docs/starter-kits` URL is absent)

## Acceptance Criteria
- All requirements have passing tests
- Code follows project standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
