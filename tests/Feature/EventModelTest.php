<?php

use App\Enums\EventRecurrence;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\Restaurant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Schema
// ---------------------------------------------------------------------------

it('creates the events table with all required columns', function () {
    $columns = Schema::getColumnListing('events');

    expect($columns)
        ->toContain('id')
        ->toContain('restaurant_id')
        ->toContain('type')
        ->toContain('recurrence')
        ->toContain('day_of_week')
        ->toContain('start_time')
        ->toContain('end_time')
        ->toContain('specific_date')
        ->toContain('title')
        ->toContain('description')
        ->toContain('active')
        ->toContain('owner_user_id')
        ->toContain('shared')
        ->toContain('created_at')
        ->toContain('updated_at');
});

it('defaults active to true', function () {
    $restaurant = Restaurant::factory()->create();
    $user = User::factory()->create();

    $event = Event::create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'type' => EventType::Trivia,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => 3,
        'start_time' => '19:00:00',
        'end_time' => '21:00:00',
        'title' => 'Wednesday Trivia',
    ]);

    expect($event->fresh()->active)->toBeTrue();
});

it('defaults shared to false', function () {
    $restaurant = Restaurant::factory()->create();
    $user = User::factory()->create();

    $event = Event::create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'type' => EventType::Trivia,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => 3,
        'start_time' => '19:00:00',
        'end_time' => '21:00:00',
        'title' => 'Wednesday Trivia',
    ]);

    expect($event->fresh()->shared)->toBeFalse();
});

it('allows day_of_week to be null', function () {
    $restaurant = Restaurant::factory()->create();
    $user = User::factory()->create();

    $event = Event::create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'type' => EventType::Special,
        'recurrence' => EventRecurrence::OneOff,
        'day_of_week' => null,
        'specific_date' => '2026-12-25',
        'start_time' => '17:00:00',
        'end_time' => '22:00:00',
        'title' => 'Christmas Special',
    ]);

    expect($event->fresh()->day_of_week)->toBeNull();
});

it('allows specific_date to be null', function () {
    $restaurant = Restaurant::factory()->create();
    $user = User::factory()->create();

    $event = Event::create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'type' => EventType::Trivia,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => 3,
        'specific_date' => null,
        'start_time' => '19:00:00',
        'end_time' => '21:00:00',
        'title' => 'Weekly Trivia',
    ]);

    expect($event->fresh()->specific_date)->toBeNull();
});

// ---------------------------------------------------------------------------
// Enums
// ---------------------------------------------------------------------------

it('casts type to the EventType enum', function () {
    $restaurant = Restaurant::factory()->create();
    $user = User::factory()->create();

    $event = Event::create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'type' => EventType::LiveMusic,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => 5,
        'start_time' => '20:00:00',
        'end_time' => '23:00:00',
        'title' => 'Friday Live Music',
    ]);

    expect($event->fresh()->type)->toBeInstanceOf(EventType::class)
        ->and($event->fresh()->type)->toBe(EventType::LiveMusic);
});

it('casts recurrence to the EventRecurrence enum', function () {
    $restaurant = Restaurant::factory()->create();
    $user = User::factory()->create();

    $event = Event::create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'type' => EventType::HappyHour,
        'recurrence' => EventRecurrence::Monthly,
        'day_of_week' => 15,
        'start_time' => '16:00:00',
        'end_time' => '18:00:00',
        'title' => 'Monthly Happy Hour',
    ]);

    expect($event->fresh()->recurrence)->toBeInstanceOf(EventRecurrence::class)
        ->and($event->fresh()->recurrence)->toBe(EventRecurrence::Monthly);
});

// ---------------------------------------------------------------------------
// Relationships
// ---------------------------------------------------------------------------

it('belongs to a restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $user = User::factory()->create();

    $event = Event::create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'type' => EventType::Trivia,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => 3,
        'start_time' => '19:00:00',
        'end_time' => '21:00:00',
        'title' => 'Wednesday Trivia',
    ]);

    expect($event->restaurant->id)->toBe($restaurant->id);
});

it('belongs to an owner via owner_user_id', function () {
    $restaurant = Restaurant::factory()->create();
    $user = User::factory()->create();

    $event = Event::create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
        'type' => EventType::Trivia,
        'recurrence' => EventRecurrence::Weekly,
        'day_of_week' => 3,
        'start_time' => '19:00:00',
        'end_time' => '21:00:00',
        'title' => 'Wednesday Trivia',
    ]);

    expect($event->owner->id)->toBe($user->id);
});

it('a restaurant has many events', function () {
    $restaurant = Restaurant::factory()->create();
    $user = User::factory()->create();

    Event::factory()->count(3)->create([
        'restaurant_id' => $restaurant->id,
        'owner_user_id' => $user->id,
    ]);

    expect($restaurant->events)->toHaveCount(3);
});

