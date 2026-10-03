<?php

use App\Actions\ClassifyEventDescription;
use App\Actions\InterpretEventDescription;
use App\Enums\EventRecurrence;
use App\Enums\EventType;

/**
 * @return array{type: ?string, recurrence: ?string, day_of_week: ?int, specific_date: ?string, start_time: ?string, end_time: ?string}
 */
function interpretWith(string $text, ?EventType $type = null, ?EventRecurrence $recurrence = null): array
{
    test()->mock(ClassifyEventDescription::class)
        ->shouldReceive('execute')
        ->with($text)
        ->andReturn(['type' => $type, 'recurrence' => $recurrence]);

    return app(InterpretEventDescription::class)->execute($text);
}

it('combines the parsed weekday and times with the Jev type and recurrence', function () {
    $result = interpretWith('Wednesday trivia 7pm-9pm', EventType::Trivia, EventRecurrence::Weekly);

    expect($result)->toBe([
        'type' => 'trivia',
        'recurrence' => 'weekly',
        'day_of_week' => 3,
        'specific_date' => null,
        'start_time' => '19:00',
        'end_time' => '21:00',
    ]);
});

it("uses the Jev recurrence over the parser's inference", function () {
    $result = interpretWith('Every Friday live music', EventType::LiveMusic, EventRecurrence::Monthly);

    expect($result['recurrence'])->toBe('monthly');
});

it('infers one_off, monthly or weekly recurrence from the parser when Jev has no answer', function () {
    expect(interpretWith('Valentine special on Feb 14')['recurrence'])->toBe('one_off')
        ->and(interpretWith('Bingo on the 15th')['recurrence'])->toBe('monthly')
        ->and(interpretWith('Trivia on Wednesday')['recurrence'])->toBe('weekly')
        ->and(interpretWith('Great food')['recurrence'])->toBeNull();
});

it('puts the day of month in day_of_week for monthly events', function () {
    $result = interpretWith('Bingo on the 15th', EventType::Bingo, EventRecurrence::Monthly);

    expect($result['day_of_week'])->toBe(15);
});

it('clears fields that do not apply to the chosen recurrence', function () {
    $monthly = interpretWith('First Friday of the month', null, EventRecurrence::Monthly);
    $weekly = interpretWith('Wednesday trivia Feb 14', null, EventRecurrence::Weekly);
    $oneOff = interpretWith('Wednesday special Feb 14', null, EventRecurrence::OneOff);
    $none = interpretWith('Wednesday Feb 14');

    expect($monthly['day_of_week'])->toBeNull()
        ->and($monthly['specific_date'])->toBeNull()
        ->and($weekly['day_of_week'])->toBe(3)
        ->and($weekly['specific_date'])->toBeNull()
        ->and($oneOff['day_of_week'])->toBeNull()
        ->and($oneOff['specific_date'])->toMatch('/^\d{4}-02-14$/')
        ->and($none['recurrence'])->toBe('one_off');
});

it('still returns parsed times and weekday when Jev is unavailable', function () {
    $result = interpretWith('Thursday open mic 8pm');

    expect($result)->toMatchArray([
        'type' => null,
        'recurrence' => 'weekly',
        'day_of_week' => 4,
        'start_time' => '20:00',
    ]);
});
