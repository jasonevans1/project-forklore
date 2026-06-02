<?php

use App\Enums\ModeUsed;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Visit;
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
        ->get(route('history'))
        ->assertOk();
});

it('redirects guests to the login page', function () {
    $this->get(route('history'))
        ->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Empty state
// ---------------------------------------------------------------------------

it('shows an empty state message when the user has no visits', function () {
    Livewire::actingAs($this->user)
        ->test('pages::history')
        ->assertSee('No visits');
});

// ---------------------------------------------------------------------------
// Visit display
// ---------------------------------------------------------------------------

it('shows the restaurant name for each visit', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create(['name' => 'Noodle Palace']);
    Visit::factory()->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now(),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::history')
        ->assertSee('Noodle Palace');
});

it('shows the visited_at date for each visit', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();
    Visit::factory()->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now()->setDate(2025, 3, 15),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::history')
        ->assertSee('Mar');
});

it('shows the mode_used label for each visit', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();
    Visit::factory()->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now(),
        'mode_used' => ModeUsed::QuickPick,
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::history')
        ->assertSee('Quick Pick');
});

// ---------------------------------------------------------------------------
// Grouping by month
// ---------------------------------------------------------------------------

it('groups visits under a month heading', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();
    Visit::factory()->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now()->setDate(2025, 6, 10),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::history')
        ->assertSee('June 2025');
});

it('shows visits from two different months under separate headings', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    Visit::factory()->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now()->setDate(2025, 5, 1),
    ]);
    Visit::factory()->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now()->setDate(2025, 6, 1),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::history')
        ->assertSee('May 2025')
        ->assertSee('June 2025');
});

it('shows most recent visits first', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    Visit::factory()->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now()->subDays(30),
    ]);
    $recent = Visit::factory()->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now(),
    ]);

    $component = Livewire::actingAs($this->user)->test('pages::history');
    $groups = $component->get('visitsByMonth');

    // First group should contain the most recent visit.
    $firstGroup = collect($groups)->first();
    expect(collect($firstGroup)->first()->id)->toBe($recent->id);
});

// ---------------------------------------------------------------------------
// Data isolation — only shows the current user's visits
// ---------------------------------------------------------------------------

it('does not show visits belonging to another user', function () {
    $other = User::factory()->create();
    $restaurant = Restaurant::factory()->for($other, 'user')->create(['name' => 'Secret Spot']);
    Visit::factory()->create([
        'user_id' => $other->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now(),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::history')
        ->assertDontSee('Secret Spot');
});

// ---------------------------------------------------------------------------
// Pagination / limit
// ---------------------------------------------------------------------------

it('loads the 50 most recent visits', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    Visit::factory()->count(60)->create([
        'user_id' => $this->user->id,
        'restaurant_id' => $restaurant->id,
        'visited_at' => now(),
    ]);

    $component = Livewire::actingAs($this->user)->test('pages::history');
    $total = collect($component->get('visitsByMonth'))->flatten()->count();

    expect($total)->toBe(50);
});
