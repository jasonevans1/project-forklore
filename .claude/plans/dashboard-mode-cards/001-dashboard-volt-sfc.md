# Task 001: Convert Dashboard to Volt SFC with Mode Cards and Recently Added Restaurants

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Move the placeholder dashboard into the page-component convention. Create a Volt SFC at `resources/views/pages/⚡dashboard.blade.php` that queries the authenticated user's 3 most recently added restaurants and renders four decision-mode cards plus a recently-added restaurants card. Delete the old plain Blade view and re-point the route at the new page component.

## Context
- **Create new file:** `resources/views/pages/⚡dashboard.blade.php` (the `⚡` prefix and `pages/` directory are required — page components are auto-discovered ONLY from `resources/views/pages/`).
- **Delete old file:** `resources/views/dashboard.blade.php`.
- **Update route** in `routes/web.php`: change `Route::view('dashboard', 'dashboard')->name('dashboard')` to `Route::livewire('dashboard', 'pages::dashboard')->name('dashboard')`. Keep the `dashboard` route name and its position inside the `['auth', 'verified']` middleware group.
- **Do NOT wrap the template in `<x-layouts::app>`.** Page components apply the default app layout automatically. Supply the page title via `#[Title('Dashboard')]` on the component class (matches `⚡pick.blade.php`). The Blade template must have a single root element.
- Pattern to follow: `resources/views/pages/⚡pick.blade.php` and `resources/views/pages/restaurants/⚡index.blade.php` (Volt SFC: `new #[Title(...)] class extends Component { ... }; ?>` followed by single-root Blade).
- Mode routes: `pick`, `tonight`, `quiz`, `tournament` (link with `wire:navigate`).
- Mode icons (match sidebar): Quick Pick → `bolt`, Tonight → `calendar`, Quiz → `question-mark-circle`, Tournament → `trophy`
- Mode descriptions:
  - Quick Pick: "Weather-aware, one-tap pick from your favorites"
  - Tonight: "Find a spot with something happening tonight"
  - Quiz: "Answer 5 questions to find your best match"
  - Tournament: "Head-to-head bracket until one winner remains"
- Restaurant query in a `#[Computed]` method returning `Collection<int, Restaurant>`: `Restaurant::ownedBy(Auth::user())->latest()->limit(3)->get()`. Note `scopeOwnedBy(Builder, User)` requires a `User` instance (use `Auth::user()`, not `Auth::id()`). `latest()` orders by `created_at` desc.
- Restaurant create route: `restaurants.create`; index route: `restaurants.index`
- Flux components: prefer plain `<div>` + Tailwind containers as in `⚡pick`/`⚡index`, plus `flux:heading`, `flux:text`, `flux:button`, `flux:icon`. Do NOT assume `flux:card` exists in Flux UI Free — verify it is available before using it; otherwise use a bordered `<div>` like the existing SFCs.

## Requirements (Test Descriptions)
- [ ] `it renders the dashboard for an authenticated user` (assert via `$this->get(route('dashboard'))->assertOk()` — proves the new `Route::livewire` registration works end-to-end)
- [ ] `it redirects guests to login` (preserve existing DashboardTest behavior under the new route)
- [ ] `it displays the quick pick mode card with a link to the pick route`
- [ ] `it displays the tonight mode card with a link to the tonight route`
- [ ] `it displays the quiz mode card with a link to the quiz route`
- [ ] `it displays the tournament mode card with a link to the tournament route`
- [ ] `it shows up to 3 recently added restaurants ordered by newest first` (use a Livewire/Volt component test — e.g. `Livewire::test('pages::dashboard')` — and assert the rendered restaurant names appear in newest-first order; control `created_at` explicitly in factories since several rows may share a timestamp)
- [ ] `it does not show more than 3 restaurants on the dashboard` (create 4+ restaurants, assert the 4th-oldest name is absent)
- [ ] `it only shows the authenticated user's restaurants, not other users'` (scope check on `ownedBy`)
- [ ] `it shows placeholder copy and add restaurant link when the user has no restaurants`
- [ ] `it shows the add restaurant link alongside existing restaurants`

## Acceptance Criteria
- All requirements have passing tests
- New file `resources/views/pages/⚡dashboard.blade.php` exists; old `resources/views/dashboard.blade.php` is deleted
- `routes/web.php` uses `Route::livewire('dashboard', 'pages::dashboard')->name('dashboard')`, kept inside the `['auth', 'verified']` group
- Dashboard is a valid page-component Volt SFC (inline class + single-root Blade), with NO `<x-layouts::app>` wrapper and title via `#[Title('Dashboard')]`
- Existing `tests/Feature/DashboardTest.php` still passes unchanged (route name preserved)
- Mobile-first layout: mode cards in a 2-column grid, restaurants card full-width below
- No placeholder content remains (`x-placeholder-pattern` removed)
- Pint passes with no violations
