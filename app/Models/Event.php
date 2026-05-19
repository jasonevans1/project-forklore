<?php

namespace App\Models;

use App\Enums\EventRecurrence;
use App\Enums\EventType;
use Carbon\Carbon;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'restaurant_id',
    'owner_user_id',
    'type',
    'recurrence',
    'day_of_week',
    'start_time',
    'end_time',
    'specific_date',
    'title',
    'description',
    'active',
    'shared',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'recurrence' => EventRecurrence::class,
            'specific_date' => 'date',
            'active' => 'boolean',
            'shared' => 'boolean',
            'day_of_week' => 'integer',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Whether this event is currently happening right now.
     */
    public function isActiveNow(): bool
    {
        return $this->occursOn(Carbon::now());
    }

    /**
     * Whether this event is occurring at the given date/time, accounting for
     * recurrence type and midnight-crossing windows.
     *
     * For weekly recurrence:  day_of_week is 0 (Sunday) – 6 (Saturday).
     * For monthly recurrence: day_of_week stores the day-of-month (1 – 31).
     * For one_off recurrence: specific_date holds the exact date.
     *
     * A midnight-crossing window is detected when end_time < start_time.
     * In that case the window spans two calendar days:
     *   - Evening portion: current day matches day_of_week AND time >= start_time
     *   - Morning portion: previous day matches day_of_week AND time <= end_time
     */
    public function occursOn(Carbon $dateTime): bool
    {
        if (! $this->active) {
            return false;
        }

        $currentTime = $dateTime->format('H:i:s');
        $crossesMidnight = $this->end_time < $this->start_time;

        if ($crossesMidnight) {
            return $this->occursOnMidnightCrossing($dateTime, $currentTime);
        }

        return $this->dayMatches($dateTime) && $this->timeInWindow($currentTime);
    }

    /**
     * Evaluate occurrence for an event whose window crosses midnight.
     *
     * The event spans from start_time on the anchor day to end_time on the
     * following day. We check both the evening portion (current day = anchor
     * day, time >= start) and the morning portion (yesterday = anchor day,
     * time <= end).
     */
    private function occursOnMidnightCrossing(Carbon $dateTime, string $currentTime): bool
    {
        // Evening portion: current day is the anchor day and we're past start.
        if ($this->dayMatches($dateTime) && $currentTime >= $this->start_time) {
            return true;
        }

        // Morning portion: yesterday was the anchor day and we're before end.
        $yesterday = $dateTime->copy()->subDay();

        if ($this->dayMatches($yesterday) && $currentTime <= $this->end_time) {
            return true;
        }

        return false;
    }

    /**
     * Check whether the given date falls on the day this event is scheduled.
     */
    private function dayMatches(Carbon $dateTime): bool
    {
        return match ($this->recurrence) {
            EventRecurrence::Weekly => $dateTime->dayOfWeek === $this->day_of_week,
            EventRecurrence::Monthly => $dateTime->day === $this->day_of_week,
            EventRecurrence::OneOff => $this->specific_date !== null
                && $dateTime->toDateString() === $this->specific_date->toDateString(),
        };
    }

    /**
     * Check whether the given time string (H:i:s) falls within the
     * non-midnight-crossing window [start_time, end_time] (inclusive).
     */
    private function timeInWindow(string $currentTime): bool
    {
        return $currentTime >= $this->start_time && $currentTime <= $this->end_time;
    }
}
