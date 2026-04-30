# Devil's Advocate Review: restaurant-model-foundation

## Critical (Must fix before building)

### C1. Migration column list is not enumerated — Task 001 will fail or drift
Task 001 says "all specified columns" but no canonical column list exists in any task or in `_plan.md`. Workers will guess. Test descriptions and the factory in Task 002 imply: `name`, `lat`, `lng`, `cuisine_tags` (json), `vibe_tags` (json), `price_level`, `source`, `patio_quality`, `indoor_vibe_when_cold`, `avg_duration_minutes`, `last_visited_at`, `visit_count`, `owner_user_id`. Task 003's seeder hardcodes street **addresses** but no `address` column is implied anywhere in 001 or 002. This is a hard contract gap that will block Task 003.

**Fix**: Enumerate the full migration schema (column name, type, nullability, default) directly in Task 001. Add an `address` column. Decide whether to include `place_id` (string, nullable, unique) for forward-compat with `source = places` rows.

### C2. `php artisan make:enum` does not exist in stock Laravel 13
Task 001 says `php artisan make:enum RestaurantSource --no-interaction` "if artisan supports it." It doesn't ship in stock Laravel 13 (that command is provided by some third-party packages, not present here). Workers will hit a "command not found" and either skip enum creation or stall.

**Fix**: Replace with explicit instruction to create the three backed enum files manually (or via `php artisan make:class App/Enums/RestaurantSource` and edit). Provide the exact case/value mapping (TitleCase keys → lowercase string values).

### C3. Tests touching the `restaurants` table need `RefreshDatabase`, but Pest.php has it commented out
`tests/Pest.php` has `// ->use(RefreshDatabase::class)` (commented). Existing feature tests work because each parallel test process gets its own `:memory:` SQLite DB and migrations run once per process. Within one process, however, multiple tests will share state. New tests that insert/seed restaurants must explicitly `uses(RefreshDatabase::class)` or seed/cleanup will leak across tests. Task 003 mentions "must use `RefreshDatabase`" but Task 001 (factory- and DB-touching tests) does not.

**Fix**: Add explicit `uses(Illuminate\Foundation\Testing\RefreshDatabase::class)` instruction to test files in Task 001 and Task 003.

## Important (Should fix before building)

### I1. `User::restaurants()` hasMany relationship must be added to `app/Models/User.php`
Task 001 mentions adding the relationship but does not list `app/Models/User.php` in "Related files" as a file to modify. The User model uses the `#[Fillable]` attribute pattern with no existing relationships, so the worker must add the method. Without explicit mention, a worker may only define `belongsTo` on `Restaurant` and miss the `hasMany` on `User`, which is required by Task 003's tests.

**Fix**: List `app/Models/User.php` as a file to modify in Task 001 with explicit instructions to add `public function restaurants(): HasMany`.

### I2. Enum key/value mapping is not specified
The `_plan.md` says "use `string` column type" and lists lowercase values (`favorite`, `places`, `none`, `decent`, etc.), and code-standards.md says "Enum keys: TitleCase". But the plan never spells out the exact key→value mapping. Different workers may write `case Favorite = 'Favorite'` vs `case Favorite = 'favorite'`, and tests in Task 001 (`it casts source to the RestaurantSource enum`) will pass either way — the bug surfaces later when the seeder writes `'favorite'` and the model can't hydrate.

**Fix**: Specify in Task 001:
- `RestaurantSource`: `Favorite = 'favorite'`, `Places = 'places'`
- `PatioQuality`: `None = 'none'`, `Decent = 'decent'`, `Destination = 'destination'`
- `IndoorVibe`: `Cozy = 'cozy'`, `Neutral = 'neutral'`, `Sterile = 'sterile'`

### I3. `address` column missing — Task 003 will break compile
Task 003 hardcodes street addresses for 10 Des Moines restaurants but no `address` column is defined in Task 001's schema. Either drop the addresses from the seeder or add `address` (string, nullable) to the migration.

**Fix**: Add `address` (string, nullable) to the migration in Task 001 and to the factory defaults in Task 002.

