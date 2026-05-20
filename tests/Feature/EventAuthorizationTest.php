<?php

use App\Models\Event;
use App\Models\Restaurant;
use App\Models\User;

it('redirects guests to login when accessing events index', function () {
    $restaurant = Restaurant::factory()->create();

    $this->get(route('restaurants.events.index', $restaurant))
        ->assertRedirect(route('login'));
});

it('redirects guests to login when accessing the create event page', function () {
    $restaurant = Restaurant::factory()->create();

    $this->get(route('restaurants.events.create', $restaurant))
        ->assertRedirect(route('login'));
});

it('redirects guests to login when accessing the edit event page', function () {
    $restaurant = Restaurant::factory()->create();
    $event = Event::factory()->create(['restaurant_id' => $restaurant->id]);

    $this->get(route('restaurants.events.edit', [$restaurant, $event]))
        ->assertRedirect(route('login'));
});

it('forbids a non-owner from viewing the events index', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs($other)
        ->get(route('restaurants.events.index', $restaurant))
        ->assertForbidden();
});

it('forbids a non-owner from accessing the create event page', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs($other)
        ->get(route('restaurants.events.create', $restaurant))
        ->assertForbidden();
});

it('forbids a non-owner from accessing the edit event page', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
    $event = Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $owner->id,
    ]);

    $this->actingAs($other)
        ->get(route('restaurants.events.edit', [$restaurant, $event]))
        ->assertForbidden();
});

it('allows the owner to access all three event routes', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    $event = Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('restaurants.events.index', $restaurant))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('restaurants.events.create', $restaurant))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('restaurants.events.edit', [$restaurant, $event]))
        ->assertOk();
});
