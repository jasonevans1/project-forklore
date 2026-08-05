<?php

use App\Enums\ModeUsed;
use App\Enums\PatioQuality;
use App\Enums\PrimaryCuisine;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Visit;
use App\Services\QuizService;
use App\Services\WeatherData;
use App\Services\WeatherService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->mock(WeatherService::class)->allows('fetch')->andReturnNull();
});

/**
 * Answers dineInTakeout + serviceLevel (steps 1–2) so the wizard lands on
 * step 3 (cuisine). Defaults to 'casual_sit_down' so no steps are skipped.
 */
function answerIntakeSteps(Testable $component, string $serviceLevel = 'casual_sit_down'): Testable
{
    return $component
        ->call('startQuiz')
        ->call('answer', 'dineInTakeout', 'either')
        ->call('answer', 'serviceLevel', $serviceLevel);
}

/**
 * Completes the full 7-step wizard with a 'casual_sit_down' service level
 * (no skips), resolving to the result state.
 */
function completeAllSteps(Testable $component): Testable
{
    return answerIntakeSteps($component)
        ->call('answer', 'cuisine', null)
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'familiarity', 'either');
}

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
// Intro screen
// ---------------------------------------------------------------------------

it('shows the intro screen with a Start quiz button on a fresh visit to the quiz page', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSeeHtml('wire:click="startQuiz"')
        ->assertSee('Start quiz');
});

it('does not show the wizard question content before Start quiz is tapped', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertDontSeeHtml("wire:click=\"answer('dineInTakeout', 'either')\"");
});

it('shows copy mentioning 5 to 7 quick questions on the intro screen', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSee('5-7 quick questions');
});

it('transitions to step 1 of the wizard when Start quiz is tapped', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('startQuiz')
        ->assertSet('introDismissed', true)
        ->assertSeeHtml("wire:click=\"answer('dineInTakeout', 'either')\"");
});

it('skips the intro screen and resumes directly into the wizard when a session snapshot already exists', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'));

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSet('introDismissed', true)
        ->assertSeeHtml("wire:click=\"answer('cuisine', null)\"");
});

it('defaults introDismissed to true and skips the intro when a session snapshot exists without the introDismissed key', function () {
    session(['quiz.wizard' => [
        'step' => 1,
        'state' => 'questions',
        'restaurantId' => null,
        'dineInTakeout' => null,
        'serviceLevel' => null,
        'energy' => null,
        'hunger' => null,
        'familiarity' => null,
        'distance' => null,
        'cuisine' => null,
        'triedFilterLoosens' => [],
        'activeLoosenedField' => null,
    ]]);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSet('introDismissed', true)
        ->assertSeeHtml("wire:click=\"answer('dineInTakeout', 'either')\"");
});

it('persists introDismissed to session immediately after Start quiz is tapped', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('startQuiz');

    expect(session('quiz.wizard.introDismissed'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Initial state — step 1
// ---------------------------------------------------------------------------

it('starts on step 1', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSet('step', 1);
});

it('shows a progress indicator', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('startQuiz')
        ->assertSee('1')
        ->assertSee('7');
});

// ---------------------------------------------------------------------------
// Stepping through the wizard
// ---------------------------------------------------------------------------

it('advances through all 7 steps in order when service level is casual_sit_down', function () {
    answerIntakeSteps(
        Livewire::actingAs($this->user)->test('pages::quiz')
            ->assertSet('step', 1)
    )
        ->assertSet('step', 3)
        ->call('answer', 'cuisine', null)
        ->assertSet('step', 4)
        ->call('answer', 'energy', 'lively')
        ->assertSet('step', 5)
        ->call('answer', 'hunger', 'moderate')
        ->assertSet('step', 6)
        ->call('answer', 'distance', 'anywhere')
        ->assertSet('step', 7);
});

it('skips step 4 (energy) and lands on step 5 (hunger) when service level is quick_easy', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'), 'quick_easy')
        ->assertSet('step', 3)
        ->call('answer', 'cuisine', null)
        ->assertSet('step', 5);
});

it('skips step 7 (familiarity) and resolves the result when service level is quick_easy and all other steps are answered', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'), 'quick_easy')
        ->call('answer', 'cuisine', null)
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'distance', 'anywhere')
        ->assertSet('state', 'result');
});

it('transitions to the result state after all 7 answers are given', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertSet('state', 'result');
});

