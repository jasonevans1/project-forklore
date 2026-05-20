<?php

namespace App\Concerns;

use App\Enums\EventRecurrence;
use App\Enums\EventType;
use Illuminate\Validation\Rule;

/**
 * Shared validation logic for the create and edit event Volt pages.
 *
 * Classes using this trait must declare the following properties:
 *   - string $recurrence
 *   - ?int $day_of_week
 *   - string $specific_date
 */
trait ValidatesEventFields
{
    /**
     * Whether the current recurrence requires day_of_week.
     */
    public function needsDayOfWeek(): bool
    {
        return in_array($this->recurrence, [
            EventRecurrence::Weekly->value,
            EventRecurrence::Monthly->value,
        ], strict: true);
    }

    /**
     * Whether the current recurrence requires a specific_date.
     */
    public function needsSpecificDate(): bool
    {
        return $this->recurrence === EventRecurrence::OneOff->value;
    }

    /**
     * Build validation rules, conditionally requiring day_of_week or specific_date.
     *
     * @return array<string, mixed>
     */
    private function eventRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(EventType::class)],
            'recurrence' => ['required', Rule::enum(EventRecurrence::class)],
            'day_of_week' => $this->needsDayOfWeek()
                ? ['required', 'integer', 'min:0', 'max:31']
                : ['nullable', 'integer', 'min:0', 'max:31'],
            'specific_date' => $this->needsSpecificDate()
                ? ['required', 'date']
                : ['nullable', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'description' => ['nullable', 'string', 'max:1000'],
            'active' => ['boolean'],
            'shared' => ['boolean'],
        ];
    }
}
