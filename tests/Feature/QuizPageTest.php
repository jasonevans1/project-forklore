<?php

use App\Enums\ModeUsed;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Visit;
use App\Services\QuizService;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->mock(WeatherService::class)->allows('fetch')->andReturnNull();
});

// ---------------------------------------------------------------------------
// Route access
// ---------------------------------------------------------------------------

it('is accessible to authenticated users', function () {
    $this->actingAs($this->user)
        ->get(route('quiz'))
        ->assertOk();
});

it('redirects guests to the login page', function () {
    $this->get(route('quiz'))
        ->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Initial state — step 1
// ---------------------------------------------------------------------------

it('starts on step 1', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSet('step', 1);
});

it('shows the energy level question on step 1', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSee('energy');
});

it('shows a progress indicator', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSee('1')
        ->assertSee('5');
});

// ---------------------------------------------------------------------------
// Stepping through all 5 questions
// ---------------------------------------------------------------------------

it('advances to step 2 after answering the energy question', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->assertSet('step', 2);
});

it('advances to step 3 after answering the hunger question', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->assertSet('step', 3);
});

it('advances to step 4 after answering the familiarity question', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->assertSet('step', 4);
});

it('advances to step 5 after answering the distance question', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->assertSet('step', 5);
});

it('transitions to the result state after all 5 answers are given', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'cuisine', null)
        ->assertSet('state', 'result');
});

// ---------------------------------------------------------------------------
// Step content
// ---------------------------------------------------------------------------

it('shows the hunger question on step 2', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'moderate')
        ->assertSee('hunger');
});

it('shows the familiarity question on step 3', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'moderate')
        ->call('answer', 'hunger', 'moderate')
        ->assertSee('familiar');
});

it('shows the distance question on step 4', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'moderate')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->assertSee('distance');
});

it('shows the cuisine question on step 5', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'moderate')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->assertSee('cuisine');
});

// ---------------------------------------------------------------------------
// Result card
// ---------------------------------------------------------------------------

it('shows the restaurant name on the result card', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'Quiz Noodle Bar']);

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'cuisine', null)
        ->assertSee('Quiz Noodle Bar');
});

it('shows the cuisine tags on the result card', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'cuisine_tags' => ['Thai', 'Noodles'],
    ]);

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'cuisine', null)
        ->assertSee('Thai')
        ->assertSee('Noodles');
});

it('shows the Going button on the result card', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'cuisine', null)
        ->assertSee('Going');
});

it('transitions to the empty state when the service returns null', function () {
    $this->mock(QuizService::class)->allows('topMatch')->andReturnNull();

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'cuisine', null)
        ->assertSet('state', 'empty');
});

// ---------------------------------------------------------------------------
// Going flow
// ---------------------------------------------------------------------------

it('creates a visit with mode_used=quiz when the user confirms going', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
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

it('increments visit_count when going', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['visit_count' => 3]);

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'cuisine', null)
        ->call('going');

    expect($restaurant->fresh()->visit_count)->toBe(4);
});

it('redirects to the dashboard after going', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'cuisine', null)
        ->call('going')
        ->assertRedirect(route('dashboard'));
});

// ---------------------------------------------------------------------------
// Try runner-up
// ---------------------------------------------------------------------------

it('shows the runner-up restaurant when the user taps Not this one', function () {
    $winner = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'First Pick']);
    $runnerUp = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'Runner Up']);

    $mock = $this->mock(QuizService::class);
    $mock->allows('topMatch')->andReturn($winner);
    $mock->allows('runnerUp')->andReturn($runnerUp);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'cuisine', null)
        ->call('reject')
        ->assertSee('Runner Up');
});

it('shows the empty state when the runner-up is also null', function () {
    $winner = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->mock(QuizService::class);
    $mock->allows('topMatch')->andReturn($winner);
    $mock->allows('runnerUp')->andReturnNull();

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'cuisine', null)
        ->call('reject')
        ->assertSet('state', 'empty');
});

// ---------------------------------------------------------------------------
// Start over
// ---------------------------------------------------------------------------

it('resets to step 1 when the user starts over from the empty state', function () {
    $this->mock(QuizService::class)->allows('topMatch')->andReturnNull();

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'cuisine', null)
        ->call('restart')
        ->assertSet('step', 1)
        ->assertSet('state', 'questions');
});
