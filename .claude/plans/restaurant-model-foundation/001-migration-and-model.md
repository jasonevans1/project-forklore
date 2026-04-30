# Task 001: Migration and Restaurant Model

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `restaurants` table migration with the full schema below, three PHP-backed string enums in `app/Enums/`, the `Restaurant` Eloquent model with casts and a `belongsTo(User)` relationship, and add the `restaurants(): HasMany` relationship + `scopeOwnedBy()` query scope so downstream tasks can build against a stable contract.

## Context
- Files to create or modify:
  - `database/migrations/{timestamp}_create_restaurants_table.php` (new)
  - `app/Models/Restaurant.php` (new)
  - `app/Models/User.php` (modify — add `restaurants()` hasMany)
  - `app/Enums/RestaurantSource.php` (new)
  - `app/Enums/PatioQuality.php` (new)
  - `app/Enums/IndoorVibe.php` (new)
- Patterns to follow: `User` model in `app/Models/User.php` — uses `#[Fillable]` attribute, `casts()` method, `HasFactory` trait
- Enum storage: use `string` column type (SQLite-compatible for tests). Backed enums with TitleCase keys → lowercase string values.
- JSON columns: `cuisine_tags`, `vibe_tags` → cast to `array`
- `price_level` stores 1–4 as an `unsignedTinyInteger`
- `owner_user_id` → `foreignId('owner_user_id')->constrained('users')->cascadeOnDelete()->index()`
- `php artisan make:enum` does NOT exist in stock Laravel 13 — create the enum files manually (or via `php artisan make:class App/Enums/RestaurantSource --no-interaction` and rewrite as `enum`).
- Run: `php artisan make:migration create_restaurants_table --no-interaction`
- Run: `php artisan make:model Restaurant --no-interaction`

### Migration schema (full column list)
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

### Enum value mapping (exact)
- `App\Enums\RestaurantSource`:
  - `case Favorite = 'favorite';`
  - `case Places = 'places';`
- `App\Enums\PatioQuality`:
  - `case None = 'none';`
  - `case Decent = 'decent';`
  - `case Destination = 'destination';`
- `App\Enums\IndoorVibe`:
  - `case Cozy = 'cozy';`
  - `case Neutral = 'neutral';`
  - `case Sterile = 'sterile';`

### Model expectations
- `Restaurant` extends `Model`, uses `HasFactory`.
- `#[Fillable]` attribute listing every writable column (mirrors `User` model pattern).
- `casts()` method returns:
  ```
  'cuisine_tags' => 'array',
  'vibe_tags' => 'array',
  'source' => RestaurantSource::class,
  'patio_quality' => PatioQuality::class,
  'indoor_vibe_when_cold' => IndoorVibe::class,
  'last_visited_at' => 'datetime',
  ```
- `user(): BelongsTo` relationship using `owner_user_id` foreign key.
- `scopeOwnedBy(Builder $query, User $user): Builder` — filters by `owner_user_id`.

### User model modification
- Add `public function restaurants(): HasMany { return $this->hasMany(Restaurant::class, 'owner_user_id'); }`
- Add the necessary `use Illuminate\Database\Eloquent\Relations\HasMany;` import.

### Test setup
- All tests in this task that touch the database MUST `uses(Illuminate\Foundation\Testing\RefreshDatabase::class);` at the top of the test file. The project's `tests/Pest.php` does NOT apply `RefreshDatabase` globally.
- For the `last_visited_at` cast test, explicitly construct the model with `last_visited_at = now()` (the factory does not set it by default).

## Requirements (Test Descriptions)
- [x] `it creates the restaurants table with all required columns`
- [x] `it stores and retrieves cuisine_tags as an array`
- [x] `it stores and retrieves vibe_tags as an array`
- [x] `it casts source to the RestaurantSource enum`
- [x] `it casts patio_quality to the PatioQuality enum`
- [x] `it casts indoor_vibe_when_cold to the IndoorVibe enum`
- [x] `it casts last_visited_at as a datetime`
- [x] `it defaults visit_count to zero`
- [x] `it returns the owning user via the user relationship`
- [x] `it returns a user's restaurants via the hasMany relationship`
- [x] `it scopes restaurants to a specific owner via scopeOwnedBy`

## Acceptance Criteria
- All requirements have passing tests
- `php artisan migrate:fresh` runs without error on SQLite (the project's test driver)
- Three enums exist in `app/Enums/` with the exact case/value mapping above
- `User::restaurants()` exists and returns a `HasMany` relation
- `Restaurant::ownedBy($user)` query scope works
- `vendor/bin/pint --dirty --format agent` produces no changes after the task

## Implementation Notes
- Created migration `2026_04_29_022521_create_restaurants_table.php` with full schema.
- Created three string-backed enums in `app/Enums/`: `RestaurantSource`, `PatioQuality`, `IndoorVibe`.
- Created `app/Models/Restaurant.php` using `#[Fillable]` attribute pattern, `HasFactory` trait, `casts()` method, `user(): BelongsTo`, and `scopeOwnedBy()` query scope.
- Updated `app/Models/User.php` to add `restaurants(): HasMany` relationship.
- Created `database/factories/RestaurantFactory.php` with sensible defaults.
- The `last_visited_at` cast test uses `Carbon\CarbonInterface` since Laravel 13 returns `CarbonImmutable` for datetime casts.