// ---------------------------------------------------------------------------
// End-to-end flows (happy + skip paths)
// ---------------------------------------------------------------------------

it('completes the full 7-step happy path and reaches the result state', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('answer', 'cuisine', null)
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'familiarity', 'either')
        ->assertSet('state', 'result');
});

it('completes the 5-step skip path when service level is quick_easy and reaches the result state', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'), 'quick_easy')
        ->call('answer', 'cuisine', null)
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'distance', 'anywhere')
        ->assertSet('state', 'result');
});

// ---------------------------------------------------------------------------
// Step content
// ---------------------------------------------------------------------------

it('shows the service level question on step 2', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('startQuiz')
        ->call('answer', 'dineInTakeout', 'either')
        ->assertSee('serviceLevel');
});

it('shows the cuisine question on step 3', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertSee('cuisine');
});

it('shows the energy question on step 4', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('answer', 'cuisine', null)
        ->assertSee('energy');
});

it('shows the hunger question on step 5', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('answer', 'cuisine', null)
        ->call('answer', 'energy', 'lively')
        ->assertSee('hunger');
});

it('shows the distance question on step 6', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('answer', 'cuisine', null)
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->assertSee('distance');
});

it('shows the familiarity question on step 7', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('answer', 'cuisine', null)
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'distance', 'anywhere')
        ->assertSee('familiar');
});

// ---------------------------------------------------------------------------
// Result card
// ---------------------------------------------------------------------------

it('shows the restaurant name on the result card', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'Quiz Noodle Bar']);

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertSee('Quiz Noodle Bar');
});

it('shows the cuisine tags on the result card', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'cuisine_tags' => ['Thai', 'Noodles'],
    ]);

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertSee('Thai')
        ->assertSee('Noodles');
});

it('shows the Going button on the result card', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertSee('Going');
});

it('transitions to the empty state when the service returns null', function () {
    $mock = $this->mock(QuizService::class);
    $mock->allows('topMatch')->andReturnNull();
    $mock->allows('filterExclusionCounts')->andReturn([]);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertSet('state', 'empty');
});

// ---------------------------------------------------------------------------
// Going flow
// ---------------------------------------------------------------------------

it('creates a visit with mode_used=quiz when the user confirms going', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
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

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('going');

    expect($restaurant->fresh()->visit_count)->toBe(4);
});

it('redirects to the dashboard after going', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
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

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('reject')
        ->assertSee('Runner Up');
});

it('shows the empty state when the runner-up is also null', function () {
    $winner = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->mock(QuizService::class);
    $mock->allows('topMatch')->andReturn($winner);
    $mock->allows('runnerUp')->andReturnNull();
    $mock->allows('filterExclusionCounts')->andReturn([]);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('reject')
        ->assertSet('state', 'empty');
});

// ---------------------------------------------------------------------------
// Start over
// ---------------------------------------------------------------------------

it('resets to step 1 when the user starts over from the empty state', function () {
    $mock = $this->mock(QuizService::class);
    $mock->allows('topMatch')->andReturnNull();
    $mock->allows('filterExclusionCounts')->andReturn([]);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('restart')
        ->assertSet('step', 1)
        ->assertSet('state', 'questions');
});

it('shows a start-over action in the header during the questions state', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('startQuiz')
        ->assertSeeHtml('wire:click="restart"');
});

it('resets to step 1 when start-over is triggered mid-quiz', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('answer', 'cuisine', null)
        ->call('restart')
        ->assertSet('step', 1)
        ->assertSet('state', 'questions');
});

it('clears previously given answers when start-over is triggered mid-quiz', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('answer', 'cuisine', null)
        ->call('restart')
        ->assertSet('cuisine', null)
        ->assertSet('energy', null);
});

it('clears dineInTakeout and serviceLevel when start-over is triggered', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('restart')
        ->assertSet('dineInTakeout', null)
        ->assertSet('serviceLevel', null);
});

it('clears the persisted session state when start-over is triggered', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('restart');

    expect(session('quiz.wizard'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Back navigation
// ---------------------------------------------------------------------------

it('returns to the previous step when back is tapped', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'dineInTakeout', 'either')
        ->call('answer', 'serviceLevel', 'casual_sit_down')
        ->call('back')
        ->assertSet('step', 2);
});

it('returns to the previous non-skipped step when back is tapped past a skipped question', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'), 'quick_easy')
        ->call('answer', 'cuisine', null)
        ->assertSet('step', 5)
        ->call('back')
        ->assertSet('step', 3);
});

