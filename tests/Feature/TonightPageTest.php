<?php

use App\Enums\ModeUsed;
use App\Models\Event;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Visit;
use App\Services\TonightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

// ---------------------------------------------------------------------------
// Route access
// ---------------------------------------------------------------------------

it('is accessible to authenticated users', function () {
    $this->actingAs($this->user)
        ->get(route('tonight'))
        ->assertOk();
});

it('redirects guests to the login page', function () {
    $this->get(route('tonight'))
        ->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Idle state
// ---------------------------------------------------------------------------

it('renders a "See what is happening" button in the idle state', function () {
    $this->actingAs($this->user)
        ->get(route('tonight'))
        ->assertSee("What's happening");
});

// ---------------------------------------------------------------------------
// Result state — event details above restaurant name
// ---------------------------------------------------------------------------

it('transitions to the result state after finding a restaurant with an event', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'The Tap Room']);

    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant);
    $mock->allows('eventLabel')->andReturn('Trivia starts at 7pm');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->assertSet('state', 'result')
        ->assertSee('The Tap Room');
});

it('shows the event detail label above the restaurant name', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'The Tap Room']);

    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant);
    $mock->allows('eventLabel')->andReturn('Trivia starts at 7pm');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->assertSeeInOrder(['Trivia starts at 7pm', 'The Tap Room']);
});

it('renders the result using the restaurant result ticket component', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'The Tap Room']);

    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant);
    $mock->allows('eventLabel')->andReturn('Trivia starts at 7pm');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->assertSeeHtml('font-family: var(--font-display);');
});

it('still shows the event label above the restaurant name after the restyle', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'The Tap Room']);

    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant);
    $mock->allows('eventLabel')->andReturn('Trivia starts at 7pm');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->assertSeeInOrder(['Trivia starts at 7pm', 'The Tap Room']);
});

it('shows the Going button in the result state', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant);
    $mock->allows('eventLabel')->andReturn('Trivia starts at 7pm');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->assertSee('Going');
});

it('shows the Not this one button in the result state', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant);
    $mock->allows('eventLabel')->andReturn('Trivia starts at 7pm');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->assertSee('Not this one');
});

// ---------------------------------------------------------------------------
// Empty state
// ---------------------------------------------------------------------------

it('transitions to the empty state when no events are found', function () {
    $this->mock(TonightService::class)->allows('pick')->andReturnNull();

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->assertSet('state', 'empty')
        ->assertSee('Nothing happening');
});

// ---------------------------------------------------------------------------
// Going — creates visit and redirects
// ---------------------------------------------------------------------------

it('creates a visit with the tonight mode when the user confirms going', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant);
    $mock->allows('eventLabel')->andReturn('Trivia starts at 7pm');

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

it('increments the restaurant visit_count when the user confirms going', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['visit_count' => 0]);

    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant);
    $mock->allows('eventLabel')->andReturn('Bingo starts at 8pm');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->call('going');

    expect($restaurant->fresh()->visit_count)->toBe(1);
});

it('updates last_visited_at on the restaurant when the user confirms going', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['last_visited_at' => null]);

    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant);
    $mock->allows('eventLabel')->andReturn('Live Music at 9pm');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->call('going');

    expect($restaurant->fresh()->last_visited_at)->not->toBeNull();
});

it('redirects to the dashboard after going', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant);
    $mock->allows('eventLabel')->andReturn('Trivia starts at 7pm');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->call('going')
        ->assertRedirect(route('dashboard'));
});

// ---------------------------------------------------------------------------
// Reject — Not this one
// ---------------------------------------------------------------------------

it('adds the current restaurant to excludedIds when rejected', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();
    $other = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant, $other);
    $mock->allows('eventLabel')->andReturn('Trivia starts at 7pm');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->call('reject')
        ->assertSet('excludedIds', [$restaurant->id]);
});

it('automatically finds the next restaurant after rejecting', function () {
    $first = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'First Place']);
    $second = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'Second Place']);

    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($first, $second);
    $mock->allows('eventLabel')->andReturn('Trivia starts at 7pm');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->assertSee('First Place')
        ->call('reject')
        ->assertSee('Second Place');
});

it('shows the empty state when all restaurants have been rejected', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->mock(TonightService::class);
    $mock->allows('pick')->andReturn($restaurant, null);
    $mock->allows('eventLabel')->andReturn('Trivia starts at 7pm');

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->call('reject')
        ->assertSet('state', 'empty');
});

it('passes excluded IDs to the service when picking after a rejection', function () {
    $first = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->mock(TonightService::class);
    $mock->allows('eventLabel')->andReturn('Trivia starts at 7pm');

    $mock->expects('pick')
        ->once()
        ->withArgs(fn ($user, array $excludedIds) => empty($excludedIds))
        ->andReturn($first);

    $mock->expects('pick')
        ->once()
        ->withArgs(fn ($user, array $excludedIds) => in_array($first->id, $excludedIds))
        ->andReturnNull();

    Livewire::actingAs($this->user)
        ->test('pages::tonight')
        ->call('findTonightsPick')
        ->call('reject');
});
