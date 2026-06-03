<?php

use App\Models\Restaurant;
use App\Models\User;
use Livewire\Livewire;

it('renders the dashboard for an authenticated user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertOk();
});

it('redirects guests to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('displays the quick pick mode card with a link to the pick route', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Quick Pick')
        ->assertSee(route('pick'));
});

it('displays the tonight mode card with a link to the tonight route', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Tonight')
        ->assertSee(route('tonight'));
});

it('displays the quiz mode card with a link to the quiz route', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Guided Quiz')
        ->assertSee(route('quiz'));
});

it('displays the tournament mode card with a link to the tournament route', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Tournament')
        ->assertSee(route('tournament'));
});

it('shows the add restaurant link alongside existing restaurants', function () {
    $user = User::factory()->create();

    Restaurant::factory()->create(['owner_user_id' => $user->id, 'name' => 'My Place']);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('My Place')
        ->assertSee(route('restaurants.create'));
});

it('shows placeholder copy and add restaurant link when the user has no restaurants', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee("You haven't added any restaurants yet.")
        ->assertSee(route('restaurants.create'));
});

it('only shows the authenticated user\'s restaurants, not other users\'', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Restaurant::factory()->create(['owner_user_id' => $user->id, 'name' => 'My Restaurant']);
    Restaurant::factory()->create(['owner_user_id' => $otherUser->id, 'name' => 'Other User Restaurant']);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('My Restaurant')
        ->assertDontSee('Other User Restaurant');
});

it('does not show more than 3 restaurants on the dashboard', function () {
    $user = User::factory()->create();

    Restaurant::factory()->create(['owner_user_id' => $user->id, 'name' => 'Restaurant One', 'created_at' => now()->subDays(4)]);
    Restaurant::factory()->create(['owner_user_id' => $user->id, 'name' => 'Restaurant Two', 'created_at' => now()->subDays(3)]);
    Restaurant::factory()->create(['owner_user_id' => $user->id, 'name' => 'Restaurant Three', 'created_at' => now()->subDays(2)]);
    Restaurant::factory()->create(['owner_user_id' => $user->id, 'name' => 'Restaurant Four', 'created_at' => now()->subDay()]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertDontSee('Restaurant One')
        ->assertSee('Restaurant Two')
        ->assertSee('Restaurant Three')
        ->assertSee('Restaurant Four');
});

it('shows up to 3 recently added restaurants ordered by newest first', function () {
    $user = User::factory()->create();

    $oldest = Restaurant::factory()->create(['owner_user_id' => $user->id, 'name' => 'Oldest Place', 'created_at' => now()->subDays(3)]);
    $middle = Restaurant::factory()->create(['owner_user_id' => $user->id, 'name' => 'Middle Place', 'created_at' => now()->subDays(2)]);
    $newest = Restaurant::factory()->create(['owner_user_id' => $user->id, 'name' => 'Newest Place', 'created_at' => now()->subDay()]);

    $html = Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->html();

    $newestPos = strpos($html, 'Newest Place');
    $middlePos = strpos($html, 'Middle Place');
    $oldestPos = strpos($html, 'Oldest Place');

    expect($newestPos)->toBeLessThan($middlePos)
        ->and($middlePos)->toBeLessThan($oldestPos);
});
