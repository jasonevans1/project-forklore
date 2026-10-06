<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JevService
{
    private const ENDPOINT = 'https://api.typesafe.ai/v1/systemone';

    private const VALUE_TYPES = ['choice', 'noul', 'score'];

    /** Seconds an identical request is not retried after a failure. */
    private const FAILURE_BACKOFF_SECONDS = 60;

    /**
     * Ask Jev typed questions about some state and return the confident answers.
     *
     * @param  array<string, mixed>|string  $state
     * @param  array<string, array{type: string, instructions: string, criteria?: array<int|string, string|null>}>  $questions
     * @return array<string, array{type: string, value: string|float, confidence: float}>|null Null when no API key is configured
     */
    public function ask(array|string $state, array $questions): ?array
    {
        $key = config('services.typesafe.key');

        if (! $key) {
            return null;
        }

        $model = config('services.typesafe.model');
        $cacheKey = 'jev:'.hash('sha256', (string) json_encode([$model, $state, $questions]));

        $answers = Cache::get($cacheKey);

        if ($answers === null) {
            if (Cache::has($cacheKey.':failed') || $this->isQuotaExceeded()) {
                return null;
            }

            try {
                $response = Http::timeout(config('services.typesafe.timeout'))
                    ->withToken($key)
                    ->acceptJson()
                    ->post(self::ENDPOINT, ['state' => $state, 'model' => $model, 'questions' => $questions]);
            } catch (ConnectionException) {
                Cache::put($cacheKey.':failed', true, self::FAILURE_BACKOFF_SECONDS);

                return null;
            }

            if ($response->failed()) {
                Cache::put($cacheKey.':failed', true, self::FAILURE_BACKOFF_SECONDS);

                if (in_array($response->status(), [401, 422], true)) {
                    Log::warning('Jev request failed', ['status' => $response->status()]);
                }

                return null;
            }

            $answers = (array) $response->json('answers');
            Cache::put($cacheKey, $answers, now()->addDays(30));
            $this->incrementQuota();
        }

        return $this->parseAnswers($answers);
    }

    private function quotaKey(): string
    {
        return 'jev_quota:'.now()->toDateString();
    }

    private function isQuotaExceeded(): bool
    {
        return (int) Cache::get($this->quotaKey(), 0) >= config('services.typesafe.daily_quota');
    }

    private function incrementQuota(): void
    {
        Cache::add($this->quotaKey(), 0, now()->addDays(2));
        Cache::increment($this->quotaKey());
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, array{type: string, value: string|float, confidence: float}>
     */
    private function parseAnswers(array $answers): array
    {
        $parsed = [];

        foreach ($answers as $id => $answer) {
            $type = $answer['type'] ?? null;

            if (! is_array($answer) || ! in_array($type, self::VALUE_TYPES, true)) {
                continue;
            }

            $confidence = $answer['confidence'] ?? null;
            $value = $answer[$type] ?? null;

            if (! is_numeric($confidence) || $confidence < config('services.typesafe.min_confidence') || $value === null) {
                continue;
            }

            $parsed[$id] = ['type' => $type, 'value' => $value, 'confidence' => (float) $confidence];
        }

        return $parsed;
    }
}
