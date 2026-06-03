# Plan: Dashboard Mode Cards

## Created
2026-06-02

## Status
completed

## Objective
Replace the placeholder dashboard with a functional Volt SFC that presents all four decision modes as tappable cards and shows the user's 3 most recently added restaurants with a link to add more.

## Related Issues
none

## Discovery Notes
- `resources/views/dashboard.blade.php` is currently a plain Blade file with only placeholder bordered boxes — no PHP class, no real content. It is wrapped in `<x-layouts::app :title="__('Dashboard')">` and rendered via `Route::view('dashboard', 'dashboard')->name('dashboard')` in `routes/web.php`.
- All four mode pages exist and are reachable via named routes: `pick`, `tonight`, `quiz`, `tournament`. Their sidebar icons are `bolt`, `calendar`, `question-mark-circle`, `trophy`.
- **CRITICAL — page-component registration:** All non-auth Volt SFCs live in `resources/views/pages/` with the `⚡` filename prefix (e.g. `resources/views/pages/⚡pick.blade.php`) and are mounted by name via `Route::livewire('pick', 'pages::pick')`. Single-file page components are ONLY auto-discovered from the `resources/views/pages/` directory. A file at `resources/views/dashboard.blade.php` will NOT be registered as a page component, and `Route::view('dashboard', 'dashboard')` renders it as a plain Blade view — a `new class extends Component {}` block placed there will NOT be mounted as Livewire, so `#[Computed]` and `#[Title]` will not function. The original plan's claim "convert in-place, no route change needed" is wrong.
- **Layout difference:** Page components in `resources/views/pages/` do NOT wrap their template in `<x-layouts::app>`; the default app layout (`resources/views/layouts/app.blade.php`, the `components.layouts.app` default) is applied automatically. The current `dashboard.blade.php` wraps itself in `<x-layouts::app :title="...">`. When converting to a page component, the `<x-layouts::app>` wrapper must be removed and the title supplied via the `#[Title('Dashboard')]` attribute instead, matching `⚡pick.blade.php`.
- The Volt SFC template requires a SINGLE root element (see comment in `⚡pick.blade.php`).
- `Restaurant` model has `created_at` (standard Eloquent), `scopeOwnedBy(Builder, User)`, and a `name` field. `restaurants.create` and `restaurants.index` named routes exist. Note `scopeOwnedBy` requires a `User` instance — `Auth::user()`, not `Auth::id()`.
- Flux UI components (`flux:card`, `flux:button`, `flux:heading`, `flux:badge`, `flux:icon`, `flux:text`) are used throughout. Confirm `flux:card` exists in the installed Flux UI Free package before relying on it; existing SFCs (`⚡pick`, `⚡index`) use plain `<div>` containers with Tailwind, not `flux:card`.
- Mode cards should show: icon + title + short description. Recently added card shows up to 3 restaurants (ordered by `created_at` desc) plus an "Add restaurant" CTA.

## Scope

### In Scope
- Move the dashboard into the page-component convention: create `resources/views/pages/⚡dashboard.blade.php` as a Volt SFC with an inline PHP class, and delete `resources/views/dashboard.blade.php`
- Update `routes/web.php`: replace `Route::view('dashboard', 'dashboard')->name('dashboard')` with `Route::livewire('dashboard', 'pages::dashboard')->name('dashboard')`
- Four decision-mode cards (Quick Pick, Tonight, Quiz, Tournament), each with icon + title + description + tap-to-navigate (use `wire:navigate` links)
- Recently added restaurants card: up to 3 restaurants ordered by `created_at` desc, with "Add restaurant" link
- Pest feature tests covering all card content and restaurant queries
- Verify the existing `tests/Feature/DashboardTest.php` still passes (route name `dashboard` is preserved)

### Out of Scope
- Modifying any of the four mode pages themselves
- Adding stats or visit counts to the dashboard
- Pagination of the recently added list

## Success Criteria
- [ ] Dashboard shows 4 mode cards, each linking to the correct route
- [ ] Each mode card displays an icon, title, and description
- [ ] Recently added card shows up to 3 restaurants (newest first)
- [ ] "Add restaurant" link is always visible on the recently added card
- [ ] All tests passing
- [ ] Code follows project standards (Pint clean)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Create `pages/⚡dashboard` page component (mode cards + recently added), update route to `Route::livewire`, delete old view | - | completed |

## Architecture Notes
- Create the SFC at `resources/views/pages/⚡dashboard.blade.php` (NOT in-place at `resources/views/dashboard.blade.php`). Page components are only auto-discovered under `resources/views/pages/` with the `⚡` prefix.
- Delete the old `resources/views/dashboard.blade.php`.
- Update the route to `Route::livewire('dashboard', 'pages::dashboard')->name('dashboard')`.
- Do NOT wrap the template in `<x-layouts::app>`; supply the title via `#[Title('Dashboard')]` on the class. The default app layout is applied automatically (matches `⚡pick.blade.php`).
- The Blade template must have a single root element.
- Use `#[Computed]` for the recently-added restaurants query: `Restaurant::ownedBy(Auth::user())->latest()->limit(3)->get()`. Reference it as `$this->recentRestaurants` in the template.
- Mode card grid: 2-column on mobile, matches thumb-friendly layout; recently added card below.
- Follow the Volt SFC pattern established in `resources/views/pages/⚡pick.blade.php` and `resources/views/pages/restaurants/⚡index.blade.php`.

## Risks & Mitigations
- **Page-component registration:** Volt/Livewire page components are auto-discovered only from `resources/views/pages/` with the `⚡` prefix. A file at `resources/views/dashboard.blade.php` would render as a plain Blade view (via `Route::view`) and the inline component class would never mount — `#[Computed]`/`#[Title]` would silently do nothing. Mitigation: place the file under `resources/views/pages/`, prefix it `⚡`, and switch the route to `Route::livewire(...)`.
- **Layout double-wrap / missing layout:** Page components rely on the automatic default layout. Keeping the `<x-layouts::app>` wrapper would double-wrap the sidebar/chrome; removing it without the page-component infrastructure would drop the layout entirely. Mitigation: remove the wrapper AND register as a page component so the default layout applies.
- **Route name stability:** `tests/Feature/DashboardTest.php` and `⚡pick.blade.php`'s `redirect(route('dashboard'))` depend on the `dashboard` route name. Keep `->name('dashboard')` intact when swapping to `Route::livewire`.
