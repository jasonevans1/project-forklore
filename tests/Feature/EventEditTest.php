<?php

use App\Enums\EventRecurrence;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\Restaurant;
use App\Models\User;
use Livewire\Livewire;

it('pre-populates title from the existing event', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    $event = Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'title' => 'Thursday Trivia',
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.edit', ['restaurant' => $restaurant, 'event' => $event])
        ->assertSet('title', 'Thursday Trivia');
});

it('pre-populates type from the existing event', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    $event = Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'type' => EventType::Bingo,
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.edit', ['restaurant' => $restaurant, 'event' => $event])
        ->assertSet('type', EventType::Bingo->value);
});

it('pre-populates recurrence from the existing event', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    $event = Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'recurrence' => EventRecurrence::Monthly,
        'day_of_week' => 15,
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.edit', ['restaurant' => $restaurant, 'event' => $event])
        ->assertSet('recurrence', EventRecurrence::Monthly->value);
});

it('pre-populates day_of_week for a weekly event', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    $event = Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => 2,
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.edit', ['restaurant' => $restaurant, 'event' => $event])
        ->assertSet('day_of_week', 2);
});

it('pre-populates specific_date for a one-off event', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    $event = Event::factory()->oneOff('2027-07-04')->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.edit', ['restaurant' => $restaurant, 'event' => $event])
        ->assertSet('specific_date', '2027-07-04');
});

it('pre-populates start_time and end_time', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    $event = Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'start_time' => '19:00:00',
        'end_time' => '21:00:00',
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.edit', ['restaurant' => $restaurant, 'event' => $event])
        ->assertSet('start_time', '19:00')
        ->assertSet('end_time', '21:00');
});

it('pre-populates the active flag', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    $event = Event::factory()->inactive()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.edit', ['restaurant' => $restaurant, 'event' => $event])
        ->assertSet('active', false);
});

it('saves updated fields and redirects to the events index', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    $event = Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'title' => 'Old Title',
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.edit', ['restaurant' => $restaurant, 'event' => $event])
        ->set('title', 'New Title')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('restaurants.events.index', $restaurant));

    expect($event->fresh()->title)->toBe('New Title');
});

it('shows validation errors without redirecting when title is cleared', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    $event = Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.edit', ['restaurant' => $restaurant, 'event' => $event])
        ->set('title', '')
        ->call('save')
        ->assertHasErrors(['title'])
        ->assertNoRedirect();
});

it('persists an active toggle change', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    $event = Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'active' => true,
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.edit', ['restaurant' => $restaurant, 'event' => $event])
        ->set('active', false)
        ->call('save');

    expect($event->fresh()->active)->toBeFalse();
});

it('deletes the event and redirects to the events index', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);
    $event = Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.edit', ['restaurant' => $restaurant, 'event' => $event])
        ->call('delete')
        ->assertRedirect(route('restaurants.events.index', $restaurant));

    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});

it('forbids a non-owner from loading the edit page', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
    $event = Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $owner->id,
    ]);

    $this->actingAs($other)
        ->get(route('restaurants.events.edit', [$restaurant, $event]))
        ->assertForbidden();
});

it('forbids a non-owner from invoking delete via the edit component', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
    $event = Event::factory()->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $owner->id,
    ]);

    // Mount as owner, then transfer ownership mid-session
    $component = Livewire::actingAs($owner)
        ->test('pages::restaurants.events.edit', ['restaurant' => $restaurant, 'event' => $event]);

    $restaurant->update(['owner_user_id' => $attacker->id]);

    $component->call('delete')->assertForbidden();

    $this->assertDatabaseHas('events', ['id' => $event->id]);
});
