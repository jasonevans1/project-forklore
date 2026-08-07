<?php

use App\Enums\ModeUsed;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Visit;
use App\Services\TournamentService;
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
        ->get(route('tournament'))
        ->assertOk();
});

it('redirects guests to the login page', function () {
    $this->get(route('tournament'))
        ->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Seeding state — idle / setup
// ---------------------------------------------------------------------------

it('starts in the setup state', function () {
    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->assertSet('state', 'setup');
});

it('shows the Start tournament button in the setup state', function () {
    Restaurant::factory()->for($this->user, 'user')->count(4)->create();

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->assertSee('Start tournament');
});

it('transitions to bracket state after calling start', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->assertSet('state', 'bracket');
});

it('transitions to the empty state when fewer than 4 favorites exist', function () {
    Restaurant::factory()->for($this->user, 'user')->count(3)->create();

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn([]);

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->assertSet('state', 'empty');
});

it('shows an empty state message when there are not enough favorites', function () {
    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn([]);

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->assertSee('Not enough favorites');
});

// ---------------------------------------------------------------------------
// Bracket display
// ---------------------------------------------------------------------------

it('stores the bracket IDs after starting', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());

    $component = Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start');

    expect($component->get('bracketIds'))->toHaveCount(4);
});

it('shows two restaurant names in the first matchup after starting', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create([
        'name' => fn () => fake()->unique()->company(),
    ]);

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());

    $component = Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start');

    $component->assertSee($restaurants[0]->name)
        ->assertSee($restaurants[1]->name);
});

it('does not show the third restaurant until the first matchup is resolved', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create([
        'name' => fn () => fake()->unique()->company(),
    ]);

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->assertDontSee($restaurants[2]->name);
});

it('tracks the current matchup index starting at 0', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->assertSet('matchupIndex', 0);
});

// ---------------------------------------------------------------------------
// Picking — advance through matchups
// ---------------------------------------------------------------------------

it('records a winner when pick is called with a restaurant ID', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());

    $component = Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('pick', $restaurants[0]->id);

    expect($component->get('roundWinnerIds'))->toContain($restaurants[0]->id);
});

it('advances to the next matchup after a pick', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('pick', $restaurants[0]->id)
        ->assertSet('matchupIndex', 1);
});

it('shows the second matchup pair after the first pick', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create([
        'name' => fn () => fake()->unique()->company(),
    ]);

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('pick', $restaurants[0]->id)
        ->assertSee($restaurants[2]->name)
        ->assertSee($restaurants[3]->name);
});

// ---------------------------------------------------------------------------
// Round completion — advancing to next round
// ---------------------------------------------------------------------------

it('calls advance() after all matchups in a round are complete', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();
    $nextRound = [$restaurants[0], $restaurants[2]];

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());
    $mock->expects('advance')->once()->andReturn($nextRound);

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('pick', $restaurants[0]->id)
        ->call('pick', $restaurants[2]->id);
});

it('resets matchupIndex to 0 when a new round begins', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();
    $nextRound = [$restaurants[0], $restaurants[2]];

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());
    $mock->allows('advance')->andReturn($nextRound);

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('pick', $restaurants[0]->id)
        ->call('pick', $restaurants[2]->id)
        ->assertSet('matchupIndex', 0);
});

it('updates bracketIds with the winners of the completed round', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();
    $nextRound = [$restaurants[0], $restaurants[2]];

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());
    $mock->allows('advance')->andReturn($nextRound);

    $component = Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('pick', $restaurants[0]->id)
        ->call('pick', $restaurants[2]->id);

    expect($component->get('bracketIds'))->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// Final winner
// ---------------------------------------------------------------------------

it('transitions to the result state when the final matchup is resolved', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();
    $finalist1 = $restaurants[0];
    $finalist2 = $restaurants[2];
    $champion = $restaurants[0];

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());
    $mock->allows('advance')->andReturn([$finalist1, $finalist2]);
    $mock->allows('winner')->andReturn($champion);

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        // Round 1
        ->call('start')
        ->call('pick', $restaurants[0]->id)
        ->call('pick', $restaurants[2]->id)
        // Final
        ->call('pick', $finalist1->id)
        ->assertSet('state', 'result');
});

it('shows the champion restaurant name on the result card', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();
    $champion = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'The Champion']);
    $nextRound = [$champion, $restaurants[2]];

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());
    $mock->allows('advance')->andReturn($nextRound);
    $mock->allows('winner')->andReturn($champion);

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('pick', $restaurants[0]->id)
        ->call('pick', $restaurants[2]->id)
        ->call('pick', $champion->id)
        ->assertSee('The Champion');
});

it('shows the Going button on the result card', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();
    $champion = $restaurants[0];
    $nextRound = [$champion, $restaurants[2]];

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());
    $mock->allows('advance')->andReturn($nextRound);
    $mock->allows('winner')->andReturn($champion);

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('pick', $restaurants[0]->id)
        ->call('pick', $restaurants[2]->id)
        ->call('pick', $champion->id)
        ->assertSee('Going');
});

// ---------------------------------------------------------------------------
// Result ticket restyle
// ---------------------------------------------------------------------------

it('renders the champion using the restaurant result ticket component', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();
    $champion = $restaurants[0];
    $nextRound = [$champion, $restaurants[2]];

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());
    $mock->allows('advance')->andReturn($nextRound);
    $mock->allows('winner')->andReturn($champion);

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('pick', $restaurants[0]->id)
        ->call('pick', $restaurants[2]->id)
        ->call('pick', $champion->id)
        ->assertSeeHtml('font-family: var(--font-display);');
});

it('shows the Tournament Champion badge label', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();
    $champion = $restaurants[0];
    $nextRound = [$champion, $restaurants[2]];

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());
    $mock->allows('advance')->andReturn($nextRound);
    $mock->allows('winner')->andReturn($champion);

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('pick', $restaurants[0]->id)
        ->call('pick', $restaurants[2]->id)
        ->call('pick', $champion->id)
        ->assertSee('Tournament Champion');
});

it('still shows the going and play-again buttons after the restyle', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();
    $champion = $restaurants[0];
    $nextRound = [$champion, $restaurants[2]];

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());
    $mock->allows('advance')->andReturn($nextRound);
    $mock->allows('winner')->andReturn($champion);

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('pick', $restaurants[0]->id)
        ->call('pick', $restaurants[2]->id)
        ->call('pick', $champion->id)
        ->assertSee('Going')
        ->assertSee('Play again');
});

// ---------------------------------------------------------------------------
// Going flow
// ---------------------------------------------------------------------------

it('creates a visit with mode_used=tournament when going', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();
    $champion = $restaurants[0];
    $nextRound = [$champion, $restaurants[2]];

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());
    $mock->allows('advance')->andReturn($nextRound);
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

it('redirects to the dashboard after going', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();
    $champion = $restaurants[0];
    $nextRound = [$champion, $restaurants[2]];

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());
    $mock->allows('advance')->andReturn($nextRound);
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

// ---------------------------------------------------------------------------
// Restart
// ---------------------------------------------------------------------------

it('resets to setup state when restart is called', function () {
    $restaurants = Restaurant::factory()->for($this->user, 'user')->count(4)->create();

    $mock = $this->mock(TournamentService::class);
    $mock->allows('seed')->andReturn($restaurants->all());

    Livewire::actingAs($this->user)
        ->test('pages::tournament')
        ->call('start')
        ->call('restart')
        ->assertSet('state', 'setup')
        ->assertSet('bracketIds', [])
        ->assertSet('matchupIndex', 0)
        ->assertSet('roundWinnerIds', []);
});
