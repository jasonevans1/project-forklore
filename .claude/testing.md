# Testing Configuration

## Test Framework
Pest 4 with pest-plugin-laravel

## TDD Methodology

Each task follows strict Red → Green → Refactor:

1. Write failing test for one requirement
2. Write minimum code to pass
3. Refactor while tests stay green
4. Repeat for next requirement
5. Commit when task complete

## Commands

```bash
# Run all tests (parallel — default)
php artisan test --compact --parallel

# Run all tests (sequential, for debugging failures)
php artisan test --compact

# Run specific test file
php artisan test --compact tests/Feature/SomeTest.php

# Run with filter
php artisan test --compact --filter=testName

# Lint check (CI gate)
vendor/bin/pint --parallel --test
```

## Parallel Execution
- **Default**: Always run tests in parallel unless debugging a specific failure
- Parallel command: `php artisan test --compact --parallel`
- Sequential fallback: `php artisan test --compact` (use only when parallel causes flaky failures)

## Test File Locations
- Unit tests: `tests/Unit/` (mirror `app/` structure)
- Feature/Integration tests: `tests/Feature/`

## Coverage Requirements
- Minimum: 80%
- New code must have tests

## Test Naming Convention
- Test files: `{DescriptiveName}Test.php`
- Test methods: `it('does something descriptive')`
- Create tests with: `php artisan make:test --pest {Name}Test`

## E2E Tests (Playwright)

Browser-level tests for critical user flows — decision modes, auth, and mobile interactions.

```bash
# Run all e2e tests
npx playwright test

# Run a specific file
npx playwright test tests/e2e/quick-pick.spec.ts

# Run headed (watch the browser)
npx playwright test --headed

# Run on mobile viewport (primary target)
npx playwright test --project=mobile

# Open interactive UI
npx playwright test --ui
```

### E2E File Locations
- `tests/e2e/` — all Playwright specs

### E2E Conventions
- Cover the golden path for each decision mode (Quick Pick, Quiz, Tournament, Something Happening Tonight)
- Always test on mobile viewport first — this is a mobile-first app
- Mock external APIs (OpenWeather, Google Places) in e2e tests to avoid quota usage and flakiness
- Use `page.getByRole()` and `page.getByText()` over CSS selectors for resilience

## Key Rules
- Use model factories for test data; check for existing factory states before manually setting attributes
- Do not create verification scripts or tinker when tests cover that functionality
- Do NOT delete tests without approval
- Run `php artisan test --compact` with a specific filename or filter to stay fast
