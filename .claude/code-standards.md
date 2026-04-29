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
