<?php

use App\Actions\PromotePlacesToFavorite;
use App\Enums\PrimaryCuisine;
use App\Enums\RestaurantSource;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\JevService;
use App\Services\QuickPickService;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->mock(WeatherService::class)->allows('fetch')->andReturnNull();
});

// ---------------------------------------------------------------------------
// Visibility: button only appears for source=places
// ---------------------------------------------------------------------------

it('shows the Save as favorite button when the result restaurant has source=places', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'source' => RestaurantSource::Places,
    ]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->assertSee('Save as favorite');
});

it('does not show the Save as favorite button when the result restaurant has source=favorite', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'source' => RestaurantSource::Favorite,
    ]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->assertDontSee('Save as favorite');
});

// ---------------------------------------------------------------------------
// Promotion: saveAsFavorite flips source to favorite
// ---------------------------------------------------------------------------

it('flips the restaurant source from places to favorite when saveAsFavorite is called', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'source' => RestaurantSource::Places,
    ]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->call('saveAsFavorite');

    expect($restaurant->fresh()->source)->toBe(RestaurantSource::Favorite);
});

it('assigns owner_user_id to the authenticated user when saveAsFavorite is called', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'source' => RestaurantSource::Places,
        'owner_user_id' => $this->user->id,
    ]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->call('saveAsFavorite');

    expect($restaurant->fresh()->owner_user_id)->toBe($this->user->id);
});

// ---------------------------------------------------------------------------
// Redirect: after promotion, opens the edit screen
// ---------------------------------------------------------------------------

it('redirects to the restaurant edit page after saveAsFavorite', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'source' => RestaurantSource::Places,
    ]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->call('saveAsFavorite')
        ->assertRedirect(route('restaurants.edit', $restaurant));
});

// ---------------------------------------------------------------------------
// Authorization: a non-owner cannot promote someone else's Places result
// ---------------------------------------------------------------------------

it('forbids a user from promoting a Places restaurant they do not own', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    $restaurant = Restaurant::factory()->for($owner, 'user')->create([
        'source' => RestaurantSource::Places,
    ]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($attacker)
        ->test('pages::pick')
        ->call('pick')
        ->call('saveAsFavorite')
        ->assertForbidden();

    expect($restaurant->fresh()->source)->toBe(RestaurantSource::Places);
});

// ---------------------------------------------------------------------------
// Guard: saveAsFavorite is a no-op when restaurant is already a favorite
// ---------------------------------------------------------------------------

it('does not change source if it is already favorite when saveAsFavorite is called', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'source' => RestaurantSource::Favorite,
    ]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->call('saveAsFavorite');

    // Source must remain unchanged — no unintended mutation
    expect($restaurant->fresh()->source)->toBe(RestaurantSource::Favorite);
});

// ---------------------------------------------------------------------------
// Jev profiling on promotion
// ---------------------------------------------------------------------------

it('profiles an unprofiled places restaurant before promoting it', function () {
    $restaurant = Restaurant::factory()->create([
        'source' => RestaurantSource::Places,
        'primary_cuisine' => null,
    ]);

    $this->mock(JevService::class)->shouldReceive('ask')->once()->andReturn([
        'primary_cuisine' => ['type' => 'choice', 'value' => 'thai', 'confidence' => 0.9],
    ]);

    app(PromotePlacesToFavorite::class)->execute($restaurant, $this->user);

    $restaurant->refresh();
    expect($restaurant->primary_cuisine)->toBe(PrimaryCuisine::Thai)
        ->and($restaurant->profiled_at)->not->toBeNull()
        ->and($restaurant->source)->toBe(RestaurantSource::Favorite);
});

it('promotes the restaurant when Jev is unavailable', function () {
    $restaurant = Restaurant::factory()->create(['source' => RestaurantSource::Places]);

    $this->mock(JevService::class)->shouldReceive('ask')->andReturnNull();

    app(PromotePlacesToFavorite::class)->execute($restaurant, $this->user);

    $restaurant->refresh();
    expect($restaurant->source)->toBe(RestaurantSource::Favorite)
        ->and($restaurant->owner_user_id)->toBe($this->user->id)
        ->and($restaurant->profiled_at)->toBeNull();
});

it('does not call Jev when promoting an already profiled restaurant', function () {
    $restaurant = Restaurant::factory()->create([
        'source' => RestaurantSource::Places,
        'profiled_at' => now(),
    ]);

    $this->mock(JevService::class)->shouldNotReceive('ask');

    app(PromotePlacesToFavorite::class)->execute($restaurant, $this->user);

    expect($restaurant->refresh()->source)->toBe(RestaurantSource::Favorite);
});
