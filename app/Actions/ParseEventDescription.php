<?php

namespace App\Actions;

use Illuminate\Support\Carbon;

class ParseEventDescription
{
    private const WEEKDAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    private const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

    private const TIME_TOKEN = '(?:noon|midnight|\d{1,2}(?::\d{2})?\s*(?:am|pm)?)';

    /**
     * Pull the schedule out of a free-text event description.
     *
     * @return array{day_of_week: ?int, day_of_month: ?int, specific_date: ?string, start_time: ?string, end_time: ?string}
     */
    public function execute(string $text): array
    {
        [$start, $end] = $this->parseTimes($text);

        return [
            'day_of_week' => $this->parseDayOfWeek($text),
            'day_of_month' => $this->parseDayOfMonth($text),
            'specific_date' => $this->parseSpecificDate($text),
            'start_time' => $start,
            'end_time' => $end,
        ];
    }

    private function parseDayOfWeek(string $text): ?int
    {
        $pattern = '/\b(sun(?:day)?|mon(?:day)?|tue(?:s|sday)?|wed(?:nesday)?|thu(?:rs?|rsday)?|fri(?:day)?|sat(?:urday)?)s?\b/iu';

        if (! preg_match($pattern, $text, $m)) {
            return null;
        }

        return (int) array_search(strtolower(substr($m[1], 0, 3)), self::WEEKDAYS, true);
    }

    private function parseDayOfMonth(string $text): ?int
    {
        $ordinal = '(\d{1,2})(?:st|nd|rd|th)';

        if (preg_match("/\\bthe\\s+{$ordinal}\\b/iu", $text, $m)
            || preg_match("/\\b{$ordinal}\\s+of\\s+(?:every|each)\\s+month\\b/iu", $text, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function parseSpecificDate(string $text): ?string
    {
        $months = implode('|', array_map(
            fn (string $abbr): string => $abbr === 'may' ? 'may' : $abbr.'[a-z]*',
            self::MONTHS,
        ));

        if (preg_match("/\\b({$months})\\.?\\s+(\\d{1,2})(?:st|nd|rd|th)?(?![:\\d])\\b/iu", $text, $m)) {
            $month = array_search(strtolower(substr($m[1], 0, 3)), self::MONTHS, true) + 1;

            return $this->nextOccurrence($month, (int) $m[2]);
        }

        if (preg_match('#(?<![\d/])(\d{1,2})/(\d{1,2})(?![\d/])(?!\s*(?:price|off)\b)#iu', $text, $m)) {
            return $this->nextOccurrence((int) $m[1], (int) $m[2]);
        }

        return null;
    }

    private function nextOccurrence(int $month, int $day): ?string
    {
        $today = Carbon::today();

        for ($year = $today->year; $year <= $today->year + 4; $year++) {
            if (checkdate($month, $day, $year) && Carbon::create($year, $month, $day)->gte($today)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseTimes(string $text): array
    {
        $token = self::TIME_TOKEN;
        $guard = '(?<![\d/:.])';

        if (preg_match("#{$guard}({$token})\\s*(?:-|–|to)\\s*({$token})(?![\\d/:])#iu", $text, $m)) {
            return $this->resolveRange($this->tokenize($m[1]), $this->tokenize($m[2]));
        }

        if (preg_match("#{$guard}(noon|midnight|\\d{1,2}:\\d{2}\\s*(?:am|pm)?|\\d{1,2}\\s*(?:am|pm))\\b#iu", $text, $m)) {
            $time = $this->tokenize($m[1]);

            return [$this->toTime($time['hour'], $time['minute'], $time['meridiem'] ?? '24h'), null];
        }

        return [null, null];
    }

    /**
     * Break a time token into hour, minute and meridiem (am, pm, 24h or null when bare).
     *
     * @return array{hour: int, minute: int, meridiem: ?string}
     */
    private function tokenize(string $token): array
    {
        $token = strtolower(trim($token));

        if ($token === 'noon') {
            return ['hour' => 12, 'minute' => 0, 'meridiem' => 'pm'];
        }

        if ($token === 'midnight') {
            return ['hour' => 12, 'minute' => 0, 'meridiem' => 'am'];
        }

        preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(am|pm)?$/', $token, $m);
        $m += [1 => '0'];

        return [
            'hour' => (int) $m[1],
            'minute' => (int) ($m[2] ?? 0),
            'meridiem' => $m[3] ?? (str_contains($token, ':') ? '24h' : null),
        ];
    }

    /**
     * Resolve a start/end pair into 24-hour times. A 12-hour-looking start with
     * minutes (`5:30-10pm`) inherits the end's meridiem like a bare one.
     *
     * @param  array{hour: int, minute: int, meridiem: ?string}  $start
     * @param  array{hour: int, minute: int, meridiem: ?string}  $end
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveRange(array $start, array $end): array
    {
        $startLacksMeridiem = $start['meridiem'] === null
            || ($start['meridiem'] === '24h' && $start['hour'] >= 1 && $start['hour'] <= 12);
        $inherits = $startLacksMeridiem && in_array($end['meridiem'], ['am', 'pm'], true);

        if ($inherits) {
            $start['meridiem'] = null;
        }

        $end['meridiem'] ??= $this->restaurantMeridiem($end['hour']);
        $start['meridiem'] ??= $inherits ? $end['meridiem'] : $this->restaurantMeridiem($start['hour']);

        $startTime = $this->toTime($start['hour'], $start['minute'], $start['meridiem']);
        $endTime = $this->toTime($end['hour'], $end['minute'], $end['meridiem']);

        if ($startTime === null || $endTime === null) {
            return [null, null];
        }

        if ($inherits && $startTime > $endTime) {
            $flipped = $start['meridiem'] === 'am' ? 'pm' : 'am';
            $startTime = $this->toTime($start['hour'], $start['minute'], $flipped);
        }

        return [$startTime, $endTime];
    }

    private function restaurantMeridiem(int $hour): string
    {
        return $hour === 11 ? 'am' : 'pm';
    }

    private function toTime(int $hour, int $minute, string $meridiem): ?string
    {
        if ($meridiem !== '24h') {
            if ($hour < 1 || $hour > 12) {
                return null;
            }
            $hour = $hour % 12 + ($meridiem === 'pm' ? 12 : 0);
        }

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }
}
