<?php

use App\Actions\ProfilePlacesRestaurant;
use App\Enums\RestaurantSource;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\PlacesService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
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

// ---------------------------------------------------------------------------
// One-tap add
// ---------------------------------------------------------------------------

/**
 * @param  array<int, array<string, mixed>>  $results
 */
function quickAddComponent(array $results): Testable
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    test()->actingAs($user);

    return Livewire::test('pages::restaurants.create')->set('searchResults', $results);
}

it('shows an add button on each search result', function () {
    quickAddComponent([placesResult(), placesResult(['id' => 'ChIJplace002'])])
        ->assertSeeHtml('wire:click.stop="quickAdd(0)"')
        ->assertSeeHtml('wire:click.stop="quickAdd(1)"');
});

it('creates a favorite owned by the user from the tapped result', function () {
    quickAddComponent([placesResult()])->call('quickAdd', 0);

    $restaurant = Restaurant::where('places_id', 'ChIJplace001')->firstOrFail();
    expect($restaurant->owner_user_id)->toBe(auth()->id())
        ->and($restaurant->source)->toBe(RestaurantSource::Favorite);
});

it("saves the result's name, address, coordinates, price level, places id and cuisine tags", function () {
    quickAddComponent([placesResult()])->call('quickAdd', 0);

    $restaurant = Restaurant::firstOrFail();
    expect($restaurant->name)->toBe('Test Bistro')
        ->and($restaurant->address)->toBe('123 Main St, Des Moines, IA')
        ->and((float) $restaurant->lat)->toBe(41.5908)
        ->and((float) $restaurant->lng)->toBe(-93.6208)
        ->and($restaurant->price_level)->toBe(2)
        ->and($restaurant->places_id)->toBe('ChIJplace001')
        ->and($restaurant->cuisine_tags)->toBe(PlacesService::cuisineTagsFromTypes(placesResult()['types']));
});

it('saves an empty vibe tags array for a one-tap add', function () {
    quickAddComponent([placesResult()])->call('quickAdd', 0);

    expect(Restaurant::firstOrFail()->vibe_tags)->toBe([]);
});

it('falls back to a restaurant cuisine tag when the result has no mappable types', function () {
    quickAddComponent([placesResult(['types' => ['point_of_interest']])])->call('quickAdd', 0);

    expect(Restaurant::firstOrFail()->cuisine_tags)->toBe(['restaurant']);
});

it('profiles the one-tap restaurant in full mode', function () {
    $this->withoutDefer();
    $mock = Mockery::mock(ProfilePlacesRestaurant::class);
    $mock->shouldReceive('execute')
        ->once()
        ->withArgs(fn (Restaurant $r, bool $onlyEmptyFields = false): bool => $r->name === 'Test Bistro' && ! $onlyEmptyFields);
    app()->instance(ProfilePlacesRestaurant::class, $mock);

    quickAddComponent([placesResult()])->call('quickAdd', 0);
});

it("redirects to the new restaurant's page after a one-tap add", function () {
    $this->withoutDefer();
    app()->instance(ProfilePlacesRestaurant::class, Mockery::mock(ProfilePlacesRestaurant::class)->shouldIgnoreMissing());

    quickAddComponent([placesResult()])
        ->call('quickAdd', 0)
        ->assertRedirect(route('restaurants.show', Restaurant::firstOrFail()));
});

it('does not create a duplicate when the place is already saved', function () {
    Restaurant::factory()->create(['places_id' => 'ChIJplace001']);

    quickAddComponent([placesResult()])
        ->call('quickAdd', 0)
        ->assertDispatched('toast-show')
        ->assertNoRedirect();

    expect(Restaurant::count())->toBe(1);
});

it('shows the already-saved toast instead of failing when the insert hits the unique constraint', function () {
    Restaurant::creating(fn () => throw new UniqueConstraintViolationException('sqlite', 'insert', [], new Exception));

    quickAddComponent([placesResult()])
        ->call('quickAdd', 0)
        ->assertDispatched('toast-show')
        ->assertNoRedirect();

    expect(Restaurant::count())->toBe(0);
});

