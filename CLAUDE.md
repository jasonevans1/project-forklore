# Forklore

A mobile-first Laravel app that ends the "I don't know, what do you want?" conversation. Built for couples — every flow resolves to **one restaurant**, never a list.

## Four Decision Modes
- **Quick Pick** — One tap. Weather-aware, time-aware, skips recently visited places.
- **Something Happening Tonight** — Filters to restaurants with live events starting soon.
- **Guided Quiz** — 5 questions scored against favorites, returns the best match.
- **Tournament** — Head-to-head bracket of 4 or 8 favorites until one wins.

## Tech Stack
- **Laravel 13** · PHP 8.4 · Livewire 4 · Flux UI 2 · Fortify v1
- **Frontend**: Tailwind CSS 4, Vite 8
- **Testing**: Pest 4 · **Lint**: Laravel Pint

## Commands
```bash
php artisan test --compact --parallel   # run all tests (default)
php artisan test --compact              # sequential (debugging only)
vendor/bin/pint --dirty --format agent  # fix PHP style after edits
composer run dev                        # start full dev stack
```

## Key Rules
- Every flow must end with exactly ONE restaurant result
- Mobile-first: all Livewire components must be usable on small screens
- Google Places API: cache aggressively, enforce daily quota cap
- OpenWeather API: cache per location + time window
- Use Actions for single-purpose operations, Service classes for multi-model logic
- Always run Pint after editing PHP: `vendor/bin/pint --dirty --format agent`
- Use `php artisan make:` commands; pass `--no-interaction`
- Descriptive names: `isRecentlyVisited` not `visited()`

## Detailed Configuration
- `.claude/testing.md` — test commands, TDD methodology, conventions

---

# Project Overview

## Project Name
Forklore

## Description
A mobile-first Laravel app that ends the "I don't know, what do you want?" conversation. Built for couples who want to pick a restaurant without scrolling through endless lists or debating options. Every path through the app ends with **one restaurant**, not five.

## Core Philosophy
- Always resolve to a single result — never present a list to choose from
- Decisions should feel fun, not like work
- Mobile-first: thumb-friendly, fast, works in low-attention moments

## Tech Stack
- **Language**: PHP 8.4
- **Framework**: Laravel 13
- **Realtime UI**: Livewire 4 + Flux UI 2
- **Auth**: Laravel Fortify v1
- **Frontend**: Tailwind CSS 4, Vite 8
- **Testing**: Pest 4 + pest-plugin-laravel
- **Linting**: Laravel Pint 1
- **Database**: SQLite (local), configurable

## Project Type
Mobile-first web app (couples/consumer)

## Four Decision Modes

| Mode | Description |
|------|-------------|
| **Quick Pick** | One tap. Weather-aware, time-aware, skips recently visited places. Best for "we're hungry, decide now." |
| **Something Happening Tonight** | Filters to restaurants with trivia, live music, bingo, or specials starting in the next few hours. |
| **Guided Quiz** | Five quick questions (energy, hunger, new vs. familiar, distance, cuisine) that score favorites and return the best match. |
| **Tournament** | Head-to-head bracket of 4 or 8 favorites. Tap the winner of each matchup until one remains. For when you want to play, not decide. |

## What makes it different
Weather and season aware. A 72°F clear evening surfaces patio spots. An 18°F snowstorm hides food trucks and rooftops, boosts cozy indoor favorites. The recommendation card tells you why.

Mixed sources, one list. Your curated favorites live alongside Google Places results when you need to branch out. Any Places discovery you like can be promoted to a favorite in one tap.

