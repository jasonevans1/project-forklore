<?php

use App\Enums\EventRecurrence;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\Restaurant;
use App\Models\User;
use Livewire\Livewire;

it('requires a title to create an event', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.create', ['restaurant' => $restaurant])
        ->set('title', '')
        ->set('type', EventType::Trivia->value)
        ->set('recurrence', EventRecurrence::Weekly->value)
        ->set('day_of_week', 2)
        ->set('start_time', '19:00')
        ->set('end_time', '21:00')
        ->call('save')
        ->assertHasErrors(['title']);
});

it('requires a type to create an event', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.create', ['restaurant' => $restaurant])
        ->set('title', 'Trivia Night')
        ->set('type', '')
        ->set('recurrence', EventRecurrence::Weekly->value)
        ->set('day_of_week', 2)
        ->set('start_time', '19:00')
        ->set('end_time', '21:00')
        ->call('save')
        ->assertHasErrors(['type']);
});

it('requires a valid recurrence value', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.create', ['restaurant' => $restaurant])
        ->set('title', 'Trivia Night')
        ->set('type', EventType::Trivia->value)
        ->set('recurrence', 'biannual')
        ->set('day_of_week', 2)
        ->set('start_time', '19:00')
        ->set('end_time', '21:00')
        ->call('save')
        ->assertHasErrors(['recurrence']);
});

it('requires start_time', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.create', ['restaurant' => $restaurant])
        ->set('title', 'Trivia Night')
        ->set('type', EventType::Trivia->value)
        ->set('recurrence', EventRecurrence::Weekly->value)
        ->set('day_of_week', 2)
        ->set('start_time', '')
        ->set('end_time', '21:00')
        ->call('save')
        ->assertHasErrors(['start_time']);
});

it('requires end_time', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.create', ['restaurant' => $restaurant])
        ->set('title', 'Trivia Night')
        ->set('type', EventType::Trivia->value)
        ->set('recurrence', EventRecurrence::Weekly->value)
        ->set('day_of_week', 2)
        ->set('start_time', '19:00')
        ->set('end_time', '')
        ->call('save')
        ->assertHasErrors(['end_time']);
});

it('requires day_of_week when recurrence is weekly', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.create', ['restaurant' => $restaurant])
        ->set('title', 'Trivia Night')
        ->set('type', EventType::Trivia->value)
        ->set('recurrence', EventRecurrence::Weekly->value)
        ->set('day_of_week', null)
        ->set('start_time', '19:00')
        ->set('end_time', '21:00')
        ->call('save')
        ->assertHasErrors(['day_of_week']);
});

it('requires day_of_week when recurrence is monthly', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.create', ['restaurant' => $restaurant])
        ->set('title', 'Monthly Special')
        ->set('type', EventType::Special->value)
        ->set('recurrence', EventRecurrence::Monthly->value)
        ->set('day_of_week', null)
        ->set('start_time', '18:00')
        ->set('end_time', '22:00')
        ->call('save')
        ->assertHasErrors(['day_of_week']);
});

it('requires specific_date when recurrence is one_off', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.create', ['restaurant' => $restaurant])
        ->set('title', 'One Night Only')
        ->set('type', EventType::LiveMusic->value)
        ->set('recurrence', EventRecurrence::OneOff->value)
        ->set('specific_date', '')
        ->set('start_time', '20:00')
        ->set('end_time', '23:00')
        ->call('save')
        ->assertHasErrors(['specific_date']);
});

it('saves a weekly event and redirects to the events index', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.create', ['restaurant' => $restaurant])
        ->set('title', 'Wednesday Trivia')
        ->set('type', EventType::Trivia->value)
        ->set('recurrence', EventRecurrence::Weekly->value)
        ->set('day_of_week', 3)
        ->set('start_time', '19:00')
        ->set('end_time', '21:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('restaurants.events.index', $restaurant));

    $this->assertDatabaseHas('events', [
        'restaurant_id' => $restaurant->id,
        'title' => 'Wednesday Trivia',
        'recurrence' => EventRecurrence::Weekly->value,
        'day_of_week' => 3,
    ]);
});

it('saves a one-off event with a specific date', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.create', ['restaurant' => $restaurant])
        ->set('title', 'Valentine\'s Prix Fixe')
        ->set('type', EventType::Special->value)
        ->set('recurrence', EventRecurrence::OneOff->value)
        ->set('specific_date', '2027-02-14')
        ->set('start_time', '18:00')
        ->set('end_time', '22:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('restaurants.events.index', $restaurant));

    $event = Event::where('title', 'Valentine\'s Prix Fixe')->firstOrFail();
    expect($event->specific_date->toDateString())->toBe('2027-02-14');
    expect($event->recurrence)->toBe(EventRecurrence::OneOff);
    expect($event->restaurant_id)->toBe($restaurant->id);
});

it('sets the owner to the authenticated user on save', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.create', ['restaurant' => $restaurant])
        ->set('title', 'Owner Event')
        ->set('type', EventType::Trivia->value)
        ->set('recurrence', EventRecurrence::Weekly->value)
        ->set('day_of_week', 1)
        ->set('start_time', '19:00')
        ->set('end_time', '21:00')
        ->call('save');

    $this->assertDatabaseHas('events', [
        'title' => 'Owner Event',
        'owner_user_id' => $user->id,
    ]);
});

it('sets the restaurant_id to the route restaurant on save', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.create', ['restaurant' => $restaurant])
        ->set('title', 'Scoped Event')
        ->set('type', EventType::Trivia->value)
        ->set('recurrence', EventRecurrence::Weekly->value)
        ->set('day_of_week', 1)
        ->set('start_time', '19:00')
        ->set('end_time', '21:00')
        ->call('save');

    $this->assertDatabaseHas('events', [
        'title' => 'Scoped Event',
        'restaurant_id' => $restaurant->id,
    ]);
});

it('defaults active to true on a new event', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.events.create', ['restaurant' => $restaurant])
        ->set('title', 'Active By Default')
        ->set('type', EventType::Bingo->value)
        ->set('recurrence', EventRecurrence::Weekly->value)
        ->set('day_of_week', 4)
        ->set('start_time', '18:00')
        ->set('end_time', '20:00')
        ->call('save');

    $this->assertDatabaseHas('events', [
        'title' => 'Active By Default',
        'active' => true,
    ]);
});

it('forbids a non-owner from creating an event for another user\'s restaurant', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs($other)
        ->get(route('restaurants.events.create', $restaurant))
        ->assertForbidden();
});
