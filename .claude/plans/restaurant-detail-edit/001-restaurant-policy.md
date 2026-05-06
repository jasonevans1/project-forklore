# Task 001: RestaurantPolicy – Ownership Authorization

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create a `RestaurantPolicy` that gates `view`, `update`, and `delete` operations to the restaurant's `owner_user_id`. Laravel 13 auto-discovers policies by convention so no manual registration is needed.

## Context
- Related files: `app/Models/Restaurant.php`, `app/Policies/RestaurantPolicy.php` (new), `app/Models/User.php`
- Patterns to follow: Laravel policy conventions; existing model uses `owner_user_id` (not `user_id`) for ownership
- The policy will be consumed by the show and edit Volt components via `$this->authorize()` and by the destroy action
- `viewAny` is not needed (the index already uses `scopeOwnedBy` to filter)

## Requirements (Test Descriptions)
- [ ] `it allows the owner to view their own restaurant`
- [ ] `it denies a non-owner from viewing a restaurant`
- [ ] `it allows the owner to update their own restaurant`
- [ ] `it denies a non-owner from updating a restaurant`
- [ ] `it allows the owner to delete their own restaurant`
- [ ] `it denies a non-owner from deleting a restaurant`

## Acceptance Criteria
- All requirements have passing tests
- Policy class at `app/Policies/RestaurantPolicy.php`
- No AuthServiceProvider changes required (auto-discovery)
- Code follows project standards

## Implementation Notes
- Method signatures: `view(User $user, Restaurant $restaurant): bool`, `update(User $user, Restaurant $restaurant): bool`, `delete(User $user, Restaurant $restaurant): bool`. Each returns `$user->id === $restaurant->owner_user_id`.
- Auth middleware on the routes ensures `$user` is never null at policy invocation time.
- Run `vendor/bin/pint --dirty --format agent` after creating the policy.
