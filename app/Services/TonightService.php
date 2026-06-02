<?php

namespace App\Services;

use App\Enums\EventRecurrence;
use App\Models\Event;
use App\Models\Restaurant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class TonightService
{
    /** How many hours ahead to look for upcoming events. */
    private const int WINDOW_HOURS = 3;

    /**
     * Return a single restaurant that has an event owned by the user
     * starting (or currently in progress) within the next 3 hours, or null
     * if none qualify.
     *
     * @param  array<int>  $excludedIds  Restaurant IDs to skip this session.
     */
    public function pick(User $user, array $excludedIds = [], ?Carbon $now = null): ?Restaurant
    {
        $now ??= Carbon::now();
        $cutoff = $now->copy()->addHours(self::WINDOW_HOURS);

        $restaurants = Restaurant::ownedBy($user)
            ->whereNotIn('id', $excludedIds)
            ->with(['events' => function ($query) use ($user): void {
                $query->where('owner_user_id', $user->id)->where('active', true);
            }])
            ->get();

        $qualifying = $restaurants->filter(
            fn (Restaurant $restaurant) => $this->hasQualifyingEvent($restaurant->events, $now, $cutoff)
        );

        return $qualifying->isEmpty() ? null : $qualifying->first();
    }

    /**
     * Build a human-readable event label like "Trivia starts at 7pm" for the
     * next qualifying event on the given restaurant, relative to now.
     */
    public function eventLabel(Restaurant $restaurant, ?Carbon $now = null): string
    {
        $now ??= Carbon::now();
        $cutoff = $now->copy()->addHours(self::WINDOW_HOURS);

        /** @var Event|null $event */
        $event = $restaurant->events
            ->filter(fn (Event $e) => $this->isQualifyingEvent($e, $now, $cutoff))
            ->first();

        if ($event === null) {
            return '';
        }

        $startTime = Carbon::createFromTimeString($event->start_time);
        $formatted = $startTime->format('g:ia');

        // "7:00pm" → "7pm" for clean display
        $formatted = str_replace(':00', '', $formatted);

        $verb = $now->format('H:i:s') >= $event->start_time ? 'started at' : 'starts at';

        return "{$event->title} {$verb} {$formatted}";
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Check whether any event in the collection qualifies (active, today,
     * window overlaps [now, cutoff]).
     *
     * @param  Collection<int, Event>  $events
     */
    private function hasQualifyingEvent(Collection $events, Carbon $now, Carbon $cutoff): bool
    {
        return $events->contains(fn (Event $event) => $this->isQualifyingEvent($event, $now, $cutoff));
    }

    /**
     * An event qualifies when:
     *   - it is active
     *   - it falls on today's date (respecting recurrence)
     *   - its window overlaps [now, cutoff] in at least one second
     *
     * Using Carbon datetime objects for comparisons so the 3-hour window
     * works correctly even when it crosses midnight.
     */
    private function isQualifyingEvent(Event $event, Carbon $now, Carbon $cutoff): bool
    {
        if (! $event->active) {
            return false;
        }

        // Day-match is evaluated against $now's calendar date.
        if (! $this->eventIsOnDate($event, $now)) {
            return false;
        }

        // Anchor start/end to today's date so Carbon comparisons are correct.
        $today = $now->toDateString();
        $eventStart = Carbon::parse("{$today} {$event->start_time}");
        $eventEnd = Carbon::parse("{$today} {$event->end_time}");

        // If end is before start, the event spans midnight — advance end to next day.
        if ($eventEnd->lt($eventStart)) {
            $eventEnd->addDay();
        }

        // Event window overlaps [now, cutoff] when it starts before cutoff
        // AND ends at or after now.
        return $eventStart->lte($cutoff) && $eventEnd->gte($now);
    }

    /**
     * Determine whether the event falls on the same calendar date as $now,
     * using its recurrence type.
     */
    private function eventIsOnDate(Event $event, Carbon $now): bool
    {
        return match ($event->recurrence) {
            EventRecurrence::Weekly => $now->dayOfWeek === $event->day_of_week,
            EventRecurrence::Monthly => $now->day === $event->day_of_week,
            EventRecurrence::OneOff => $event->specific_date !== null
                && $now->toDateString() === $event->specific_date->toDateString(),
        };
    }
}
