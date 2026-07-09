# Task 003: Seed Accurate Values for Des Moines Restaurants

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Update `RestaurantSeeder` so every one of the 10 seeded Des Moines restaurants gets accurate `service_level`, `service_options`, and `primary_cuisine` values, and add tests confirming all three fields are populated for every seeded restaurant.

## Context
- Related files: `database/seeders/RestaurantSeeder.php`, `tests/Feature/RestaurantSeederTest.php`
- Run all php/artisan commands via `ddev exec`
- Use the enum cases (e.g., `ServiceLevel::Casual`), matching how the seeder already uses `PatioQuality::Decent`
- Values to assign (best-effort real-world accuracy):

| Restaurant | service_level | service_options | primary_cuisine |
|---|---|---|---|
| Fong's Pizza | casual | dine_in, takeout, delivery | pizza |
| Exile Brewing Co | casual | dine_in, takeout | american |
| Centro | upscale_casual | dine_in, takeout | italian |
| Zombie Burger | fast_casual | dine_in, takeout, delivery | american |
| Django | upscale_casual | dine_in, takeout | other |
| Proof | fine_dining | dine_in | mediterranean |
| ARC Restaurant | upscale_casual | dine_in, takeout | american |
| El Bait Shop | casual | dine_in, takeout | bar_food |
| Zanzibar's Coffee Adventure | fast_casual | dine_in, takeout | cafe |
| Eatery A | upscale_casual | dine_in, takeout | asian_general |

## Requirements (Test Descriptions)
- [x] `it populates service_level for every seeded restaurant`
- [x] `it populates service_options with at least one valid option for every seeded restaurant`
- [x] `it populates primary_cuisine for every seeded restaurant`
- [x] `it assigns the expected classification to known restaurants`

## Acceptance Criteria
- All requirements have passing tests
- Seeder runs cleanly (`ddev exec php artisan db:seed --class=RestaurantSeeder --no-interaction` on a fresh DB)
- Code follows code standards (Pint clean)

## Implementation Notes
(Left blank - filled in by programmer during implementation)
