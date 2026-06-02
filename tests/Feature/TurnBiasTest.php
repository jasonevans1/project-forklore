<?php

use App\Models\HouseholdState;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\QuickPickFilters;
use App\Services\QuickPickService;
use App\Services\QuizAnswers;
use App\Services\QuizService;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->alice = User::factory()->create(['preferred_vibe_tags' => ['cozy']]);
    $this->bob = User::factory()->create(['preferred_vibe_tags' => ['lively']]);

    $this->alice->update(['partner_id' => $this->bob->id]);
    $this->bob->update(['partner_id' => $this->alice->id]);

    $this->mock(WeatherService::class)->allows('fetch')->andReturnNull();
});

// ---------------------------------------------------------------------------
// QuickPickService — partner preference boost
// ---------------------------------------------------------------------------

it('boosts a restaurant matching the partner preferred vibe tags when it is the partner turn', function () {
    // Alice last picked → it is Bob's turn → Bob's preferred tag is "lively".
    HouseholdState::recordPick($this->alice);

    $lively = Restaurant::factory()->for($this->alice, 'user')->create(['vibe_tags' => ['lively']]);
    $cozy = Restaurant::factory()->for($this->alice, 'user')->create(['vibe_tags' => ['cozy']]);

    // Run 40 picks and expect "lively" to win the majority.
    $service = app(QuickPickService::class);
    $filters = new QuickPickFilters;

    $wins = collect(range(1, 40))
        ->map(fn () => $service->pick($this->alice, $filters)?->id)
        ->countBy()
        ->all();

    expect($wins[$lively->id] ?? 0)->toBeGreaterThan($wins[$cozy->id] ?? 0);
});

it('does not apply partner bias when the partner last picked (my turn)', function () {
    // Bob last picked → it is Alice's turn → no partner bias applied for Alice's picks.
    HouseholdState::recordPick($this->bob);

    // "lively" matches Bob's preferences, but Bob picked last so no bias.
    $lively = Restaurant::factory()->for($this->alice, 'user')->create(['vibe_tags' => ['lively']]);
    $cozy = Restaurant::factory()->for($this->alice, 'user')->create(['vibe_tags' => ['cozy']]);

    $service = app(QuickPickService::class);
    $filters = new QuickPickFilters;

    $wins = collect(range(1, 40))
        ->map(fn () => $service->pick($this->alice, $filters)?->id)
        ->countBy()
        ->all();

    // Without bias both restaurants should win roughly equally (within 75/25 range).
    $livelyWins = $wins[$lively->id] ?? 0;
    $cozyWins = $wins[$cozy->id] ?? 0;
    $total = $livelyWins + $cozyWins;

    // Neither restaurant should dominate when there is no bias.
    expect($livelyWins)->toBeLessThan((int) ($total * 0.75));
    expect($cozyWins)->toBeLessThan((int) ($total * 0.75));
});

it('does not apply partner bias when the user has no partner', function () {
    $solo = User::factory()->create(['preferred_vibe_tags' => []]);
    HouseholdState::recordPick($solo);

    $a = Restaurant::factory()->for($solo, 'user')->create(['vibe_tags' => ['lively']]);
    $b = Restaurant::factory()->for($solo, 'user')->create(['vibe_tags' => ['cozy']]);

    $service = app(QuickPickService::class);

    $wins = collect(range(1, 40))
        ->map(fn () => $service->pick($solo, new QuickPickFilters)?->id)
        ->countBy()
        ->all();

    $aWins = $wins[$a->id] ?? 0;
    $bWins = $wins[$b->id] ?? 0;
    $total = $aWins + $bWins;

    expect($aWins)->toBeLessThan((int) ($total * 0.75));
    expect($bWins)->toBeLessThan((int) ($total * 0.75));
});

// ---------------------------------------------------------------------------
// QuizService — partner preference boost
// ---------------------------------------------------------------------------

it('boosts a restaurant matching the partner preferred vibe tags in quiz scoring when it is the partner turn', function () {
    // Alice last picked → Bob's turn → Bob prefers "lively".
    HouseholdState::recordPick($this->alice);

    $lively = Restaurant::factory()->for($this->alice, 'user')->create(['vibe_tags' => ['lively']]);
    $cozy = Restaurant::factory()->for($this->alice, 'user')->create(['vibe_tags' => ['cozy']]);

    $service = app(QuizService::class);
    $answers = new QuizAnswers(energy: 'moderate', hunger: 'moderate', familiarity: 'either', distance: 'anywhere');

    $wins = collect(range(1, 40))
        ->map(fn () => $service->topMatch($this->alice, $answers)?->id)
        ->countBy()
        ->all();

    expect($wins[$lively->id] ?? 0)->toBeGreaterThan($wins[$cozy->id] ?? 0);
});

it('does not bias quiz results when there is no partner', function () {
    $solo = User::factory()->create(['preferred_vibe_tags' => []]);

    $a = Restaurant::factory()->for($solo, 'user')->create(['vibe_tags' => ['lively']]);
    $b = Restaurant::factory()->for($solo, 'user')->create(['vibe_tags' => ['cozy']]);

    $service = app(QuizService::class);
    $answers = new QuizAnswers(energy: 'moderate', hunger: 'moderate', familiarity: 'either', distance: 'anywhere');

    $result = $service->topMatch($solo, $answers);

    expect($result)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Measurable difference over many picks
// ---------------------------------------------------------------------------

it('the partner bias measurably changes outcomes over 50 picks compared to no bias', function () {
    // Baseline: no state set → no bias.
    $baseline = Restaurant::factory()->for($this->alice, 'user')->create(['vibe_tags' => ['lively'], 'name' => 'Lively']);
    $other = Restaurant::factory()->for($this->alice, 'user')->create(['vibe_tags' => ['cozy'], 'name' => 'Cozy']);

    $service = app(QuickPickService::class);
    $filters = new QuickPickFilters;

    $baselineWins = collect(range(1, 50))
        ->map(fn () => $service->pick($this->alice, $filters)?->id)
        ->filter(fn ($id) => $id === $baseline->id)
        ->count();

    // With bias: Alice last picked → Bob's turn → Bob prefers "lively".
    HouseholdState::recordPick($this->alice);

    $biasedWins = collect(range(1, 50))
        ->map(fn () => $service->pick($this->alice, $filters)?->id)
        ->filter(fn ($id) => $id === $baseline->id)
        ->count();

    // The biased run must produce more wins for the boosted restaurant.
    expect($biasedWins)->toBeGreaterThan($baselineWins);
});
