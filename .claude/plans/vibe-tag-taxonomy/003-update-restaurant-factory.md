# Task 003: Update RestaurantFactory and RestaurantSeeder to Use Valid Vibe Tags

**Status**: completed
**Depends on**: [001]
**Retry count**: 0

## Description
Update `RestaurantFactory` and `RestaurantSeeder` so their default `vibe_tags` only use the new taxonomy. Currently both include `'romantic'` (and the seeder also has `'seasonal'`) which will not be valid tags once validation enforces the taxonomy. This prevents factory-created restaurants and seeded data from drifting out of sync with the canonical taxonomy.

## Context
- Files to update:
  - `database/factories/RestaurantFactory.php`
  - `database/seeders/RestaurantSeeder.php`
- Factory current default: `fake()->randomElement(['romantic', 'casual', 'lively', 'quiet'])`
- Factory must become a value from the taxonomy: energy (lively, quiet, moderate), occasion (casual, date_night, special_occasion, quick), experience (cozy, trendy, classic, adventurous)
- Recommended: `fake()->randomElement(\Illuminate\Support\Arr::flatten(config('vibes')))` so the factory automatically adopts any future taxonomy additions
- Seeder current usages: `['romantic']`, `['casual']`, `['lively']`, `['seasonal']`, `['romantic', 'cozy']`. Map them to the closest valid taxonomy values:
  - `romantic` → `date_night` (intent matches "occasion")
  - `seasonal` → `trendy` (closest experience match) or `casual` — choose what reads best for the seed restaurant
  - `casual`, `lively`, `cozy` → already valid, leave as-is
- Validation does not run when factories or seeders persist directly (they bypass Livewire), but keeping seed data in-taxonomy avoids confusing developers and ensures seeded restaurants display sensibly in the chip-picker on edit.

## Requirements (Test Descriptions)
- [ ] `it creates a restaurant with vibe_tags entries that all exist in the vibes taxonomy` (factory)
- [ ] `it creates a restaurant whose vibe_tags are stored as an array` (factory)
- [ ] `it seeds restaurants with vibe_tags that all exist in the vibes taxonomy` (seeder — run the seeder, then assert every persisted vibe_tag is in `Arr::flatten(config('vibes'))`)

## Acceptance Criteria
- All requirements have passing tests
- Factory no longer generates 'romantic' or any other out-of-taxonomy tag
- Seeder no longer hard-codes 'romantic' or 'seasonal'
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