// ---------------------------------------------------------------------------
// occursOn — inactive events
// ---------------------------------------------------------------------------

it('occursOn returns false when the event is inactive regardless of recurrence', function () {
    $event = new Event;
    $event->active = false;
    $event->recurrence = EventRecurrence::Weekly;
    $event->day_of_week = 3; // Wednesday
    $event->start_time = '19:00:00';
    $event->end_time = '21:00:00';

    // Wednesday at 20:00 — would match if active
    $dateTime = Carbon::parse('2026-05-13 20:00:00'); // Wednesday

    expect($event->occursOn($dateTime))->toBeFalse();
});

// ---------------------------------------------------------------------------
// occursOn — weekly recurrence
// ---------------------------------------------------------------------------

it('occursOn returns true for a weekly event on the matching day within the time window', function () {
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Weekly;
    $event->day_of_week = 3; // Wednesday
    $event->start_time = '19:00:00';
    $event->end_time = '21:00:00';

    // Wednesday 2026-05-13 at 20:00
    $dateTime = Carbon::parse('2026-05-13 20:00:00');

    expect($event->occursOn($dateTime))->toBeTrue();
});

it('occursOn returns false for a weekly event on the wrong day of week', function () {
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Weekly;
    $event->day_of_week = 3; // Wednesday
    $event->start_time = '19:00:00';
    $event->end_time = '21:00:00';

    // Thursday 2026-05-14 at 20:00
    $dateTime = Carbon::parse('2026-05-14 20:00:00');

    expect($event->occursOn($dateTime))->toBeFalse();
});

it('occursOn returns false for a weekly event when time is before start_time', function () {
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Weekly;
    $event->day_of_week = 3; // Wednesday
    $event->start_time = '19:00:00';
    $event->end_time = '21:00:00';

    // Wednesday at 18:59
    $dateTime = Carbon::parse('2026-05-13 18:59:00');

    expect($event->occursOn($dateTime))->toBeFalse();
});

it('occursOn returns false for a weekly event when time is after end_time', function () {
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Weekly;
    $event->day_of_week = 3; // Wednesday
    $event->start_time = '19:00:00';
    $event->end_time = '21:00:00';

    // Wednesday at 21:01
    $dateTime = Carbon::parse('2026-05-13 21:01:00');

    expect($event->occursOn($dateTime))->toBeFalse();
});

it('occursOn returns true for a weekly event exactly at start_time', function () {
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Weekly;
    $event->day_of_week = 3; // Wednesday
    $event->start_time = '19:00:00';
    $event->end_time = '21:00:00';

    $dateTime = Carbon::parse('2026-05-13 19:00:00');

    expect($event->occursOn($dateTime))->toBeTrue();
});

it('occursOn returns true for a weekly event exactly at end_time', function () {
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Weekly;
    $event->day_of_week = 3; // Wednesday
    $event->start_time = '19:00:00';
    $event->end_time = '21:00:00';

    $dateTime = Carbon::parse('2026-05-13 21:00:00');

    expect($event->occursOn($dateTime))->toBeTrue();
});

// ---------------------------------------------------------------------------
// occursOn — monthly recurrence
// ---------------------------------------------------------------------------

it('occursOn returns true for a monthly event on the matching day of month within the time window', function () {
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Monthly;
    $event->day_of_week = 13; // 13th of the month
    $event->start_time = '16:00:00';
    $event->end_time = '18:00:00';

    // 13th of May at 17:00
    $dateTime = Carbon::parse('2026-05-13 17:00:00');

    expect($event->occursOn($dateTime))->toBeTrue();
});

it('occursOn returns false for a monthly event on the wrong day of month', function () {
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Monthly;
    $event->day_of_week = 13; // 13th of the month
    $event->start_time = '16:00:00';
    $event->end_time = '18:00:00';

    // 14th of May at 17:00
    $dateTime = Carbon::parse('2026-05-14 17:00:00');

    expect($event->occursOn($dateTime))->toBeFalse();
});

it('occursOn returns false for a monthly event when time is outside the window', function () {
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Monthly;
    $event->day_of_week = 13; // 13th of the month
    $event->start_time = '16:00:00';
    $event->end_time = '18:00:00';

    // 13th of May but at 19:00 — after end
    $dateTime = Carbon::parse('2026-05-13 19:00:00');

    expect($event->occursOn($dateTime))->toBeFalse();
});

// ---------------------------------------------------------------------------
// occursOn — one_off recurrence
// ---------------------------------------------------------------------------

it('occursOn returns true for a one_off event on the matching specific_date within the time window', function () {
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::OneOff;
    $event->specific_date = Carbon::parse('2026-12-25');
    $event->start_time = '17:00:00';
    $event->end_time = '22:00:00';

    $dateTime = Carbon::parse('2026-12-25 19:00:00');

    expect($event->occursOn($dateTime))->toBeTrue();
});