it('does not render a back button on the first effective step', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertDontSee('Back');
});

it('renders a back button on steps after the first', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('startQuiz')
        ->call('answer', 'dineInTakeout', 'either')
        ->assertSee('Back');
});

it('persists the updated step to session after navigating back', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'dineInTakeout', 'either')
        ->call('answer', 'serviceLevel', 'casual_sit_down')
        ->call('back');

    expect(session('quiz.wizard.step'))->toBe(2);
});

it('retains a previously given answer after navigating back then forward again', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'dineInTakeout', 'either')
        ->call('answer', 'serviceLevel', 'casual_sit_down')
        ->call('back')
        ->assertSet('serviceLevel', 'casual_sit_down')
        ->call('answer', 'serviceLevel', 'casual_sit_down')
        ->assertSet('step', 3);
});

// ---------------------------------------------------------------------------
// Step registry / effective-count helpers
// ---------------------------------------------------------------------------

it('reports effective step number 1 for raw step 1', function () {
    $component = Livewire::actingAs($this->user)->test('pages::quiz');

    expect($component->instance()->effectiveStepNumber(1))->toBe(1);
});

it('reports effective step total of 7 when service level is casual_sit_down', function () {
    $component = answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'));

    expect($component->instance()->effectiveStepTotal())->toBe(7);
});

it('reports effective step total of 5 when service level is quick_easy', function () {
    $component = answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'), 'quick_easy');

    expect($component->instance()->effectiveStepTotal())->toBe(5);
});

// ---------------------------------------------------------------------------
// Session persistence
// ---------------------------------------------------------------------------

it('restores previously answered fields from session when the component remounts', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('answer', 'cuisine', null);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSet('cuisine', null)
        ->assertSet('dineInTakeout', 'either');
});

it('restores dineInTakeout and serviceLevel from session when the component remounts', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'));

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSet('dineInTakeout', 'either')
        ->assertSet('serviceLevel', 'casual_sit_down');
});

it('restores the current step from session when the component remounts', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'));

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSet('step', 3);
});

it('persists each answer to session immediately after answering', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('answer', 'dineInTakeout', 'either');

    expect(session('quiz.wizard.step'))->toBe(2);
});

it('does not restore any state when no session data exists yet', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->assertSet('step', 1)
        ->assertSet('dineInTakeout', null);
});

