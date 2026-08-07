<?php

use App\Models\Restaurant;
use App\Models\User;

it('shows an empty state message when the user has no restaurants', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('restaurants.index'))
        ->assertSee('No restaurants yet');
});

it('lists each restaurant the authenticated user owns by name', function () {
    $user = User::factory()->create();

    Restaurant::factory()->create(['owner_user_id' => $user->id, 'name' => 'Pasta Palace']);
    Restaurant::factory()->create(['owner_user_id' => $user->id, 'name' => 'Sushi Garden']);

    $this->actingAs($user)
        ->get(route('restaurants.index'))
        ->assertSee('Pasta Palace')
        ->assertSee('Sushi Garden');
});

it('displays cuisine tags on each card', function () {
    $user = User::factory()->create();

    Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'cuisine_tags' => ['Italian', 'Pizza'],
    ]);

    $this->actingAs($user)
        ->get(route('restaurants.index'))
        ->assertSee('Italian')
        ->assertSee('Pizza');
});

it('displays the price level as dollar signs on each card', function () {
    $user = User::factory()->create();

    Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'price_level' => 3,
    ]);

    $this->actingAs($user)
        ->get(route('restaurants.index'))
        ->assertSee('$$$');
});

it('does not show restaurants owned by other users', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Restaurant::factory()->create(['owner_user_id' => $user->id, 'name' => 'My Restaurant']);
    Restaurant::factory()->create(['owner_user_id' => $otherUser->id, 'name' => 'Other Person Place']);

    $this->actingAs($user)
        ->get(route('restaurants.index'))
        ->assertSee('My Restaurant')
        ->assertDontSee('Other Person Place');
});

it('shows a link to the create restaurant page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('restaurants.index'))
        ->assertSee(route('restaurants.create'));
});

it('renders each restaurant using the ticket row component', function () {
    $user = User::factory()->create();

    Restaurant::factory()->create(['owner_user_id' => $user->id, 'name' => 'Pasta Palace']);

    $this->actingAs($user)
        ->get(route('restaurants.index'))
        ->assertSeeHtml('bg-ticket-bg');
});

it('links each row to the restaurant show page', function () {
    $user = User::factory()->create();

    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id, 'name' => 'Pasta Palace']);

    $this->actingAs($user)
        ->get(route('restaurants.index'))
        ->assertSeeHtml('href="'.route('restaurants.show', $restaurant).'"');
});
