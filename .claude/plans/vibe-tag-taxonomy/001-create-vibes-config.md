# Task 001: Create config/vibes.php

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create `config/vibes.php` that defines the canonical vibe tag taxonomy in three named dimensions. This file is the single source of truth for all valid vibe tags throughout the app.

## Context
- New file: `config/vibes.php`
- The config must be easily flattened to a plain list of all valid tags (used for validation)
- Structure: top-level keys are dimension names, values are arrays of tag strings
- Dimensions and tags:
  - `energy`: lively, quiet, moderate
  - `occasion`: casual, date_night, special_occasion, quick
  - `experience`: cozy, trendy, classic, adventurous

## Requirements (Test Descriptions)
- [ ] `it loads the vibes config and returns an array keyed by dimension`
- [ ] `it defines an energy dimension with lively, quiet, and moderate`
- [ ] `it defines an occasion dimension with casual, date_night, special_occasion, and quick`
- [ ] `it defines an experience dimension with cozy, trendy, classic, and adventurous`
- [ ] `it can be flattened to a single list of all twelve valid tags`

## Acceptance Criteria
- All requirements have passing tests
- Config readable via `config('vibes')` and `config('vibes.energy')` etc.
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
