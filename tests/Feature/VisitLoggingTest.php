<?php

/**
 * Confirms that every selection mode reliably executes the full visit flywheel:
 *   1. Creates a Visit row with the correct mode_used.
 *   2. Increments restaurant.visit_count.
 *   3. Stamps restaurant.last_visited_at.
 *   4. Redirects to the dashboard.
 */

use App\Enums\ModeUsed;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Visit;
use App\Services\QuickPickService;
use App\Services\QuizService;
use App\Services\TonightService;
use App\Services\TournamentService;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->mock(WeatherService::class)->allows('fetch')->andReturnNull();
});

// ---------------------------------------------------------------------------
// Helper — run the full Quick Pick → going() flow
// ---------------------------------------------------------------------------

function quickPickGoing(User $user, Restaurant $restaurant): void
{
    app()->make(TestCase::class);

    Livewire::actingAs($user)
        ->test('pages::pick')
        ->call('pick')
        ->call('going');
}

// ---------------------------------------------------------------------------
// Mode: Quick Pick
// ---------------------------------------------------------------------------

it('Quick Pick going() creates a Visit with mode_used=quick_pick', function () {
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

it('Quick Pick going() increments visit_count', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['visit_count' => 0]);
    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->call('going');

    expect($restaurant->fresh()->visit_count)->toBe(1);
});

it('Quick Pick going() stamps last_visited_at', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['last_visited_at' => null]);
    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->call('going');

    expect($restaurant->fresh()->last_visited_at)->not->toBeNull();
});

it('Quick Pick going() redirects to the dashboard', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();
    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::pick')
        ->call('pick')
        ->call('going')
        ->assertRedirect(route('dashboard'));
});

// ---------------------------------------------------------------------------
// Mode: Tonight (Something Happening Tonight)
// ---------------------------------------------------------------------------

it('Tonight going() creates a Visit with mode_used=tonight', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();
    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant);
    $mock->allows('eventLabel')->andReturn('Trivia at 7pm');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->call('going');

    expect(
        Visit::where('user_id', $this->user->id)
            ->where('restaurant_id', $restaurant->id)
            ->where('mode_used', ModeUsed::Tonight)
            ->exists()
    )->toBeTrue();
});

it('Tonight going() increments visit_count', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['visit_count' => 2]);
    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant);
    $mock->allows('eventLabel')->andReturn('Live music');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->call('going');

    expect($restaurant->fresh()->visit_count)->toBe(3);
});

it('Tonight going() stamps last_visited_at', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['last_visited_at' => null]);
    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant);
    $mock->allows('eventLabel')->andReturn('Bingo night');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->call('going');

    expect($restaurant->fresh()->last_visited_at)->not->toBeNull();
});

it('Tonight going() redirects to the dashboard', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();
    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant);
    $mock->allows('eventLabel')->andReturn('');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->call('going')
        ->assertRedirect(route('dashboard'));
});

// ---------------------------------------------------------------------------
// Mode: Guided Quiz
// ---------------------------------------------------------------------------

it('Quiz going() creates a Visit with mode_used=quiz', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();
    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'moderate')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'cuisine', null)
        ->call('going');

    expect(
        Visit::where('user_id', $this->user->id)
            ->where('restaurant_id', $restaurant->id)
            ->where('mode_used', ModeUsed::Quiz)
            ->exists()
    )->toBeTrue();
});

it('Quiz going() increments visit_count', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['visit_count' => 1]);
    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'moderate')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'cuisine', null)
        ->call('going');

    expect($restaurant->fresh()->visit_count)->toBe(2);
});

it('Quiz going() stamps last_visited_at', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['last_visited_at' => null]);
    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'moderate')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'cuisine', null)
        ->call('going');

    expect($restaurant->fresh()->last_visited_at)->not->toBeNull();
});

it('Quiz going() redirects to the dashboard', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();
    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'moderate')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'cuisine', null)
        ->call('going')
        ->assertRedirect(route('dashboard'));
});

// ---------------------------------------------------------------------------
// Mode: Tournament
// ---------------------------------------------------------------------------

it('Tournament going() creates a Visit with mode_used=tournament', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();
    $champion = $restaurants[0];
    $finalist = [$champion, $restaurants[2]];

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());
    $mock->allows('advance')->andReturn($finalist);
    $mock->allows('winner')->andReturn($champion);

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('pick', $restaurants[0]->id)
        ->call('pick', $restaurants[2]->id)
        ->call('pick', $champion->id)
        ->call('going');

    expect(
        Visit::where('user_id', $this->user->id)
            ->where('restaurant_id', $champion->id)
            ->where('mode_used', ModeUsed::Tournament)
            ->exists()
    )->toBeTrue();
});

it('Tournament going() increments visit_count', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();
    $champion = $restaurants[0];
    $champion->update(['visit_count' => 5]);
    $finalist = [$champion, $restaurants[2]];

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());
    $mock->allows('advance')->andReturn($finalist);
    $mock->allows('winner')->andReturn($champion);

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('pick', $restaurants[0]->id)
        ->call('pick', $restaurants[2]->id)
        ->call('pick', $champion->id)
        ->call('going');

    expect($champion->fresh()->visit_count)->toBe(6);
});

it('Tournament going() stamps last_visited_at', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();
    $champion = $restaurants[0];
    $champion->update(['last_visited_at' => null]);
    $finalist = [$champion, $restaurants[2]];

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());
    $mock->allows('advance')->andReturn($finalist);
    $mock->allows('winner')->andReturn($champion);

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('pick', $restaurants[0]->id)
        ->call('pick', $restaurants[2]->id)
        ->call('pick', $champion->id)
        ->call('going');

    expect($champion->fresh()->last_visited_at)->not->toBeNull();
});

it('Tournament going() redirects to the dashboard', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();
    $champion = $restaurants[0];
    $finalist = [$champion, $restaurants[2]];

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());
    $mock->allows('advance')->andReturn($finalist);
    $mock->allows('winner')->andReturn($champion);

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('pick', $restaurants[0]->id)
        ->call('pick', $restaurants[2]->id)
        ->call('pick', $champion->id)
        ->call('going')
        ->assertRedirect(route('dashboard'));
});
