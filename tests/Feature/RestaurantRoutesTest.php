<?php

use App\Models\Restaurant;
use App\Models\User;

it('redirects guests to login when visiting /restaurants', function () {
    $this->get('/restaurants')
        ->assertRedirect('/login');
});

it('redirects guests to login when visiting /restaurants/create', function () {
    $this->get('/restaurants/create')
        ->assertRedirect('/login');
});

it('returns 200 for authenticated users on /restaurants', function () {
    $this->actingAs(User::factory()->create())
        ->get('/restaurants')
        ->assertOk();
});

it('returns 200 for authenticated users on /restaurants/create', function () {
    $this->actingAs(User::factory()->create())
        ->get('/restaurants/create')
        ->assertOk();
});

it('redirects guests to login when visiting /restaurants/{id}', function () {
    $restaurant = Restaurant::factory()->create();

    $this->get(route('restaurants.show', $restaurant))
        ->assertRedirect(route('login'));
});

it('redirects guests to login when visiting /restaurants/{id}/edit', function () {
    $restaurant = Restaurant::factory()->create();

    $this->get(route('restaurants.edit', $restaurant))
        ->assertRedirect(route('login'));
});

it('returns 200 for an authenticated owner on the show route', function () {
    $owner = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('restaurants.show', $restaurant))
        ->assertOk();
});

it('returns 200 for an authenticated owner on the edit route', function () {
    $owner = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('restaurants.edit', $restaurant))
        ->assertOk();
});

it('returns 403 for a non-owner on the show route', function () {
    $owner = User::factory()->create();
    $nonOwner = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs($nonOwner)
        ->get(route('restaurants.show', $restaurant))
        ->assertForbidden();
});

it('returns 403 for a non-owner on the edit route', function () {
    $owner = User::factory()->create();
    $nonOwner = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs($nonOwner)
        ->get(route('restaurants.edit', $restaurant))
        ->assertForbidden();
});