it('clears the session when the quiz is completed via going', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('going');

    expect(session('quiz.wizard'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Question partial extraction
// ---------------------------------------------------------------------------

it('renders the dine-in/takeout question with dine in, takeout, and either is fine options', function () {
    expect(view()->exists('components.quiz.steps.dineInTakeout'))->toBeTrue();

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('startQuiz')
        ->assertSeeHtml("wire:click=\"answer('dineInTakeout', 'dine_in')\"")
        ->assertSeeHtml("wire:click=\"answer('dineInTakeout', 'takeout')\"")
        ->assertSeeHtml("wire:click=\"answer('dineInTakeout', 'either')\"")
        ->assertSee('Dine in')
        ->assertSee('Takeout')
        ->assertSee('Either is fine');
});

it('renders the service level question with all 4 friendly labels', function () {
    expect(view()->exists('components.quiz.steps.serviceLevel'))->toBeTrue();

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('startQuiz')
        ->call('answer', 'dineInTakeout', 'either')
        ->assertSeeHtml("wire:click=\"answer('serviceLevel', 'quick_easy')\"")
        ->assertSeeHtml("wire:click=\"answer('serviceLevel', 'casual_sit_down')\"")
        ->assertSeeHtml("wire:click=\"answer('serviceLevel', 'nicer_night_out')\"")
        ->assertSeeHtml("wire:click=\"answer('serviceLevel', 'special_occasion')\"")
        ->assertSee('Quick and easy')
        ->assertSee('Casual sit-down')
        ->assertSee('Nicer night out')
        ->assertSee('Special occasion');
});

it('renders 16 cuisine options plus a prominent Surprise me button in a 4x4 grid', function () {
    expect(view()->exists('components.quiz.steps.cuisine'))->toBeTrue();

    $component = answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertSeeHtml("wire:click=\"answer('cuisine', null)\"")
        ->assertSee('Surprise me')
        ->assertSeeHtml('grid grid-cols-4 gap-3');

    foreach (PrimaryCuisine::cases() as $cuisine) {
        if (in_array($cuisine, [PrimaryCuisine::AsianGeneral, PrimaryCuisine::Other], true)) {
            continue;
        }

        $component->assertSeeHtml("wire:click=\"answer('cuisine', '{$cuisine->value}')\"");
    }
});

it('renders the distance question with 4 buckets: under 2mi, 2-5mi, 5-15mi, anywhere', function () {
    expect(view()->exists('components.quiz.steps.distance'))->toBeTrue();

    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('answer', 'cuisine', null)
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->assertSeeHtml("wire:click=\"answer('distance', 'under_2_miles')\"")
        ->assertSeeHtml("wire:click=\"answer('distance', '2_to_5_miles')\"")
        ->assertSeeHtml("wire:click=\"answer('distance', '5_to_15_miles')\"")
        ->assertSeeHtml("wire:click=\"answer('distance', 'anywhere')\"");
});

it('advances past the cuisine step and applies no cuisine constraint when Surprise me is tapped', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('answer', 'cuisine', null)
        ->assertSet('cuisine', null)
        ->assertSet('step', 4);
});

it('renders the energy question from its own partial on step 4', function () {
    expect(view()->exists('components.quiz.steps.energy'))->toBeTrue();

    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('answer', 'cuisine', null)
        ->assertSee("What's your energy tonight?");
});

it('renders the familiarity question from its own partial on the last effective step', function () {
    expect(view()->exists('components.quiz.steps.familiarity'))->toBeTrue();

    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('answer', 'cuisine', null)
        ->call('answer', 'energy', 'lively')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'distance', 'anywhere')
        ->assertSee('Something new or a familiar spot?');
});

it('displays step and total counts derived from the effective step total, not a hardcoded number', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('startQuiz')
        ->assertSee('Step 1 of 7')
        ->assertDontSee('Step 1 of 5');
});

it('shows Step 1 of 7 on the first step for a casual-bound flow', function () {
    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('startQuiz')
        ->assertSee('Step 1 of 7');
});

it('shows Step 3 of 5 after service level quick_easy has been answered', function () {
    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'), 'quick_easy')
        ->assertSee('Step 3 of 5');
});

// ---------------------------------------------------------------------------
// Empty-pool loosen filters
// ---------------------------------------------------------------------------

it('shows a headline naming the most restrictive filter on the empty-pool screen', function () {
    $mock = $this->partialMock(QuizService::class);
    $mock->allows('topMatch')->andReturnNull();
    $mock->allows('filterExclusionCounts')->andReturn([
        'dineInTakeout' => 2, 'serviceLevel' => 5, 'cuisine' => 1, 'distance' => 0,
    ]);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertSee('service level');
});

it('renders up to 3 loosen-filter buttons ranked by exclusion count descending', function () {
    $mock = $this->partialMock(QuizService::class);
    $mock->allows('topMatch')->andReturnNull();
    $mock->allows('filterExclusionCounts')->andReturn([
        'dineInTakeout' => 2, 'serviceLevel' => 5, 'cuisine' => 1, 'distance' => 3,
    ]);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertSeeHtmlInOrder([
            "wire:click=\"loosenFilter('serviceLevel')\"",
            "wire:click=\"loosenFilter('distance')\"",
            "wire:click=\"loosenFilter('dineInTakeout')\"",
        ])
        ->assertDontSeeHtml("wire:click=\"loosenFilter('cuisine')\"");
});

it('still shows the No matches found heading and Start over button on the empty-pool screen', function () {
    $mock = $this->partialMock(QuizService::class);
    $mock->allows('topMatch')->andReturnNull();
    $mock->allows('filterExclusionCounts')->andReturn([
        'dineInTakeout' => 2, 'serviceLevel' => 5, 'cuisine' => 1, 'distance' => 0,
    ]);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertSee('No matches found')
        ->assertSee('Start over');
});

it('transitions to the result state when a loosen-filter button finds a match', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->partialMock(QuizService::class);
    $mock->allows('topMatch')->andReturn(null, $restaurant);
    $mock->allows('filterExclusionCounts')->andReturn([
        'dineInTakeout' => 2, 'serviceLevel' => 5, 'cuisine' => 1, 'distance' => 0,
    ]);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertSet('state', 'empty')
        ->call('loosenFilter', 'serviceLevel')
        ->assertSet('state', 'result')
        ->assertSet('restaurantId', $restaurant->id);
});

