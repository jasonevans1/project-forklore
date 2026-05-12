<?php

namespace App\Services;

/**
 * Optional filters for the QuickPickService.
 *
 * @property-read int|null   $time_window        Maximum visit duration in minutes.
 * @property-read int|null   $budget_max         Maximum price_level (1–4).
 * @property-read float|null $max_distance_miles Maximum distance from the reference point.
 * @property-read float|null $lat                Reference latitude for distance filtering.
 * @property-read float|null $lng                Reference longitude for distance filtering.
 */
readonly class QuickPickFilters
{
    public function __construct(
        public ?int $time_window = null,
        public ?int $budget_max = null,
        public ?float $max_distance_miles = null,
        public ?float $lat = null,
        public ?float $lng = null,
    ) {}
}
