<?php

use App\Enums\EventRecurrence;
use App\Models\Event;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\TonightService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->service = new TonightService;
    $this->now = Carbon::now();
});

// ---------------------------------------------------------------------------
// Empty-pool handling
// ---------------------------------------------------------------------------

it('returns null when the user has no restaurants', function () {
    expect($this->service->pick($this->user))->toBeNull();
});

it('returns null when the user has restaurants but no events', function () {
    Restaurant::factory()->for($this->user, 'user')->create();

    expect($this->service->pick($this->user))->toBeNull();
});

it('returns null when there are no events in the next 3 hours', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    // Event starts 4 hours from now — outside the 3-hour window
    $start = $this->now->copy()->addHours(4);

    Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $this->user->id,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => $this->now->dayOfWeek,
        'start_time' => $start->format('H:i:s'),
        'end_time' => $start->copy()->addHours(2)->format('H:i:s'),
        'active' => true,
    ]);

    expect($this->service->pick($this->user))->toBeNull();
});

// ---------------------------------------------------------------------------
// Time-window matching
// ---------------------------------------------------------------------------

it('returns a restaurant when its event starts within the next 3 hours', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $start = $this->now->copy()->addHour();

    Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $this->user->id,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => $this->now->dayOfWeek,
        'start_time' => $start->format('H:i:s'),
        'end_time' => $start->copy()->addHours(2)->format('H:i:s'),
        'active' => true,
    ]);

    expect($this->service->pick($this->user))->not->toBeNull();
});

it('returns a restaurant when an event starts exactly at the 3-hour boundary', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $start = $this->now->copy()->addHours(3);

    Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $this->user->id,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => $this->now->dayOfWeek,
        'start_time' => $start->format('H:i:s'),
        'end_time' => $start->copy()->addHours(2)->format('H:i:s'),
        'active' => true,
    ]);

    expect($this->service->pick($this->user))->not->toBeNull();
});

it('returns a restaurant when an event is currently in progress', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    // Event started 1 hour ago, ends 1 hour from now
    $start = $this->now->copy()->subHour();
    $end = $this->now->copy()->addHour();

    Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $this->user->id,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => $this->now->dayOfWeek,
        'start_time' => $start->format('H:i:s'),
        'end_time' => $end->format('H:i:s'),
        'active' => true,
    ]);

    expect($this->service->pick($this->user))->not->toBeNull();
});

it('returns null when the event is on a different day of the week', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $start = $this->now->copy()->addHour();
    $wrongDay = ($this->now->dayOfWeek + 1) % 7;

    Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $this->user->id,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => $wrongDay,
        'start_time' => $start->format('H:i:s'),
        'end_time' => $start->copy()->addHours(2)->format('H:i:s'),
        'active' => true,
    ]);

    expect($this->service->pick($this->user))->toBeNull();
});

// ---------------------------------------------------------------------------
// One-off events
// ---------------------------------------------------------------------------

it('returns a restaurant for a one-off event on today with a start time in the next 3 hours', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $start = $this->now->copy()->addHour();

    Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $this->user->id,
        'recurrence' => EventRecurrence::OneOff,
        'day_of_week' => null,
        'specific_date' => $this->now->toDateString(),
        'start_time' => $start->format('H:i:s'),
        'end_time' => $start->copy()->addHours(2)->format('H:i:s'),
        'active' => true,
    ]);

    expect($this->service->pick($this->user))->not->toBeNull();
});

it('returns null for a one-off event scheduled on a different date', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $start = $this->now->copy()->addHour();

    Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $this->user->id,
        'recurrence' => EventRecurrence::OneOff,
        'day_of_week' => null,
        'specific_date' => $this->now->copy()->addDay()->toDateString(),
        'start_time' => $start->format('H:i:s'),
        'end_time' => $start->copy()->addHours(2)->format('H:i:s'),
        'active' => true,
    ]);

    expect($this->service->pick($this->user))->toBeNull();
});

// ---------------------------------------------------------------------------
// Ownership and active state
// ---------------------------------------------------------------------------

it('returns null when the event belongs to another user', function () {
    $otherUser = User::factory()->create();
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $start = $this->now->copy()->addHour();

    Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $otherUser->id,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => $this->now->dayOfWeek,
        'start_time' => $start->format('H:i:s'),
        'end_time' => $start->copy()->addHours(2)->format('H:i:s'),
        'active' => true,
    ]);

    expect($this->service->pick($this->user))->toBeNull();
});

it('returns null when the event is inactive', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $start = $this->now->copy()->addHour();

    Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $this->user->id,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => $this->now->dayOfWeek,
        'start_time' => $start->format('H:i:s'),
        'end_time' => $start->copy()->addHours(2)->format('H:i:s'),
        'active' => false,
    ]);

    expect($this->service->pick($this->user))->toBeNull();
});

// ---------------------------------------------------------------------------
// excludedIds
// ---------------------------------------------------------------------------

it('skips restaurants whose ids are in the excluded list', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $start = $this->now->copy()->addHour();

    Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $this->user->id,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => $this->now->dayOfWeek,
        'start_time' => $start->format('H:i:s'),
        'end_time' => $start->copy()->addHours(2)->format('H:i:s'),
        'active' => true,
    ]);

    expect($this->service->pick($this->user, excludedIds: [$restaurant->id]))->toBeNull();
});

// ---------------------------------------------------------------------------
// Next-event label
// ---------------------------------------------------------------------------

it('resolves an event label in "Trivia starts at 7pm" format', function () {
    $fivepm = Carbon::today()->setHour(17); // 5pm — event at 7pm is 2 hours away

    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $this->user->id,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => $fivepm->dayOfWeek,
        'start_time' => '19:00:00',
        'end_time' => '21:00:00',
        'title' => 'Trivia Night',
        'active' => true,
    ]);

    $label = $this->service->eventLabel($restaurant->fresh()->load('events'), $fivepm);

    expect($label)->toContain('7pm');
});

it('returns the correct restaurant id from pick', function () {
    $restaurant = Restaurant::factory()->for($this->user, 'user')->create();

    $start = $this->now->copy()->addHour();

    Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $this->user->id,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => $this->now->dayOfWeek,
        'start_time' => $start->format('H:i:s'),
        'end_time' => $start->copy()->addHours(2)->format('H:i:s'),
        'active' => true,
    ]);

    $result = $this->service->pick($this->user);

    expect($result->id)->toBe($restaurant->id);
});