### I4. Seeder relies on `test@example.com` user existing — fragile
Task 003 says "Assign all 10 to a single seeded user (`test@example.com` from `DatabaseSeeder`)." But:
- If `RestaurantSeeder` is called standalone (`php artisan db:seed --class=RestaurantSeeder`), the user won't exist.
- The feature test in Task 003 uses `$this->seed(RestaurantSeeder::class)` — which will fail because no user exists.
- Order matters in `DatabaseSeeder`: must create user before calling RestaurantSeeder.

**Fix**: Use `User::firstOrCreate(['email' => 'test@example.com'], [...])` inside `RestaurantSeeder::run()` so it is self-sufficient. Or have the seeder call `User::factory()->create()` if no test user found.

### I5. Query scope contract gap between Task 001 and Task 003
Task 003 has the requirement `it can filter restaurants by owner_user_id using a query scope`, but Task 001 does not define a scope on the `Restaurant` model. A worker building Task 003 will be blocked or will define the scope themselves (which arguably belongs in Task 001 with the model definition).

**Fix**: Either add `scopeOwnedBy(Builder $query, User $user)` to Task 001's requirements, or rewrite the Task 003 requirement to not assume a scope (use `Restaurant::where('owner_user_id', $user->id)`).

### I6. Task 001 test for `last_visited_at` casting needs explicit data
Requirement `it casts last_visited_at as a datetime` cannot be verified against factory defaults if the factory leaves it null. The test must explicitly set `last_visited_at` to a datetime string and assert hydration as a `Carbon`/`DateTimeInterface`.

**Fix**: Add a note in Task 001's Context that this test must construct the model with an explicit `last_visited_at` value.

### I7. Task 002 factory imports must be specified
The factory needs `use App\Models\User; use App\Enums\RestaurantSource; use App\Enums\PatioQuality; use App\Enums\IndoorVibe;` plus `use App\Models\Restaurant;`. The task should list these to prevent missing-import errors during TDD.

**Fix**: Add an explicit imports block to Task 002's Context.

## Minor (Nice to address)

### M1. `visit_count` default-zero requirement should specify migration default
The test `it defaults visit_count to zero` will pass only if either (a) the migration sets `->default(0)` or (b) the model's `$attributes` array sets it. Specify in Task 001 to use `->default(0)` in the migration for predictability.

### M2. `timestamps()` not explicitly listed in migration
Standard Laravel convention but worth listing alongside other columns for completeness.

### M3. Soft deletes / archival
Not mentioned. Probably fine for v1, but worth noting that a future "remove from favorites without losing history" feature will require soft deletes. Adding `softDeletes()` now is cheap insurance.

### M4. Index on `owner_user_id`
`foreignId('owner_user_id')->constrained()` creates the FK but on SQLite the implicit index behavior varies. Add `->index()` explicitly or rely on `constrained()` (Laravel 13 adds an index for foreign keys by default on most drivers). Worth documenting expectation.

### M5. Pint format check
Reminder: `vendor/bin/pint --dirty --format agent` must run after each task. Already in code-standards.md, but worth referencing in each task's Acceptance Criteria for clarity (currently says "Pint clean" — fine but could be sharper).

## Questions for the Team

### Q1. Should `place_id` be added now?
The `source = 'places'` enum value implies Google Places-sourced rows. Without a `place_id` column, deduplicating Places results against existing rows is impossible. Adding it now (nullable, unique-when-not-null) is cheap. Defer or include?

### Q2. Should `address` be required or split into structured fields?
Single `address` string vs. `street`, `city`, `state`, `zip`? Single string is simpler; structured supports filtering but adds migration complexity. Recommend a single nullable string for v1.

### Q3. Should the Pest `RefreshDatabase` trait be enabled globally?
Currently commented in `tests/Pest.php`. Enabling globally for `Feature` would simplify all DB-touching tests across the project. Out of scope here but worth a team decision soon.

### Q4. Should the seeder be guarded against double-runs?
If `php artisan db:seed` is run twice, current spec creates 10 more rows each time. Use `firstOrCreate` keyed on `(owner_user_id, name)` to make it idempotent?
