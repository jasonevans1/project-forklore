<?php

namespace App\Actions;

use App\Enums\EventRecurrence;
use App\Enums\EventType;
use App\Services\JevService;

class ClassifyEventDescription
{
    public function __construct(public JevService $jev) {}

    /**
     * Ask Jev what kind of event a description is and how often it happens.
     * Answers Jev is unsure about (or cannot give) come back as null.
     *
     * @return array{type: ?EventType, recurrence: ?EventRecurrence}
     */
    public function execute(string $text): array
    {
        $answers = $this->jev->ask(['event_description' => $text], $this->questions()) ?? [];

        return [
            'type' => EventType::tryFrom((string) ($answers['type']['value'] ?? '')),
            'recurrence' => EventRecurrence::tryFrom((string) ($answers['recurrence']['value'] ?? '')),
        ];
    }

    /**
     * @return array<string, array{type: string, instructions: string, criteria: array<string, string>}>
     */
    private function questions(): array
    {
        return [
            'type' => [
                'type' => 'choice',
                'instructions' => 'What kind of restaurant or bar event is this?',
                'criteria' => [
                    EventType::Trivia->value => 'Pub quiz or trivia night',
                    EventType::Bingo->value => 'Bingo night',
                    EventType::LiveMusic->value => 'Live band, DJ or open mic',
                    EventType::HappyHour->value => 'Discounted drinks or food during set hours',
                    EventType::Special->value => 'A food or drink special, prix fixe, tasting or holiday menu',
                    EventType::Other->value => 'Anything else',
                ],
            ],
            'recurrence' => [
                'type' => 'choice',
                'instructions' => 'How often does this event happen?',
                'criteria' => [
                    EventRecurrence::Weekly->value => 'Every week on a given day',
                    EventRecurrence::Monthly->value => 'Once a month on a given date or week',
                    EventRecurrence::OneOff->value => 'A single date',
                ],
            ],
        ];
    }
}
