<?php

use App\Models\Event;
use App\Models\Restaurant;
use App\Models\User;
use Livewire\Livewire;

it('lists events belonging to the restaurant', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    $event = Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'title' => 'Wednesday Trivia',
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.index', ['restaurant' => $restaurant])
        ->assertSee('Wednesday Trivia');
});

it('does not show events from other restaurants', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    $other = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    Event::factory()->create([
        'restaurant_id' => $other->id,
        'owner_user_id' => $user->id,
        'title' => 'Other Trivia',
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.index', ['restaurant' => $restaurant])
        ->assertDontSee('Other Trivia');
});

it('shows an empty state when the restaurant has no events', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.index', ['restaurant' => $restaurant])
        ->assertSee('No events yet');
});

it('shows a link to add a new event', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.index', ['restaurant' => $restaurant])
        ->assertSee(route('restaurants.events.create', $restaurant));
});

it('forbids a non-owner from viewing the events index', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs($other)
        ->get(route('restaurants.events.index', $restaurant))
        ->assertForbidden();
});

it('redirects guests to login on the events index', function () {
    $restaurant = Restaurant::factory()->create();

    $this->get(route('restaurants.events.index', $restaurant))
        ->assertRedirect(route('login'));
});
