<?php

use App\Models\Restaurant;
use App\Models\User;
use App\Models\Visit;
use App\Services\QuickPickFilters;
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
// Schema — user has the column
// ---------------------------------------------------------------------------

it('users table has an allow_repeats column defaulting to false', function () {
    expect($this->user->allow_repeats)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Settings page
// ---------------------------------------------------------------------------

it('preferences page shows the allow repeats toggle', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.preferences')
        ->assertSee('repeats');
});

it('preferences page reflects the current allow_repeats value', function () {
    $this->user->update(['allow_repeats' => true]);

    Livewire::actingAs($this->user)
        ->test('pages::settings.preferences')
        ->assertSet('allowRepeats', true);
});

it('saving the preferences page persists allow_repeats', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.preferences')
        ->set('allowRepeats', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->user->fresh()->allow_repeats)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Recency exclusion — 21 days (3 weeks)
// ---------------------------------------------------------------------------

it('excludes a restaurant visited within 21 days by default', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    Visit::factory()->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now()->subDays(10),
    ]);

    $result = app(QuickPickService::class)->pick($this->user, new QuickPickFilters);

    expect($result)->toBeNull();
});

it('excludes a restaurant visited exactly 21 days ago by default', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    Visit::factory()->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now()->subDays(21),
    ]);

    // Visited exactly 21 days ago — still within the window.
    $result = app(QuickPickService::class)->pick($this->user, new QuickPickFilters);

    expect($result)->toBeNull();
});

it('includes a restaurant visited 22 days ago by default', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    Visit::factory()->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now()->subDays(22),
    ]);

    $result = app(QuickPickService::class)->pick($this->user, new QuickPickFilters);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($restaurant->id);
});

// ---------------------------------------------------------------------------
// allow_repeats = true — bypass recency exclusion
// ---------------------------------------------------------------------------

it('includes a recently visited restaurant when allow_repeats is true', function () {
    $this->user->update(['allow_repeats' => true]);

    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    Visit::factory()->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now()->subDays(5),
    ]);

    $result = app(QuickPickService::class)->pick($this->user, new QuickPickFilters);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($restaurant->id);
});

it('does not exclude any restaurant based on recency when allow_repeats is true', function () {
    $this->user->update(['allow_repeats' => true]);

    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(3)->create();

    // All visited very recently.
    foreach ($restaurants as $r) {
        Visit::factory()->create([
            'user_id' => $this->user->id,
            'restaurant_id' => $r->id,
            'visited_at' => now()->subDay(),
        ]);
    }

    $result = app(QuickPickService::class)->pick($this->user, new QuickPickFilters);

    expect($result)->not->toBeNull();
});
