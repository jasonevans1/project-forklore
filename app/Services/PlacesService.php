<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PlacesService
{
    private const BASE_URL = 'https://places.googleapis.com/v1/places';

    private const CACHE_TTL_SECONDS = 7 * 24 * 3600; // 7 days

    private const QUOTA_TTL_SECONDS = 2 * 24 * 3600; // 2 days (covers day boundary)

    /**
     * Search restaurants by free-text query.
     *
     * @return array<int, array<string, mixed>>|null Parsed places, or null when quota exceeded / key missing
     */
    public function textSearch(string $query): ?array
    {
        if (! $this->hasApiKey()) {
            return null;
        }

        $hash = hash('sha256', "text_search:{$query}");

        $cached = $this->resolveFromCache($hash);
        if ($cached !== null) {
            return $this->parsePlaces($cached);
        }

        if ($this->isQuotaExceeded()) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => $this->apiKey(),
                'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.types,places.rating',
            ])->post(self::BASE_URL.':searchText', [
                'textQuery' => $query,
            ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $raw = $response->json();
        $this->storeInCache($hash, $raw);
        $this->incrementQuota();

        return $this->parsePlaces($raw);
    }

    /**
     * Search restaurants near a coordinate within a given radius (metres).
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function nearbySearch(float $lat, float $lng, int $radiusMetres = 1500): ?array
    {
        if (! $this->hasApiKey()) {
            return null;
        }

        $hash = hash('sha256', "nearby_search:{$lat}:{$lng}:{$radiusMetres}");

        $cached = $this->resolveFromCache($hash);
        if ($cached !== null) {
            return $this->parsePlaces($cached);
        }

        if ($this->isQuotaExceeded()) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => $this->apiKey(),
                'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.types,places.rating',
            ])->post(self::BASE_URL.':searchNearby', [
                'includedTypes' => ['restaurant'],
                'maxResultCount' => 20,
                'locationRestriction' => [
                    'circle' => [
                        'center' => ['latitude' => $lat, 'longitude' => $lng],
                        'radius' => (float) $radiusMetres,
                    ],
                ],
            ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $raw = $response->json();
        $this->storeInCache($hash, $raw);
        $this->incrementQuota();

        return $this->parsePlaces($raw);
    }

    /**
     * Fetch full details for a specific place ID.
     *
     * @return array<string, mixed>|null
     */
    public function placeDetails(string $placeId): ?array
    {
        if (! $this->hasApiKey()) {
            return null;
        }

        $hash = hash('sha256', "place_details:{$placeId}");

        $cached = $this->resolveFromCache($hash);
        if ($cached !== null) {
            return $this->parseDetails($cached);
        }

        if ($this->isQuotaExceeded()) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => $this->apiKey(),
                'X-Goog-FieldMask' => 'id,displayName,formattedAddress,types,rating,regularOpeningHours,websiteUri',
            ])->get(self::BASE_URL."/{$placeId}");
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $raw = $response->json();
        $this->storeInCache($hash, $raw);
        $this->incrementQuota();

        return $this->parseDetails($raw);
    }

    // ---------------------------------------------------------------------------
    // Cache helpers
    // ---------------------------------------------------------------------------

    /**
     * Resolve a cached response: check Laravel cache first, then the DB table.
     * Returns the raw response array if fresh (≤ 7 days), or null on a miss.
     *
     * @return array<string, mixed>|null
     */
    private function resolveFromCache(string $hash): ?array
    {
        $cacheKey = "places:{$hash}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Fall through to DB (covers process-cache miss after restart)
        $row = DB::table('places_cache')->where('query_hash', $hash)->first();

        if ($row === null) {
            return null;
        }

        if (Carbon::parse($row->fetched_at)->lt(now()->subDays(7))) {
            return null;
        }

        $decoded = json_decode($row->response_json, true);
        Cache::put($cacheKey, $decoded, self::CACHE_TTL_SECONDS);

        return $decoded;
    }

    /**
     * Store a raw API response in both the Laravel cache and the DB table.
     *
     * @param  array<string, mixed>  $raw
     */
    private function storeInCache(string $hash, array $raw): void
    {
        $cacheKey = "places:{$hash}";
        Cache::put($cacheKey, $raw, self::CACHE_TTL_SECONDS);

        DB::table('places_cache')->upsert(
            [
                'query_hash' => $hash,
                'response_json' => json_encode($raw),
                'fetched_at' => now()->toDateTimeString(),
            ],
            uniqueBy: ['query_hash'],
            update: ['response_json', 'fetched_at'],
        );
    }

    // ---------------------------------------------------------------------------
    // Quota helpers
    // ---------------------------------------------------------------------------

    private function quotaKey(): string
    {
        return 'google_places_quota:'.now()->toDateString();
    }

    private function isQuotaExceeded(): bool
    {
        $cap = (int) config('services.google_places.daily_quota', 1000);
        $used = (int) Cache::get($this->quotaKey(), 0);

        return $used >= $cap;
    }

    private function incrementQuota(): void
    {
        $key = $this->quotaKey();
        $newCount = Cache::increment($key);

        if ($newCount === 1) {
            Cache::put($key, 1, self::QUOTA_TTL_SECONDS);
        }
    }

    // ---------------------------------------------------------------------------
    // API key helpers
    // ---------------------------------------------------------------------------

    private function hasApiKey(): bool
    {
        return ! empty(config('services.google_places.key'));
    }

    private function apiKey(): string
    {
        return (string) config('services.google_places.key');
    }

    // ---------------------------------------------------------------------------
    // Response parsing
    // ---------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $raw
     * @return array<int, array<string, mixed>>
     */
    private function parsePlaces(array $raw): array
    {
        return array_map(
            fn (array $place) => [
                'id' => $place['id'] ?? null,
                'name' => $place['displayName']['text'] ?? null,
                'address' => $place['formattedAddress'] ?? null,
                'types' => $place['types'] ?? [],
                'rating' => $place['rating'] ?? null,
            ],
            $raw['places'] ?? []
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function parseDetails(array $raw): array
    {
        return [
            'id' => $raw['id'] ?? null,
            'name' => $raw['displayName']['text'] ?? null,
            'address' => $raw['formattedAddress'] ?? null,
            'types' => $raw['types'] ?? [],
            'rating' => $raw['rating'] ?? null,
            'opening_hours' => $raw['regularOpeningHours']['weekdayDescriptions'] ?? [],
            'website' => $raw['websiteUri'] ?? null,
        ];
    }
}
