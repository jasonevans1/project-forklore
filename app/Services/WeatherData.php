<?php

namespace App\Services;

use Carbon\CarbonImmutable;

readonly class WeatherData
{
    public function __construct(
        public float $temperature,
        public string $conditions,
        public float $precipitation,
        public float $windSpeed,
        public CarbonImmutable $sunset,
        public string $units,
    ) {}
}
