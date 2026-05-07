<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeatherService
{
    private const QUOTA_LIMIT = 1000;

    private const CACHE_TTL = 3600;

    private const QUOTA_TTL = 172800;

    public function fetch(float $lat, float $lng): ?WeatherData
    {
        $key = config('services.openweather.key');

        if (empty($key)) {
            return null;
        }

        $latStr = number_format($lat, 2, '.', '');
        $lngStr = number_format($lng, 2, '.', '');
        $cacheKey = "weather:{$latStr}:{$lngStr}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $quotaKey = 'openweather_quota:'.now()->toDateString();
        $quota = Cache::get($quotaKey, 0);

        if ($quota >= self::QUOTA_LIMIT) {
            return null;
        }

        try {
            $response = Http::timeout(5)->get('https://api.openweathermap.org/data/2.5/weather', [
                'lat' => $lat,
                'lon' => $lng,
                'appid' => $key,
                'units' => 'metric',
            ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        $weatherArray = $data['weather'] ?? [];
        if (empty($weatherArray)) {
            return null;
        }

        $newCount = Cache::increment($quotaKey);
        if ($newCount === 1) {
            Cache::put($quotaKey, 1, self::QUOTA_TTL);
        }

        $precipitation = (float) ($data['rain']['1h'] ?? $data['snow']['1h'] ?? 0.0);

        $weatherData = new WeatherData(
            temperature: (float) $data['main']['temp'],
            conditions: $weatherArray[0]['main'],
            precipitation: $precipitation,
            windSpeed: (float) $data['wind']['speed'],
            sunset: CarbonImmutable::createFromTimestampUTC($data['sys']['sunset']),
            units: 'metric',
        );

        Cache::put($cacheKey, $weatherData, self::CACHE_TTL);

        return $weatherData;
    }
}