it('occursOn returns false for a one_off event on a different date', function () {
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::OneOff;
    $event->specific_date = Carbon::parse('2026-12-25');
    $event->start_time = '17:00:00';
    $event->end_time = '22:00:00';

    $dateTime = Carbon::parse('2026-12-26 19:00:00');

    expect($event->occursOn($dateTime))->toBeFalse();
});

it('occursOn returns false for a one_off event when time is outside the window', function () {
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::OneOff;
    $event->specific_date = Carbon::parse('2026-12-25');
    $event->start_time = '17:00:00';
    $event->end_time = '22:00:00';

    // Correct date, but too early
    $dateTime = Carbon::parse('2026-12-25 15:00:00');

    expect($event->occursOn($dateTime))->toBeFalse();
});

// ---------------------------------------------------------------------------
// occursOn — midnight-crossing events
// ---------------------------------------------------------------------------

it('occursOn returns true in the evening portion of a midnight-crossing weekly event', function () {
    // Wednesday night bar crawl: 22:00 Wednesday → 02:00 Thursday
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Weekly;
    $event->day_of_week = 3; // Wednesday
    $event->start_time = '22:00:00';
    $event->end_time = '02:00:00';

    // Wednesday at 23:30 — after start, before midnight
    $dateTime = Carbon::parse('2026-05-13 23:30:00');

    expect($event->occursOn($dateTime))->toBeTrue();
});

it('occursOn returns true in the early-morning portion of a midnight-crossing weekly event', function () {
    // Wednesday night bar crawl: 22:00 Wednesday → 02:00 Thursday
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Weekly;
    $event->day_of_week = 3; // Wednesday
    $event->start_time = '22:00:00';
    $event->end_time = '02:00:00';

    // Thursday at 01:00 — event started Wednesday night, still running
    $dateTime = Carbon::parse('2026-05-14 01:00:00');

    expect($event->occursOn($dateTime))->toBeTrue();
});

it('occursOn returns false in the daytime gap of a midnight-crossing weekly event', function () {
    // Wednesday night bar crawl: 22:00 Wednesday → 02:00 Thursday
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Weekly;
    $event->day_of_week = 3; // Wednesday
    $event->start_time = '22:00:00';
    $event->end_time = '02:00:00';

    // Wednesday at 14:00 — well outside the window
    $dateTime = Carbon::parse('2026-05-13 14:00:00');

    expect($event->occursOn($dateTime))->toBeFalse();
});

it('occursOn returns false after end_time on the next day of a midnight-crossing weekly event', function () {
    // Wednesday night bar crawl: 22:00 Wednesday → 02:00 Thursday
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Weekly;
    $event->day_of_week = 3; // Wednesday
    $event->start_time = '22:00:00';
    $event->end_time = '02:00:00';

    // Thursday at 03:00 — past the end time
    $dateTime = Carbon::parse('2026-05-14 03:00:00');

    expect($event->occursOn($dateTime))->toBeFalse();
});

it('occursOn returns false on the wrong day for a midnight-crossing weekly event', function () {
    // Wednesday night: 22:00 → 02:00
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Weekly;
    $event->day_of_week = 3; // Wednesday
    $event->start_time = '22:00:00';
    $event->end_time = '02:00:00';

    // Tuesday at 23:00 — wrong week day
    $dateTime = Carbon::parse('2026-05-12 23:00:00');

    expect($event->occursOn($dateTime))->toBeFalse();
});

// ---------------------------------------------------------------------------
// isActiveNow
// ---------------------------------------------------------------------------

it('isActiveNow returns true when the event occurs at the current time', function () {
    $now = Carbon::now();

    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Weekly;
    $event->day_of_week = $now->dayOfWeek;
    $event->start_time = $now->copy()->subHour()->format('H:i:s');
    $event->end_time = $now->copy()->addHour()->format('H:i:s');

    expect($event->isActiveNow())->toBeTrue();
});

it('isActiveNow returns false when the event is inactive', function () {
    $now = Carbon::now();

    $event = new Event;
    $event->active = false;
    $event->recurrence = EventRecurrence::Weekly;
    $event->day_of_week = $now->dayOfWeek;
    $event->start_time = $now->copy()->subHour()->format('H:i:s');
    $event->end_time = $now->copy()->addHour()->format('H:i:s');

    expect($event->isActiveNow())->toBeFalse();
});

it('isActiveNow returns false when the current time is outside the event window', function () {
    $event = new Event;
    $event->active = true;
    $event->recurrence = EventRecurrence::Weekly;
    $event->day_of_week = Carbon::now()->addDay()->dayOfWeek; // tomorrow's day
    $event->start_time = '02:00:00';
    $event->end_time = '03:00:00';

    expect($event->isActiveNow())->toBeFalse();
});
