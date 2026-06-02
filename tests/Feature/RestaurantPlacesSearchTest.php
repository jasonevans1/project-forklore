<?php

use App\Enums\RestaurantSource;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\PlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function placesResult(array $overrides = []): array
{
    return array_merge([
        'id' => 'ChIJplace001',
        'name' => 'Test Bistro',
        'address' => '123 Main St, Des Moines, IA',
        'types' => ['italian_restaurant', 'restaurant', 'food', 'establishment', 'point_of_interest'],
        'rating' => 4.5,
        'lat' => 41.5908,
        'lng' => -93.6208,
        'price_level' => 2,
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Tab visibility
// ---------------------------------------------------------------------------

it('shows the search tab by default', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->assertSet('activeTab', 'search');
});

// ---------------------------------------------------------------------------
// Search action
// ---------------------------------------------------------------------------

it('calls PlacesService textSearch with the entered query when search is submitted', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $mock = $this->mock(PlacesService::class);
    $mock->expects('textSearch')->once()->with('tacos')->andReturn([placesResult()]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('searchQuery', 'tacos')
        ->call('search')
        ->assertSet('searchResults', [placesResult()]);
});

// ---------------------------------------------------------------------------
// Result cards
// ---------------------------------------------------------------------------

it('displays each result as a card with name and address', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->mock(PlacesService::class)
        ->allows('textSearch')->andReturn([placesResult()]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('searchQuery', 'tacos')
        ->call('search')
        ->assertSee('Test Bistro')
        ->assertSee('123 Main St, Des Moines, IA');
});

// ---------------------------------------------------------------------------
// Empty states
// ---------------------------------------------------------------------------

it('shows an empty state message when the search returns no results', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->mock(PlacesService::class)
        ->allows('textSearch')->andReturn([]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('searchQuery', 'zzz-nothing')
        ->call('search')
        ->assertSee('No results found');
});

it('shows the empty state when textSearch returns null (quota exceeded or no key)', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->mock(PlacesService::class)
        ->allows('textSearch')->andReturn(null);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('searchQuery', 'something')
        ->call('search')
        ->assertSee('No results found');
});

// ---------------------------------------------------------------------------
// selectPlace pre-fills
// ---------------------------------------------------------------------------

it('pre-fills the name field when a place is selected', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('searchResults', [placesResult(['name' => 'Fancy Grill'])])
        ->call('selectPlace', 0)
        ->assertSet('name', 'Fancy Grill');
});

it('pre-fills the address field when a place is selected', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('searchResults', [placesResult(['address' => '456 Elm St, Ames, IA'])])
        ->call('selectPlace', 0)
        ->assertSet('address', '456 Elm St, Ames, IA');
});

it('pre-fills price_level when a place is selected', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('searchResults', [placesResult(['price_level' => 3])])
        ->call('selectPlace', 0)
        ->assertSet('price_level', 3);
});

it('leaves price_level null when the selected place has no price level', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('searchResults', [placesResult(['price_level' => null])])
        ->call('selectPlace', 0)
        ->assertSet('price_level', null);
});

it('stores the places_id when a place is selected', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('searchResults', [placesResult(['id' => 'ChIJabc123'])])
        ->call('selectPlace', 0)
        ->assertSet('places_id', 'ChIJabc123');
});

it('stores lat and lng when a place is selected', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('searchResults', [placesResult(['lat' => 41.5908, 'lng' => -93.6208])])
        ->call('selectPlace', 0)
        ->assertSet('lat', 41.5908)
        ->assertSet('lng', -93.6208);
});

// ---------------------------------------------------------------------------
// Cuisine type mapping
// ---------------------------------------------------------------------------

it('maps Google types to cuisine_tags omitting noise types', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $types = ['italian_restaurant', 'food', 'restaurant', 'establishment', 'point_of_interest', 'meal_takeaway', 'meal_delivery', 'cafe'];

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('searchResults', [placesResult(['types' => $types])])
        ->call('selectPlace', 0)
        ->assertSet('cuisine_tags', 'italian');
});

it('strips the trailing restaurant suffix from cuisine types', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $types = ['mexican_restaurant', 'japanese_restaurant', 'establishment'];

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('searchResults', [placesResult(['types' => $types])])
        ->call('selectPlace', 0)
        ->assertSet('cuisine_tags', 'mexican, japanese');
});

// ---------------------------------------------------------------------------
// Tab switch
// ---------------------------------------------------------------------------

it('switches to the manual tab after a place is selected', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('searchResults', [placesResult()])
        ->call('selectPlace', 0)
        ->assertSet('activeTab', 'manual');
});

// ---------------------------------------------------------------------------
// Database persistence
// ---------------------------------------------------------------------------

it('persists places_id, lat, and lng to the database when the form is saved after a place search', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('name', 'Search Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->set('places_id', 'ChIJtest001')
        ->set('lat', 41.5908)
        ->set('lng', -93.6208)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('restaurants', [
        'name' => 'Search Place',
        'places_id' => 'ChIJtest001',
        'lat' => 41.5908,
        'lng' => -93.6208,
    ]);
});

it('keeps source as favorite when saving a place-prefilled restaurant', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('name', 'Prefilled Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->set('places_id', 'ChIJtest002')
        ->set('lat', 41.5908)
        ->set('lng', -93.6208)
        ->call('save');

    $this->assertDatabaseHas('restaurants', [
        'name' => 'Prefilled Place',
        'source' => RestaurantSource::Favorite->value,
    ]);
});

it('shows a validation error instead of throwing when saving a place whose places_id already exists', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create();

    Restaurant::factory()->for($other, 'user')->create(['places_id' => 'ChIJduplicate']);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('name', 'Duplicate Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->set('places_id', 'ChIJduplicate')
        ->call('save')
        ->assertHasErrors(['name'])
        ->assertNoRedirect();
});

it('still saves a normal manual restaurant with places_id null (regression for the existing save path)', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.create')
        ->set('name', 'Manual Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('restaurants.index'));

    $this->assertDatabaseHas('restaurants', [
        'name' => 'Manual Place',
        'places_id' => null,
        'lat' => null,
        'lng' => null,
    ]);
});
