<?php

use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Models\Restaurant;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

it('shows the restaurant name on the detail page', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'name' => 'Pasta Palace',
    ]);

    $this->actingAs($user)
        ->get(route('restaurants.show', $restaurant))
        ->assertSee('Pasta Palace');
});

it('shows the cuisine tags on the detail page', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'cuisine_tags' => ['Italian', 'Pizza'],
    ]);

    $this->actingAs($user)
        ->get(route('restaurants.show', $restaurant))
        ->assertSee('Italian')
        ->assertSee('Pizza');
});

it('shows the price level on the detail page', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'price_level' => 3,
    ]);

    $this->actingAs($user)
        ->get(route('restaurants.show', $restaurant))
        ->assertSee('$$$');
});

it('shows the vibe tags on the detail page', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'vibe_tags' => ['romantic', 'quiet'],
    ]);

    $this->actingAs($user)
        ->get(route('restaurants.show', $restaurant))
        ->assertSee('romantic')
        ->assertSee('quiet');
});

it('shows the patio quality on the detail page', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'patio_quality' => PatioQuality::Destination,
    ]);

    $this->actingAs($user)
        ->get(route('restaurants.show', $restaurant))
        ->assertSee('destination');
});

it('shows the indoor vibe on the detail page', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'indoor_vibe_when_cold' => IndoorVibe::Cozy,
    ]);

    $this->actingAs($user)
        ->get(route('restaurants.show', $restaurant))
        ->assertSee('cozy');
});

it('shows the visit count on the detail page', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'visit_count' => 7,
    ]);

    $this->actingAs($user)
        ->get(route('restaurants.show', $restaurant))
        ->assertSee('7');
});

it('shows the last visited date on the detail page', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'last_visited_at' => Carbon::parse('2025-03-15'),
    ]);

    $this->actingAs($user)
        ->get(route('restaurants.show', $restaurant))
        ->assertSee('15 Mar 2025');
});

it('shows never when last visited date is null on the detail page', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'last_visited_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('restaurants.show', $restaurant))
        ->assertSee('Never');
});

it('shows a link to the edit page on the detail page', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('restaurants.show', $restaurant))
        ->assertSee('restaurants/'.$restaurant->id.'/edit', false)
        ->assertSee('Edit');
});

it('shows the restaurant address when present on the detail page', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'address' => '123 Main St, Des Moines, IA',
    ]);

    $this->actingAs($user)
        ->get(route('restaurants.show', $restaurant))
        ->assertSee('123 Main St, Des Moines, IA');
});

it('deletes the restaurant from the database and redirects to the index when the owner calls delete', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    $restaurantId = $restaurant->id;

    Livewire::actingAs($user)
        ->test('pages::restaurants.show', ['restaurant' => $restaurant])
        ->call('delete')
        ->assertRedirect(route('restaurants.index'));

    $this->assertDatabaseMissing('restaurants', ['id' => $restaurantId]);
});

it('forbids a non-owner from invoking the delete method', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    // Mount as owner to get a valid component session
    $component = Livewire::actingAs($owner)
        ->test('pages::restaurants.show', ['restaurant' => $restaurant]);

    // Transfer ownership so that delete() re-authorization fails
    $restaurant->update(['owner_user_id' => $otherUser->id]);

    $component->call('delete')
        ->assertForbidden();
});
