<?php

use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds ten restaurants without errors', function () {
    $this->seed(RestaurantSeeder::class);

    expect(Restaurant::count())->toBe(10);
});

it('assigns all seeded restaurants to the owner user', function () {
    $this->seed(RestaurantSeeder::class);

    $owner = User::where('email', 'test@example.com')->firstOrFail();

    expect(Restaurant::where('owner_user_id', $owner->id)->count())->toBe(10);
});

it('returns only the owner user\'s restaurants via the relationship', function () {
    $this->seed(RestaurantSeeder::class);

    $owner = User::where('email', 'test@example.com')->firstOrFail();
    $otherUser = User::factory()->create();

    expect($owner->restaurants()->count())->toBe(10)
        ->and($otherUser->restaurants()->count())->toBe(0);
});

it('returns an empty collection for a user with no restaurants', function () {
    $user = User::factory()->create();

    expect($user->restaurants()->get())->toBeEmpty();
});

it('can filter restaurants by owner_user_id using the ownedBy scope', function () {
    $this->seed(RestaurantSeeder::class);

    $owner = User::where('email', 'test@example.com')->firstOrFail();
    $otherUser = User::factory()->create();

    expect(Restaurant::ownedBy($owner)->count())->toBe(10)
        ->and(Restaurant::ownedBy($otherUser)->count())->toBe(0);
});

it('is idempotent — running the seeder twice produces ten rows, not twenty', function () {
    $this->seed(RestaurantSeeder::class);
    $this->seed(RestaurantSeeder::class);

    expect(Restaurant::count())->toBe(10);
});
