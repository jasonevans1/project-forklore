<?php

use App\Enums\ModeUsed;
use App\Enums\PatioQuality;
use App\Enums\RestaurantSource;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Visit;
use App\Services\QuickPickFilters;
use App\Services\QuickPickService;
use App\Services\WeatherData;
use App\Services\WeatherService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    // Silence the weather service by default — no real HTTP calls.
    $this->mock(WeatherService::class)->allows('fetch')->andReturnNull();
});

// ---------------------------------------------------------------------------
// Route access
// ---------------------------------------------------------------------------

it('is accessible to authenticated users', function () {
    $this->actingAs($this->user)
        ->get(route('pick'))
        ->assertOk();
});

it('redirects guests to the login page', function () {
    $this->get(route('pick'))
        ->assertRedirect(route('login'));
});

it('renders the Pick for us button in the idle state', function () {
    $this->actingAs($this->user)
        ->get(route('pick'))
        ->assertSee('Pick for us');
});

// ---------------------------------------------------------------------------
// Pick action
// ---------------------------------------------------------------------------

it('transitions to the result state and shows the restaurant name after picking', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'The Noodle House']);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->assertSet('state', 'result')
        ->assertSee('The Noodle House');
});

it('shows the cuisine tags on the result card', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'cuisine_tags' => ['Thai', 'Noodles'],
    ]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->assertSee('Thai')
        ->assertSee('Noodles');
});

it('transitions to the empty state when no restaurants are available', function () {
    $this->mock(QuickPickService::class)->allows('pick')->andReturnNull();

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->assertSet('state', 'empty')
        ->assertSee('No restaurants');
});

it('shows a patio tagline when the restaurant has a destination patio and ideal weather', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::Destination,
        'lat' => 41.58,
        'lng' => -93.62,
    ]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    // 22 °C = 71.6 °F, no rain — ideal patio weather
    $this->mock(WeatherService::class)->allows('fetch')->andReturn(new WeatherData(
        temperature: 22.0,
        conditions: 'Clear',
        precipitation: 0.0,
        windSpeed: 2.0,
        sunset: CarbonImmutable::now()->addHours(4),
        units: 'metric',
    ));

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->set('lat', 41.58)
        ->set('lng', -93.62)
        ->call('pick')
        ->assertSee('Perfect patio weather');
});

it('still shows a weather-aware tagline on the Quick Pick result card after extraction', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::Destination,
        'lat' => 41.58,
        'lng' => -93.62,
    ]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    // 22 °C = 71.6 °F, no rain — ideal patio weather
    $this->mock(WeatherService::class)->allows('fetch')->andReturn(new WeatherData(
        temperature: 22.0,
        conditions: 'Clear',
        precipitation: 0.0,
        windSpeed: 2.0,
        sunset: CarbonImmutable::now()->addHours(4),
        units: 'metric',
    ));

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->set('lat', 41.58)
        ->set('lng', -93.62)
        ->call('pick')
        ->assertSee('Perfect patio weather');
});

it('still shows a distance label on the Quick Pick result card after extraction', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 41.60,
        'lng' => -93.60,
    ]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->set('lat', 41.58)
        ->set('lng', -93.62)
        ->call('pick')
        ->assertSee('mi');
});

it("still shows no distance label when the user's coordinates are unavailable", function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 41.60,
        'lng' => -93.60,
    ]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->assertSet('distanceLabel', null);
});

// ---------------------------------------------------------------------------
// Going — full pick → going flow
// ---------------------------------------------------------------------------

it('creates a visit record when the user confirms going', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->call('going');

    expect(
        Visit::where('user_id', $this->user->id)
            ->where('restaurant_id', $restaurant->id)
            ->where('mode_used', ModeUsed::QuickPick)
            ->exists()
    )->toBeTrue();
});

it('increments the restaurant visit_count when the user confirms going', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['visit_count' => 2]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->call('going');

    expect($restaurant->fresh()->visit_count)->toBe(3);
});

it('updates last_visited_at on the restaurant when the user confirms going', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['last_visited_at' => null]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->call('going');

    expect($restaurant->fresh()->last_visited_at)->not->toBeNull();
});

it('redirects to the dashboard after going', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->call('going')
        ->assertRedirect(route('dashboard'));
});

// ---------------------------------------------------------------------------
// Reject / Not this one
// ---------------------------------------------------------------------------

it('adds the current restaurant to excludedIds when rejected', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();
    $other = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->mock(QuickPickService::class);
    $mock->allows('pick')->andReturn($restaurant, $other);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->call('reject')
        ->assertSet('excludedIds', [$restaurant->id]);
});

it('automatically picks a new restaurant after rejecting', function () {
    $first = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'First']);
    $second = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'Second']);

    $mock = $this->mock(QuickPickService::class);
    $mock->allows('pick')->andReturn($first, $second);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->assertSee('First')
        ->call('reject')
        ->assertSee('Second');
});

it('passes the excluded IDs to the service when picking after a rejection', function () {
    $first = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->mock(QuickPickService::class);

    // First pick — returns a restaurant
    $mock->expects('pick')
        ->once()
        ->withArgs(fn ($user, QuickPickFilters $filters) => empty($filters->excludedIds))
        ->andReturn($first);

    // Second pick (after reject) — excluded IDs should contain the first restaurant
    $mock->expects('pick')
        ->once()
        ->withArgs(fn ($user, QuickPickFilters $filters) => in_array($first->id, $filters->excludedIds))
        ->andReturnNull();

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->call('reject');
});

it('shows the empty state when all restaurants have been rejected', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->mock(QuickPickService::class);
    $mock->allows('pick')->andReturn($restaurant, null);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->call('reject')
        ->assertSet('state', 'empty');
});

// ---------------------------------------------------------------------------
// Restaurant result ticket restyle
// ---------------------------------------------------------------------------

it('renders the result using the restaurant result ticket component', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'cuisine_tags' => ['Thai', 'Noodles'],
    ]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->assertSee('cuisine-tags', false);
});

it('still shows the save as favorite button for a Places-sourced result', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'source' => RestaurantSource::Places,
    ]);

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->assertSee('Save as favorite');
});

it('still shows the going and reject buttons after the restyle', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->assertSee('Going')
        ->assertSee('Not this one');
});