it('stays in the empty state and drops the tried filter from future ranking when the loosened pool is still empty', function () {
    $mock = $this->partialMock(QuizService::class);
    $mock->allows('topMatch')->andReturnNull();
    $mock->allows('filterExclusionCounts')->andReturn([
        'dineInTakeout' => 2, 'serviceLevel' => 5, 'cuisine' => 1, 'distance' => 0,
    ]);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('loosenFilter', 'serviceLevel')
        ->assertSet('state', 'empty')
        ->assertSet('triedFilterLoosens', ['serviceLevel'])
        ->assertDontSeeHtml("wire:click=\"loosenFilter('serviceLevel')\"")
        ->assertSeeHtml("wire:click=\"loosenFilter('dineInTakeout')\"");
});

it('does not overwrite the original stored answer when a loosen-filter attempt fails', function () {
    $mock = $this->partialMock(QuizService::class);
    $mock->allows('topMatch')->andReturnNull();
    $mock->allows('filterExclusionCounts')->andReturn([
        'dineInTakeout' => 2, 'serviceLevel' => 5, 'cuisine' => 1, 'distance' => 0,
    ]);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('loosenFilter', 'serviceLevel')
        ->assertSet('serviceLevel', 'casual_sit_down');
});

it('shows a generic fallback message when no filter has any exclusion power', function () {
    $mock = $this->partialMock(QuizService::class);
    $mock->allows('topMatch')->andReturnNull();
    $mock->allows('filterExclusionCounts')->andReturn([
        'dineInTakeout' => 0, 'serviceLevel' => 0, 'cuisine' => 0, 'distance' => 0,
    ]);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertSee('Add more favorites');
});

it('resets triedFilterLoosens and activeLoosenedField when the user starts over', function () {
    $mock = $this->partialMock(QuizService::class);
    $mock->allows('topMatch')->andReturnNull();
    $mock->allows('filterExclusionCounts')->andReturn([
        'dineInTakeout' => 2, 'serviceLevel' => 5, 'cuisine' => 1, 'distance' => 0,
    ]);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('loosenFilter', 'serviceLevel')
        ->assertSet('triedFilterLoosens', ['serviceLevel'])
        ->call('restart')
        ->assertSet('triedFilterLoosens', [])
        ->assertSet('activeLoosenedField', null);
});

it('shows the runner-up from the loosened pool when Not this one is tapped after loosening a filter', function () {
    $winner = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'Loosened Winner']);
    $runnerUp = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'Loosened Runner Up']);

    $mock = $this->partialMock(QuizService::class);
    $mock->allows('filterExclusionCounts')->andReturn([
        'dineInTakeout' => 0, 'serviceLevel' => 5, 'cuisine' => 0, 'distance' => 0,
    ]);
    $mock->allows('topMatch')->andReturn(null, $winner);
    $mock->shouldReceive('runnerUp')
        ->withArgs(fn ($user, $answers, $restaurantWinner, $weather) => $answers->serviceLevel !== 'casual_sit_down')
        ->andReturn($runnerUp);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('loosenFilter', 'serviceLevel')
        ->assertSet('state', 'result')
        ->call('reject')
        ->assertSee('Loosened Runner Up');
});

// ---------------------------------------------------------------------------
// Result card — distance, tagline, show-runner-up peek
// ---------------------------------------------------------------------------

it('shows a distance label on the quiz result card when coordinates are available', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 41.60,
        'lng' => -93.60,
    ]);

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    completeAllSteps(
        Livewire::actingAs($this->user)->test('pages::quiz')
            ->set('lat', 41.58)
            ->set('lng', -93.62)
    )->assertSee('mi');
});

it('shows no distance label on the quiz result card when coordinates are unavailable', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 41.60,
        'lng' => -93.60,
    ]);

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertSet('distanceLabel', null);
});

