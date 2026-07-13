<?php

use App\Models\HouseholdState;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\QuickPickService;
use App\Services\QuizService;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->alice = User::factory()->create(['name' => 'Alice']);
    $this->bob = User::factory()->create(['name' => 'Bob']);

    $this->alice->update(['partner_id' => $this->bob->id]);
    $this->bob->update(['partner_id' => $this->alice->id]);

    $this->mock(WeatherService::class)->allows('fetch')->andReturnNull();
});

// ---------------------------------------------------------------------------
// Quick Pick — turn indicator visibility
// ---------------------------------------------------------------------------

it('shows a turn indicator on the quick pick page when a partner exists', function () {
    Livewire::actingAs($this->alice)
        ->test('pages::pick')
        ->assertSee('turn');
});

it('shows "Your turn" when the partner last picked', function () {
    HouseholdState::recordPick($this->bob);

    Livewire::actingAs($this->alice)
        ->test('pages::pick')
        ->assertSee('Your turn');
});

it('shows "Partner\'s turn" when the current user last picked', function () {
    HouseholdState::recordPick($this->alice);

    Livewire::actingAs($this->alice)
        ->test('pages::pick')
        ->assertSee("Partner's turn");
});

it('does not show a turn indicator when the user has no partner', function () {
    $solo = User::factory()->create();

    Livewire::actingAs($solo)
        ->test('pages::pick')
        ->assertDontSee('turn');
});

// ---------------------------------------------------------------------------
// Quick Pick — going() records the pick
// ---------------------------------------------------------------------------

it('records the current user as last picker after going on quick pick', function () {
    $restaurant = Restaurant::factory()->for($this->alice, 'user')->create();

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->alice)
        ->test('pages::pick')
        ->call('pick')
        ->call('going');

    $this->assertDatabaseHas('household_state', [
        'user_id' => $this->alice->id,
        'last_picker_id' => $this->alice->id,
    ]);
});

it('updates the partner household_state row too after going on quick pick', function () {
    $restaurant = Restaurant::factory()->for($this->alice, 'user')->create();

    $this->mock(QuickPickService::class)->allows('pick')->andReturn($restaurant);

    Livewire::actingAs($this->alice)
        ->test('pages::pick')
        ->call('pick')
        ->call('going');

    $this->assertDatabaseHas('household_state', [
        'user_id' => $this->bob->id,
        'last_picker_id' => $this->alice->id,
    ]);
});

// ---------------------------------------------------------------------------
// Quiz — turn indicator
// ---------------------------------------------------------------------------

it('shows a turn indicator on the quiz page when a partner exists', function () {
    Livewire::actingAs($this->alice)
        ->test('pages::quiz')
        ->assertSee('turn');
});

it('shows "Your turn" on quiz page when the partner last picked', function () {
    HouseholdState::recordPick($this->bob);

    Livewire::actingAs($this->alice)
        ->test('pages::quiz')
        ->assertSee('Your turn');
});

// ---------------------------------------------------------------------------
// Quiz — going() records the pick
// ---------------------------------------------------------------------------

it('records the current user as last picker after going on quiz', function () {
    $restaurant = Restaurant::factory()->for($this->alice, 'user')->create();

    $mock = app()->make(QuizService::class);
    $this->mock(QuizService::class)->allows('topMatch')->andReturn($restaurant);

    Livewire::actingAs($this->alice)
        ->test('pages::quiz')
        ->call('answer', 'dineInTakeout', 'either')
        ->call('answer', 'serviceLevel', 'casual_sit_down')
        ->call('answer', 'cuisine', null)
        ->call('answer', 'energy', 'moderate')
        ->call('answer', 'hunger', 'moderate')
        ->call('answer', 'distance', 'anywhere')
        ->call('answer', 'familiarity', 'either')
        ->call('going');

    $this->assertDatabaseHas('household_state', [
        'user_id' => $this->alice->id,
        'last_picker_id' => $this->alice->id,
    ]);
});
