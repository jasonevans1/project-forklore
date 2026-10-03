<?php

namespace App\Actions;

use App\Enums\EventRecurrence;

class InterpretEventDescription
{
    public function __construct(
        public ParseEventDescription $parser,
        public ClassifyEventDescription $classifier,
    ) {}

    /**
     * Turn a free-text description into the Add event form's field values.
     *
     * @return array{type: ?string, recurrence: ?string, day_of_week: ?int, specific_date: ?string, start_time: ?string, end_time: ?string}
     */
    public function execute(string $text): array
    {
        $parsed = $this->parser->execute($text);
        $classified = $this->classifier->execute($text);

        $recurrence = $classified['recurrence'] ?? $this->inferRecurrence($parsed);

        return [
            'type' => $classified['type']?->value,
            'recurrence' => $recurrence?->value,
            'day_of_week' => match ($recurrence) {
                EventRecurrence::Weekly => $parsed['day_of_week'],
                EventRecurrence::Monthly => $parsed['day_of_month'],
                default => null,
            },
            'specific_date' => $recurrence === EventRecurrence::OneOff ? $parsed['specific_date'] : null,
            'start_time' => $parsed['start_time'],
            'end_time' => $parsed['end_time'],
        ];
    }

    /**
     * @param  array{day_of_week: ?int, day_of_month: ?int, specific_date: ?string, start_time: ?string, end_time: ?string}  $parsed
     */
    private function inferRecurrence(array $parsed): ?EventRecurrence
    {
        return match (true) {
            $parsed['specific_date'] !== null => EventRecurrence::OneOff,
            $parsed['day_of_month'] !== null => EventRecurrence::Monthly,
            $parsed['day_of_week'] !== null => EventRecurrence::Weekly,
            default => null,
        };
    }
}