it('does not save a tampered search result that fails validation', function () {
    quickAddComponent([placesResult(['name' => str_repeat('x', 300)])])
        ->call('quickAdd', 0)
        ->assertDispatched('toast-show')
        ->assertNoRedirect();

    expect(Restaurant::count())->toBe(0);
});

it('does nothing for an index that is not in the search results', function () {
    quickAddComponent([placesResult()])
        ->call('quickAdd', 5)
        ->assertNotDispatched('toast-show')
        ->assertNoRedirect();

    expect(Restaurant::count())->toBe(0);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function placesRowFor(User $user, array $attributes = []): Restaurant
{
    test()->actingAs($user);

    return Restaurant::factory()->create(array_merge([
        'owner_user_id' => $user->id,
        'source' => RestaurantSource::Places,
        'places_id' => 'ChIJplace001',
        'profiled_at' => null,
    ], $attributes));
}

function claimComponent(): Testable
{
    return Livewire::test('pages::restaurants.create')->set('searchResults', [placesResult()]);
}

it("turns the user's own places-sourced row into a favorite on one-tap add", function () {
    $this->withoutDefer();
    app()->instance(ProfilePlacesRestaurant::class, Mockery::mock(ProfilePlacesRestaurant::class)->shouldIgnoreMissing());
    $row = placesRowFor(User::factory()->create());

    claimComponent()->call('quickAdd', 0);

    expect($row->fresh()->source)->toBe(RestaurantSource::Favorite)
        ->and(Restaurant::count())->toBe(1);
});

it("redirects to the claimed restaurant's page", function () {
    $this->withoutDefer();
    app()->instance(ProfilePlacesRestaurant::class, Mockery::mock(ProfilePlacesRestaurant::class)->shouldIgnoreMissing());
    $row = placesRowFor(User::factory()->create());

    claimComponent()->call('quickAdd', 0)->assertRedirect(route('restaurants.show', $row));
});

it('profiles a claimed restaurant that has not been profiled yet', function () {
    $this->withoutDefer();
    $mock = Mockery::mock(ProfilePlacesRestaurant::class);
    $mock->shouldReceive('execute')->once();
    app()->instance(ProfilePlacesRestaurant::class, $mock);
    placesRowFor(User::factory()->create());

    claimComponent()->call('quickAdd', 0);
});

it('does not re-profile a claimed restaurant that is already profiled', function () {
    $this->withoutDefer();
    $mock = Mockery::mock(ProfilePlacesRestaurant::class);
    $mock->shouldNotReceive('execute');
    app()->instance(ProfilePlacesRestaurant::class, $mock);
    $row = placesRowFor(User::factory()->create(), ['profiled_at' => now()]);

    claimComponent()->call('quickAdd', 0)->assertRedirect(route('restaurants.show', $row));
});

it('shows the already-saved toast when the place is already a favorite', function () {
    placesRowFor(User::factory()->create(), ['source' => RestaurantSource::Favorite]);

    claimComponent()->call('quickAdd', 0)
        ->assertDispatched('toast-show')
        ->assertNoRedirect();
});

it('does not claim a places-sourced row owned by another user', function () {
    $row = placesRowFor(User::factory()->create(), ['owner_user_id' => User::factory()->create()->id]);

    claimComponent()->call('quickAdd', 0)
        ->assertDispatched('toast-show')
        ->assertNoRedirect();

    expect($row->fresh()->source)->toBe(RestaurantSource::Places);
});

it('does not save a search result with non-array types', function () {
    quickAddComponent([placesResult(['types' => 'restaurant'])])
        ->call('quickAdd', 0)
        ->assertDispatched('toast-show')
        ->assertNoRedirect();

    expect(Restaurant::count())->toBe(0);
});
