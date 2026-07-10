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

it('shows a start-over action in the header during the questions state', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSeeHtml('wire:click="restart"');
});

it('resets to step 1 when start-over is triggered mid-quiz', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('restart')
        ->assertSet('step', 1)
        ->assertSet('state', 'questions');
});

it('clears previously given answers when start-over is triggered mid-quiz', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('restart')
        ->assertSet('energy', null)
        ->assertSet('hunger', null);
});

it('clears the persisted session state when start-over is triggered', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('restart');

    expect(session('quiz.wizard'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Back navigation
// ---------------------------------------------------------------------------

it('returns to the previous step when back is tapped', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('back')
        ->assertSet('step', 2);
});

it('skips reserved placeholder slots when navigating back', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->set('step', 7)
        ->call('back')
        ->assertSet('step', 5);
});

it('does not render a back button on the first effective step', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertDontSee('Back');
});

it('renders a back button on steps after the first', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->assertSee('Back');
});

it('persists the updated step to session after navigating back', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('back');

    expect(session('quiz.wizard.step'))->toBe(2);
});

it('retains a previously given answer after navigating back then forward again', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('back')
        ->assertSet('hunger', 'moderate')
        ->call('answer', 'hunger', 'moderate')
        ->assertSet('step', 3);
});

// ---------------------------------------------------------------------------
// Step registry / effective-count helpers
// ---------------------------------------------------------------------------

it('reports effective step number 1 for raw step 1', function () {
    $component = Livewire::actingAs($this->user)->test('pages::quiz');

    expect($component->instance()->effectiveStepNumber(1))->toBe(1);
});

it('reports effective step total of 5 given the current registry', function () {
    $component = Livewire::actingAs($this->user)->test('pages::quiz');

    expect($component->instance()->effectiveStepTotal())->toBe(5);
});

it('advances from raw step 5 to resolving the result instead of a raw step 6 or 7', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'cuisine', null)
        ->assertSet('state', 'result')
        ->assertSet('step', 5);
});

// ---------------------------------------------------------------------------
// Session persistence
// ---------------------------------------------------------------------------

it('restores previously answered fields from session when the component remounts', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate');

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSet('energy', 'lively')
        ->assertSet('hunger', 'moderate');
});

it('restores the current step from session when the component remounts', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate');

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSet('step', 3);
});

it('persists each answer to session immediately after answering', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively');

    expect(session('quiz.wizard.step'))->toBe(2);
});

it('does not restore any state when no session data exists yet', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSet('step', 1)
        ->assertSet('energy', null);
});

it('clears the session when the quiz is completed via going', function () {
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

    expect(session('quiz.wizard'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Question partial extraction
// ---------------------------------------------------------------------------

it('renders the energy question from its own partial on step 1', function () {
    expect(view()->exists('components.quiz.steps.energy'))->toBeTrue();

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSee("What's your energy tonight?");
});

it('renders the cuisine question from its own partial on the last effective step', function () {
    expect(view()->exists('components.quiz.steps.cuisine'))->toBeTrue();

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'familiarity', 'either')
        ->call('answer', 'distance', 'anywhere')
        ->assertSee('Any cuisine in mind?');
});

it('displays step and total counts derived from the effective step total, not a hardcoded number', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->set('step', 7)
        ->assertSee('Step 5 of 5')
        ->assertDontSee('Step 7 of 5');
});

it('still shows all 5 question option buttons with unchanged copy after extraction', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSeeHtml("wire:click=\"answer('energy', 'lively')\"")
        ->assertSee('🎉 Lively')
        ->call('answer', 'energy', 'moderate')
        ->assertSeeHtml("wire:click=\"answer('hunger', 'light')\"")
        ->assertSee('🥗 Light bite')
        ->call('answer', 'hunger', 'moderate')
        ->assertSeeHtml("wire:click=\"answer('familiarity', 'new')\"")
        ->assertSee('🗺️ Something new')
        ->call('answer', 'familiarity', 'either')
        ->assertSeeHtml("wire:click=\"answer('distance', 'nearby')\"")
        ->assertSee('📍 Nearby')
        ->call('answer', 'distance', 'anywhere')
        ->assertSeeHtml("wire:click=\"answer('cuisine', 'Italian')\"")
        ->assertSee('Italian');
});
