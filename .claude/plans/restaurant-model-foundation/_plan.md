# Plan: Restaurant Model Foundation

## Created
2026-04-28

## Status
completed

## Objective
Create the core `restaurants` table migration and `Restaurant` Eloquent model with all schema fields, proper casts, factory, and a Des Moines seeder — establishing the data foundation every decision mode depends on.

## Related Issues
none

## Scope

### In Scope
- `create_restaurants_table` migration with all specified fields (full schema below)
- `Restaurant` Eloquent model with casts, fillable, `belongsTo(User)`, and `scopeOwnedBy()` query scope
- `User::restaurants()` `hasMany` relationship added to `app/Models/User.php`
- Three backed PHP enums in `app/Enums/`: `RestaurantSource`, `PatioQuality`, `IndoorVibe`
- `RestaurantFactory` with sensible defaults
- `RestaurantSeeder` (self-sufficient, idempotent) with 10 realistic Des Moines area restaurants
- `DatabaseSeeder` wired to call `RestaurantSeeder`
- Feature test: seeder runs without error and restaurants can be filtered by owner via `ownedBy` scope

### Out of Scope
- Any decision-mode logic (Quick Pick, Quiz, etc.)
- Google Places API integration (but schema includes hooks via the `source` enum)
- Front-end / Livewire components
- Restaurant CRUD controllers or routes
- Soft deletes (deferred — see Q3 in `_devils_advocate.md`)

## Success Criteria
- [ ] Migration runs cleanly with `php artisan migrate` on SQLite
- [ ] All enum columns hydrate to backed PHP enums
- [ ] JSON columns cast to arrays automatically
- [ ] `User::restaurants()` returns only that user's restaurants
- [ ] `Restaurant::ownedBy($user)` query scope returns only that user's restaurants
- [ ] Seeder creates 10 rows with valid Des Moines addresses
- [ ] Seeder is idempotent — running twice yields 10 rows, not 20
- [ ] Feature test passes confirming seeder + owner query
- [ ] All tests passing (`php artisan test --compact --parallel`)
- [ ] `vendor/bin/pint --dirty --format agent` produces no changes

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Migration + Restaurant model + 3 enums + User::restaurants() + ownedBy scope | - | pending |
| 002 | RestaurantFactory | 001 | pending |
| 003 | RestaurantSeeder (idempotent) + DatabaseSeeder wiring + feature test | 001, 002 | pending |

## Architecture Notes

### Migration schema (canonical column list)
| Column | Type | Notes |
|--------|------|-------|
| `id` | `id()` | primary key |
| `owner_user_id` | `foreignId` | `->constrained('users')->cascadeOnDelete()->index()` |
| `name` | `string` | required |
| `address` | `string` | nullable |
| `lat` | `decimal(10, 7)` | nullable |
| `lng` | `decimal(10, 7)` | nullable |
| `cuisine_tags` | `json` | cast to `array` |
| `vibe_tags` | `json` | cast to `array` |
| `price_level` | `unsignedTinyInteger` | nullable, range 1–4 (app-level validation) |
| `source` | `string` | cast to `RestaurantSource` enum |
| `patio_quality` | `string` | cast to `PatioQuality` enum, default `'none'` |
| `indoor_vibe_when_cold` | `string` | cast to `IndoorVibe` enum, default `'neutral'` |
| `avg_duration_minutes` | `unsignedSmallInteger` | nullable |
| `last_visited_at` | `timestamp` | nullable, cast to `datetime` |
| `visit_count` | `unsignedInteger` | `->default(0)` |
| `timestamps()` | — | created_at / updated_at |

### Enum value mapping (TitleCase keys, lowercase values)
- `App\Enums\RestaurantSource`: `Favorite = 'favorite'`, `Places = 'places'`
- `App\Enums\PatioQuality`: `None = 'none'`, `Decent = 'decent'`, `Destination = 'destination'`
- `App\Enums\IndoorVibe`: `Cozy = 'cozy'`, `Neutral = 'neutral'`, `Sterile = 'sterile'`

### Other notes
- Enums stored as `string` columns (SQLite-compatible for tests); Eloquent casts handle hydration.
- `cuisine_tags` and `vibe_tags` store as JSON → cast to `array`.
- `owner_user_id` is a foreign key to `users.id` with `cascadeOnDelete()`.
- Follow `User` model conventions: use the `#[Fillable]` attribute pattern; define `casts()` as a method (not the deprecated `$casts` property).
- `php artisan make:enum` does NOT exist in stock Laravel 13 — create enum files manually under `app/Enums/`.
- Tests touching the DB MUST `uses(Illuminate\Foundation\Testing\RefreshDatabase::class)` — `tests/Pest.php` keeps that trait commented out at the global level.

## Risks & Mitigations
- **Enum column portability**: use `string` column type — avoids SQLite vs MySQL ENUM divergence. Application-level validation via cast.
- **Seeder idempotency**: use `firstOrCreate` keyed on `(owner_user_id, name)` so re-running the seeder is safe and the test `it is idempotent` can verify this.
- **Schema mismatch between tasks**: full column list now lives in this plan and Task 001 — Task 002 (factory) and Task 003 (seeder) both reference it.
- **Tests sharing state**: each test file applies `RefreshDatabase` explicitly because the project's Pest bootstrap does not.
