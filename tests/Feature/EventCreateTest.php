<?php

use App\Actions\InterpretEventDescription;
use App\Enums\EventRecurrence;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\Restaurant;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
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
    expect($event->specific_date->toDateString())->toBe('2027-02-14')
        ->and($event->recurrence)->toBe(EventRecurrence::OneOff)
        ->and($event->restaurant_id)->toBe($restaurant->id);
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

/**
 * @param  array<string, mixed>  $overrides
 */
function fillInForMe(array $overrides = []): Testable
{
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    test()->mock(InterpretEventDescription::class)
        ->shouldReceive('execute')
        ->andReturn(array_merge([
            'type' => 'trivia',
            'recurrence' => 'weekly',
            'day_of_week' => 3,
            'specific_date' => null,
            'start_time' => '19:00',
            'end_time' => '21:00',
        ], $overrides));

    return Livewire::actingAs($user)
        ->test('pages::restaurants.events.create', ['restaurant' => $restaurant])
        ->set('eventText', 'Wednesday trivia 7-9pm');
}

it('fills type, recurrence, day and times from the description', function () {
    fillInForMe()
        ->call('fillFromDescription')
        ->assertSet('type', 'trivia')
        ->assertSet('recurrence', 'weekly')
        ->assertSet('day_of_week', 3)
        ->assertSet('start_time', '19:00')
        ->assertSet('end_time', '21:00')
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['dataset']['variant'] ?? null) === 'success',
        );
});

it('does not overwrite fields the user already filled', function () {
    fillInForMe()
        ->set('type', 'bingo')
        ->set('start_time', '18:00')
        ->call('fillFromDescription')
        ->assertSet('type', 'bingo')
        ->assertSet('start_time', '18:00')
        ->assertSet('end_time', '21:00');
});

it('does not copy the day into a recurrence the user chose that differs from the interpreted one', function () {
    fillInForMe()
        ->set('recurrence', 'monthly')
        ->call('fillFromDescription')
        ->assertSet('recurrence', 'monthly')
        ->assertSet('day_of_week', null);
});

it('keeps weekly recurrence when the user already picked a weekday', function () {
    fillInForMe(['recurrence' => 'monthly', 'day_of_week' => 15])
        ->set('day_of_week', 2)
        ->call('fillFromDescription')
        ->assertSet('recurrence', 'weekly')
        ->assertSet('day_of_week', 2);
});

it('sets the title from the first line of the description when the title is empty', function () {
    fillInForMe()
        ->set('eventText', "  Wednesday trivia  \nbring friends")
        ->call('fillFromDescription')
        ->assertSet('title', 'Wednesday trivia');
});

it('copies the description text into the description field when empty', function () {
    fillInForMe()
        ->call('fillFromDescription')
        ->assertSet('description', 'Wednesday trivia 7-9pm');
});

it('shows a warning toast when some fields could not be filled', function () {
    fillInForMe(['start_time' => null])
        ->call('fillFromDescription')
        ->assertSet('start_time', '')
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['dataset']['variant'] ?? null) === 'warning',
        );
});

it('requires description text before filling in', function () {
    fillInForMe()
        ->set('eventText', '')
        ->call('fillFromDescription')
        ->assertHasErrors(['eventText'])
        ->assertHasNoErrors(['title', 'type']);
});

it('saves an event after filling in from the description', function () {
    fillInForMe()
        ->call('fillFromDescription')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('events', [
        'title' => 'Wednesday trivia 7-9pm',
        'type' => 'trivia',
        'day_of_week' => 3,
    ]);
});
