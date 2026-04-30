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