it('shows a weather-aware tagline on the quiz result card', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'patio_quality' => PatioQuality::Destination,
        'lat' => 41.58,
        'lng' => -93.62,
    ]);

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    // 22 °C = 71.6 °F, no rain — ideal patio weather
    $this->mock(WeatherService::class)->allows('fetch')->andReturn(new WeatherData(
        temperature: 22.0,
        conditions: 'Clear',
        precipitation: 0.0,
        windSpeed: 2.0,
        sunset: CarbonImmutable::now()->addHours(4),
        units: 'metric',
    ));

    completeAllSteps(
        Livewire::actingAs($this->user)->test('pages::quiz')
            ->set('lat', 41.58)
            ->set('lng', -93.62)
    )->assertSee('Perfect patio weather');
});

it('reveals the runner-up name when Show runner-up is tapped', function () {
    $winner = Restaurant::factory()->for($this->user, 'user')->create();
    $runnerUp = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'Peeked Runner Up']);

    $mock = $this->mock(QuizService::class);
    $mock->allows('topMatch')->andReturn($winner);
    $mock->allows('runnerUp')->andReturn($runnerUp);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('peekRunnerUp')
        ->assertSee('Peeked Runner Up');
});

it('does not change the displayed restaurant when Show runner-up is tapped', function () {
    $winner = Restaurant::factory()->for($this->user, 'user')->create();
    $runnerUp = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->mock(QuizService::class);
    $mock->allows('topMatch')->andReturn($winner);
    $mock->allows('runnerUp')->andReturn($runnerUp);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('peekRunnerUp')
        ->assertSet('restaurantId', $winner->id)
        ->assertSet('state', 'result');
});

it('shows a no-other-match indicator when Show runner-up is tapped and no runner-up exists', function () {
    $winner = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->mock(QuizService::class);
    $mock->allows('topMatch')->andReturn($winner);
    $mock->allows('runnerUp')->andReturnNull();

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('peekRunnerUp')
        ->assertSet('state', 'result')
        ->assertSet('restaurantId', $winner->id)
        ->assertSee('No other match');
});

it('clears the peeked runner-up name after Not this one swaps to a new restaurant', function () {
    $winner = Restaurant::factory()->for($this->user, 'user')->create();
    $peekedRunnerUp = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'Peeked Only']);
    $rejectRunnerUp = Restaurant::factory()->for($this->user, 'user')->create();

    $mock = $this->mock(QuizService::class);
    $mock->allows('topMatch')->andReturn($winner);
    $mock->allows('runnerUp')->andReturn($peekedRunnerUp, $rejectRunnerUp);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->call('peekRunnerUp')
        ->assertSet('peekedRunnerUpName', 'Peeked Only')
        ->call('reject')
        ->assertSet('peekedRunnerUpName', null);
});

it('populates the tagline and distance label after Not this one swaps to the runner-up', function () {
    $winner = Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 41.60,
        'lng' => -93.60,
    ]);
    $runnerUp = Restaurant::factory()->for($this->user, 'user')->create([
        'lat' => 41.61,
        'lng' => -93.61,
    ]);

    $mock = $this->mock(QuizService::class);
    $mock->allows('topMatch')->andReturn($winner);
    $mock->allows('runnerUp')->andReturn($runnerUp);

    completeAllSteps(
        Livewire::actingAs($this->user)->test('pages::quiz')
            ->set('lat', 41.58)
            ->set('lng', -93.62)
    )
        ->call('reject')
        ->assertSet('restaurantId', $runnerUp->id)
        ->assertSee('mi');
});

// ---------------------------------------------------------------------------
// Skipped-steps indicator + completion log
// ---------------------------------------------------------------------------

it('shows a skipped-questions message on the result card when service level is quick_easy', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'), 'quick_easy')
        ->call('answer', 'cuisine', null)
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'distance', 'anywhere')
        ->assertSee('You picked fast food, so we skipped 2 questions');
});

it('does not show a skipped-questions message on the result card when service level is not quick_easy', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertDontSee('so we skipped');
});

it('logs the quiz completion with skipped step names and reason when service level is quick_easy', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Log::shouldReceive('info')->once()->with('Quiz completed', [
        'user_id' => $this->user->id,
        'skipped_steps' => ['energy', 'familiarity'],
        'skip_reason' => 'quick_easy_service_level',
    ]);

    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'), 'quick_easy')
        ->call('answer', 'cuisine', null)
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'distance', 'anywhere');
});

it('logs the quiz completion with an empty skipped-steps list and no reason when no steps were skipped', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Log::shouldReceive('info')->once()->with('Quiz completed', [
        'user_id' => $this->user->id,
        'skipped_steps' => [],
        'skip_reason' => null,
    ]);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'));
});

