<?php

use App\Enums\RestaurantSource;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\TournamentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->service = app(TournamentService::class);
});

// ---------------------------------------------------------------------------
// seed() — bracket size selection
// ---------------------------------------------------------------------------

it('seeds a bracket of 4 when the user has exactly 4 favorites', function () {
    Restaurant::factory()->for($this->user, 'user')->count(4)->create();

    $bracket = $this->service->seed($this->user);

    expect($bracket)->toHaveCount(4);
});

it('seeds a bracket of 4 when the user has 5 favorites', function () {
    Restaurant::factory()->for($this->user, 'user')->count(5)->create();

    $bracket = $this->service->seed($this->user);

    expect($bracket)->toHaveCount(4);
});

it('seeds a bracket of 8 when the user has exactly 8 favorites', function () {
    Restaurant::factory()->for($this->user, 'user')->count(8)->create();

    $bracket = $this->service->seed($this->user);

    expect($bracket)->toHaveCount(8);
});

it('seeds a bracket of 8 when the user has more than 8 favorites', function () {
    Restaurant::factory()->for($this->user, 'user')->count(12)->create();

    $bracket = $this->service->seed($this->user);

    expect($bracket)->toHaveCount(8);
});

it('seeds a bracket of 4 when the user has 6 or 7 favorites', function () {
    Restaurant::factory()->for($this->user, 'user')->count(6)->create();

    $bracket = $this->service->seed($this->user);

    expect($bracket)->toHaveCount(4);
});

it('returns an empty bracket when the user has fewer than 4 favorites', function () {
    Restaurant::factory()->for($this->user, 'user')->count(3)->create();

    $bracket = $this->service->seed($this->user);

    expect($bracket)->toBeEmpty();
});

it('returns an empty bracket when the user has no favorites', function () {
    $bracket = $this->service->seed($this->user);

    expect($bracket)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// seed() — only favorites are included
// ---------------------------------------------------------------------------

it('excludes source=places restaurants from the bracket', function () {
    Restaurant::factory()->for($this->user, 'user')->count(4)->create([
        'source' => RestaurantSource::Favorite,
    ]);
    Restaurant::factory()->for($this->user, 'user')->count(4)->create([
        'source' => RestaurantSource::Places,
    ]);

    $bracket = $this->service->seed($this->user);
    $ids = collect($bracket)->pluck('id');

    expect(
        Restaurant::whereIn('id', $ids)->where('source', RestaurantSource::Places)->count()
    )->toBe(0);
});

// ---------------------------------------------------------------------------
// seed() — returns Restaurant models
// ---------------------------------------------------------------------------

it('returns an array of Restaurant model instances', function () {
    Restaurant::factory()->for($this->user, 'user')->count(4)->create();

    $bracket = $this->service->seed($this->user);

    foreach ($bracket as $entry) {
        expect($entry)->toBeInstanceOf(Restaurant::class);
    }
});

it('returns unique restaurants (no duplicates in the bracket)', function () {
    Restaurant::factory()->for($this->user, 'user')->count(8)->create();

    $bracket = $this->service->seed($this->user);
    $ids = collect($bracket)->pluck('id');

    expect($ids->count())->toBe($ids->unique()->count());
});

// ---------------------------------------------------------------------------
// seed() — budget filter
// ---------------------------------------------------------------------------

it('respects a budget_max filter when seeding', function () {
    Restaurant::factory()->for($this->user, 'user')->count(4)->create(['price_level' => 1]);
    Restaurant::factory()->for($this->user, 'user')->count(4)->create(['price_level' => 4]);

    $bracket = $this->service->seed($this->user, budgetMax: 1);

    foreach ($bracket as $restaurant) {
        expect($restaurant->price_level)->toBeLessThanOrEqual(1);
    }
});

it('returns empty bracket when filter reduces pool below 4', function () {
    Restaurant::factory()->for($this->user, 'user')->count(4)->create(['price_level' => 4]);

    $bracket = $this->service->seed($this->user, budgetMax: 1);

    expect($bracket)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// advance() — bracket progression
// ---------------------------------------------------------------------------

it('reduces a 4-team bracket to 2 after one round of picks', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create()->all();

    $winners = $this->service->advance(
        bracket: $restaurants,
        winnerIds: [$restaurants[0]->id, $restaurants[2]->id],
    );

    expect($winners)->toHaveCount(2);
});

it('reduces an 8-team bracket to 4 after the first round', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(8)->create()->all();
    $winnerIds = [$restaurants[0]->id, $restaurants[2]->id, $restaurants[4]->id, $restaurants[6]->id];

    $next = $this->service->advance(bracket: $restaurants, winnerIds: $winnerIds);

    expect($next)->toHaveCount(4);
});

it('returns only restaurants whose IDs are in the winner list', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create()->all();
    $winnerIds = [$restaurants[1]->id, $restaurants[3]->id];

    $next = $this->service->advance(bracket: $restaurants, winnerIds: $winnerIds);
    $returnedIds = collect($next)->pluck('id')->all();

    expect($returnedIds)->toContain($restaurants[1]->id)
        ->and($returnedIds)->toContain($restaurants[3]->id)
        ->and($returnedIds)->not->toContain($restaurants[0]->id)
        ->and($returnedIds)->not->toContain($restaurants[2]->id);
});

it('advance() returns Restaurant model instances', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create()->all();
    $winnerIds = [$restaurants[0]->id, $restaurants[2]->id];

    $next = $this->service->advance(bracket: $restaurants, winnerIds: $winnerIds);

    foreach ($next as $entry) {
        expect($entry)->toBeInstanceOf(Restaurant::class);
    }
});

// ---------------------------------------------------------------------------
// winner() — final result
// ---------------------------------------------------------------------------

it('returns the single remaining restaurant as the winner', function () {
    $champion = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'Champion']);

    $result = $this->service->winner([$champion]);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($champion->id);
});

it('returns null when the remaining bracket is empty', function () {
    $result = $this->service->winner([]);

    expect($result)->toBeNull();
});
