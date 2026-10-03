<?php

use App\Actions\ParseEventDescription;

/**
 * @return array{day_of_week: ?int, day_of_month: ?int, specific_date: ?string, start_time: ?string, end_time: ?string}
 */
function parseEvent(string $text): array
{
    return app(ParseEventDescription::class)->execute($text);
}

beforeEach(fn () => $this->travelTo('2026-10-02 12:00:00'));

it('parses a weekday name or plural into day_of_week with Sunday as 0', function (string $text, int $day) {
    expect(parseEvent($text)['day_of_week'])->toBe($day);
})->with([
    ['Wednesday trivia', 3],
    ['Live music on Wednesdays', 3],
    ['wed night', 3],
    ['SUNDAY brunch', 0],
    ['Saturdays', 6],
    ['Thurs wings', 4],
    ['Tue tacos', 2],
]);

it('parses a time range with a shared meridiem into start and end times', function (string $text, string $start, string $end) {
    expect(parseEvent($text))->toMatchArray(['start_time' => $start, 'end_time' => $end]);
})->with([
    ['7-9pm', '19:00', '21:00'],
    ['7–9 pm', '19:00', '21:00'],
    ['7pm-9pm', '19:00', '21:00'],
    ['7:30pm to 10pm', '19:30', '22:00'],
]);

it('parses a single start time and leaves the end time null', function (string $text, string $start) {
    expect(parseEvent($text))->toMatchArray(['start_time' => $start, 'end_time' => null]);
})->with([
    ['trivia at 7pm', '19:00'],
    ['starts 7:30 pm', '19:30'],
]);

it('parses 24-hour time ranges and ranges that cross midnight', function (string $text, string $start, ?string $end) {
    expect(parseEvent($text))->toMatchArray(['start_time' => $start, 'end_time' => $end]);
})->with([
    ['19:00-21:00', '19:00', '21:00'],
    ['9pm-1am', '21:00', '01:00'],
    ['at 7:30', '07:30', null],
]);

it('parses an ordinal day of the month', function (string $text) {
    expect(parseEvent($text)['day_of_month'])->toBe(15);
})->with(['on the 15th', 'the 15th of every month', '15th of each month']);

it('parses a month and day into the next upcoming date', function (string $text, string $date) {
    expect(parseEvent($text)['specific_date'])->toBe($date);
})->with([
    ['Feb 14', '2027-02-14'],
    ['February 14th', '2027-02-14'],
    ['2/14', '2027-02-14'],
    ['Dec 25', '2026-12-25'],
    ['Oct 2', '2026-10-02'],
    ['Oct 1', '2027-10-01'],
]);

it('returns all nulls for text with no schedule information', function () {
    expect(parseEvent('Great pizza and friendly staff'))->toBe([
        'day_of_week' => null,
        'day_of_month' => null,
        'specific_date' => null,
        'start_time' => null,
        'end_time' => null,
    ]);
});

it('converts 12am, 12pm, noon and midnight correctly', function (string $text, string $start, ?string $end) {
    expect(parseEvent($text))->toMatchArray(['start_time' => $start, 'end_time' => $end]);
})->with([
    ['12pm', '12:00', null],
    ['12am', '00:00', null],
    ['noon', '12:00', null],
    ['noon-3pm', '12:00', '15:00'],
    ['9pm-midnight', '21:00', '00:00'],
    ['midnight', '00:00', null],
]);

it('flips an inherited meridiem when the start would be after the end', function (string $text, string $start, string $end) {
    expect(parseEvent($text))->toMatchArray(['start_time' => $start, 'end_time' => $end]);
})->with([
    ['11-2pm', '11:00', '14:00'],
    ['9-1am', '21:00', '01:00'],
]);

it('lets a range start with minutes inherit the end meridiem', function (string $text, string $start, string $end) {
    expect(parseEvent($text))->toMatchArray(['start_time' => $start, 'end_time' => $end]);
})->with([
    ['prix fixe 5:30-10pm', '17:30', '22:00'],
    ['brunch 10:30-2pm', '10:30', '14:00'],
    ['late night 19:00-21:00', '19:00', '21:00'],
]);

it('assumes pm for bare range hours 1 to 10 and am for 11', function (string $text, string $start, string $end) {
    expect(parseEvent($text))->toMatchArray(['start_time' => $start, 'end_time' => $end]);
})->with([
    ['happy hour 4-6', '16:00', '18:00'],
    ['7-9', '19:00', '21:00'],
    ['11-2', '11:00', '14:00'],
]);

it('leaves times null for a single bare number without a meridiem or colon', function () {
    expect(parseEvent('trivia at 7'))->toMatchArray(['start_time' => null, 'end_time' => null]);
});

it('does not treat the ordinal in a month date as a day of month', function () {
    expect(parseEvent('February 14th'))
        ->toMatchArray(['day_of_month' => null, 'specific_date' => '2027-02-14']);
});

it('does not treat fractions like 1/2 price as a date', function (string $text) {
    expect(parseEvent($text)['specific_date'])->toBeNull();
})->with(['1/2 price wings', '1/2 off pitchers']);

it('does not match weekday or month abbreviations inside other words', function (string $text) {
    expect(parseEvent($text))->toMatchArray(['day_of_week' => null, 'specific_date' => null]);
})->with(['satisfied customers', 'prices may vary', 'sunny patio', 'march 1 is not' => 'marching band 5']);