it('renders exactly the 5 non-skipped steps end to end, starting from the entry screen, for a fast-food quick_easy selection', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('startQuiz')
        ->assertSeeHtml("wire:click=\"answer('dineInTakeout', 'either')\"")
        ->call('answer', 'dineInTakeout', 'either')
        ->assertSeeHtml("wire:click=\"answer('serviceLevel', 'quick_easy')\"")
        ->call('answer', 'serviceLevel', 'quick_easy')
        ->assertSee('cuisine')
        ->assertSee('Step 3 of 5')
        ->call('answer', 'cuisine', null)
        ->assertSee('hunger')
        ->assertSee('Step 4 of 5')
        ->call('answer', 'hunger', 'moderate')
        ->assertSee('distance')
        ->assertSee('Step 5 of 5')
        ->call('answer', 'distance', 'anywhere')
        ->assertSet('state', 'result');
});

it('renders all 7 steps end to end, starting from the entry screen, for a fine-dining special_occasion selection', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Livewire::actingAs($this->user)
        ->test('pages::quiz')
        ->call('startQuiz')
        ->assertSeeHtml("wire:click=\"answer('dineInTakeout', 'either')\"")
        ->call('answer', 'dineInTakeout', 'either')
        ->assertSeeHtml("wire:click=\"answer('serviceLevel', 'special_occasion')\"")
        ->call('answer', 'serviceLevel', 'special_occasion')
        ->assertSee('cuisine')
        ->assertSee('Step 3 of 7')
        ->call('answer', 'cuisine', null)
        ->assertSee('energy')
        ->assertSee('Step 4 of 7')
        ->call('answer', 'energy', 'lively')
        ->assertSee('hunger')
        ->assertSee('Step 5 of 7')
        ->call('answer', 'hunger', 'moderate')
        ->assertSee('distance')
        ->assertSee('Step 6 of 7')
        ->call('answer', 'distance', 'anywhere')
        ->assertSee('familiar')
        ->assertSee('Step 7 of 7')
        ->call('answer', 'familiarity', 'either')
        ->assertSet('state', 'result');
});

it('still shows all 7 question option buttons with unchanged copy after extraction', function () {
    answerIntakeSteps(
        Livewire::actingAs($this->user)->test('pages::quiz')
            ->call('startQuiz')
            ->assertSeeHtml("wire:click=\"answer('dineInTakeout', 'either')\"")
    )
        ->assertSeeHtml("wire:click=\"answer('cuisine', null)\"")
        ->call('answer', 'cuisine', null)
        ->assertSeeHtml("wire:click=\"answer('energy', 'lively')\"")
        ->assertSee('🎉 Lively')
        ->call('answer', 'energy', 'moderate')
        ->assertSeeHtml("wire:click=\"answer('hunger', 'quick_bite')\"")
        ->assertSee('🥪 Quick bite')
        ->call('answer', 'hunger', 'moderate')
        ->assertSeeHtml("wire:click=\"answer('distance', 'under_2_miles')\"")
        ->assertSee('Under 2 mi')
        ->call('answer', 'distance', 'anywhere')
        ->assertSeeHtml("wire:click=\"answer('familiarity', 'new')\"")
        ->assertSee('🗺️ Something new');
});

// ---------------------------------------------------------------------------
// Restaurant result ticket restyle
// ---------------------------------------------------------------------------

it('renders the result using the restaurant result ticket component', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create([
        'cuisine_tags' => ['Thai', 'Noodles'],
    ]);

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertSee('cuisine-tags', false);
});

it('still shows the skipped-steps message below the result ticket', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    answerIntakeSteps(Livewire::actingAs($this->user)->test('pages::quiz'), 'quick_easy')
        ->call('answer', 'cuisine', null)
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'distance', 'anywhere')
        ->assertSee('You picked fast food, so we skipped 2 questions');
});

it('still shows the going, reject, and runner-up buttons after the restyle', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    completeAllSteps(Livewire::actingAs($this->user)->test('pages::quiz'))
        ->assertSeeHtml('wire:click="going"')
        ->assertSeeHtml('wire:click="reject"')
        ->assertSeeHtml('wire:click="peekRunnerUp"')
        ->assertSee('Going ✓')
        ->assertSee('Not this one')
        ->assertSee('Show runner-up');
});
