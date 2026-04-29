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