User-maintained events. Add recurring events (Wednesday trivia at Smash Park) or one-offs (Valentine's prix fixe) to any restaurant. Share them with other users of the app, or keep them private.

Paralysis-resistant UX. No list views at decision time. No "top 5" screens. Swipe to reject, tap to commit. The app tracks visits to rotate your favorites and surface places you've been neglecting.

Turn-taking. Toggle whose turn it is to pick — the app subtly biases toward the other person's tagged preferences so the same three restaurants don't dominate.

## Key Integrations
- **OpenWeather API** — real-time weather context (used in Quick Pick to factor in conditions)
- **Google Places API** — restaurant discovery; aggressively cached, enforced daily quota cap to control costs

---

# Architecture

## Directory Structure

```
app/
├── Actions/          # Single-purpose action classes
│   └── Fortify/      # Fortify auth action overrides
├── Concerns/         # Shared traits
├── Http/
│   └── Controllers/  # HTTP request handlers (thin, delegate to actions/services)
├── Livewire/         # Livewire components (mobile-first UI)
│   └── Actions/      # Livewire action methods
├── Models/           # Eloquent models
└── Providers/        # Service providers

tests/
├── Unit/             # Unit tests (mirror app/ structure)
└── Feature/          # Integration/feature tests
    ├── Auth/
    └── Settings/
```

## Patterns Used

- **Actions** — single-purpose classes for discrete operations (e.g., `CreateRestaurant`, `ScoreQuizResult`). All Fortify auth hooks live in `app/Actions/Fortify/`.
- **Service classes** — business logic that spans multiple models or external APIs (e.g., `PlacesService`, `WeatherService`).
- Livewire components handle UI state server-side; Alpine.js for lightweight client interactions.

## Decision Modes — Conceptual Flow

Each mode takes a user's favorites/preferences and resolves to **exactly one restaurant**:

| Mode | Key Logic |
|------|-----------|
| Quick Pick | Score favorites by weather suitability + time of day + recency penalty |
| Something Happening Tonight | Filter by active events in next N hours via Places API |
| Guided Quiz | 5-question weighted scoring against favorite attributes |
| Tournament | Bracket elimination — N favorites → 1 winner via user taps |

## External Integrations

- **OpenWeather API** — called per location + time window, cached in Laravel cache. Used by Quick Pick to adjust scores based on conditions (rainy → prefer indoor; sunny → prefer patios).
- **Google Places API** — restaurant discovery and detail fetches. Must be aggressively cached (restaurant data doesn't change hourly). A daily quota cap is enforced — design features to work within the budget, never assume unlimited calls.

## Key Conventions
- Mobile-first: every Livewire component must be usable on a small phone screen
- Every user-facing flow ends with one result — never expose a list for the user to pick from
- Prefer server-side state in Livewire; keep Alpine.js for animations and micro-interactions only

---

# Code Standards

## Style Guide
PSR-12 enforced by Laravel Pint

## Linting

```bash
# Auto-fix (run after modifying any PHP file)
vendor/bin/pint --dirty --format agent

# Check only (CI gate)
vendor/bin/pint --parallel --test
```

## Pre-commit Checks
- Run Pint fix (`vendor/bin/pint --dirty --format agent`) before finalizing any PHP changes
- All tests must pass
- Never skip hooks (`--no-verify`)

## Naming Conventions
- Classes: `PascalCase`
- Methods/Variables: `camelCase`, descriptive (`isRecentlyVisited`, not `visited()`)
- Constants: `SCREAMING_SNAKE_CASE`
- Enum keys: `TitleCase` (`QuickPick`, `GuidedQuiz`)
- Files: match class name

## PHP Standards
- PHP 8 constructor property promotion: `public function __construct(public PlacesService $places) {}`
- Explicit return type declarations on all methods
- Type hints on all parameters; use `?Type` for nullable
- Curly braces on all control structures, even single-line bodies
- PHPDoc blocks with array shape types for complex structures — prefer over inline comments

## Architecture Rules
- Use `php artisan make:` commands to create new files
- Pass `--no-interaction` to all Artisan commands
- Prefer named routes and `route()` for URL generation
- One class per file; stick to the existing directory structure

## API Integration Standards
- Google Places API results must be cached aggressively; never call the API on every request
- Enforce daily quota cap on Places API — design around budget, not unlimited calls
- OpenWeather API calls should be cached per location + time window

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
