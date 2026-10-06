<?php

use App\Services\JevService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    config([
        'services.typesafe.key' => 'test-key',
        'services.typesafe.model' => 'jev-latest',
        'services.typesafe.min_confidence' => 0.6,
    ]);
});

/**
 * @param  array<string, mixed>  $answers
 */
function fakeJev(array $answers): void
{
    Http::fake(['api.typesafe.ai/*' => Http::response(['model' => 'jev-latest', 'answers' => $answers])]);
}

$questions = [
    'cuisine' => ['type' => 'choice', 'instructions' => 'Pick one', 'criteria' => ['italian' => null, 'thai' => 'Thai food']],
];

it('posts state, model and questions to the Jev systemone endpoint with a bearer token', function () use ($questions) {
    fakeJev([]);

    app(JevService::class)->ask('some state', $questions);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.typesafe.ai/v1/systemone'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request['state'] === 'some state'
        && $request['model'] === 'jev-latest'
        && $request['questions'] === $questions);
});

it('returns a confident choice answer keyed by question id', function () use ($questions) {
    fakeJev(['cuisine' => ['type' => 'choice', 'choice' => 'italian', 'confidence' => 0.92]]);

    expect(app(JevService::class)->ask('s', $questions))
        ->toBe(['cuisine' => ['type' => 'choice', 'value' => 'italian', 'confidence' => 0.92]]);
});

it('returns noul and score answers with their numeric values', function () {
    fakeJev([
        'a' => ['type' => 'noul', 'noul' => 0.81, 'confidence' => 0.88],
        'b' => ['type' => 'score', 'score' => 1.4, 'confidence' => 0.7],
    ]);

    expect(app(JevService::class)->ask('s', []))->toBe([
        'a' => ['type' => 'noul', 'value' => 0.81, 'confidence' => 0.88],
        'b' => ['type' => 'score', 'value' => 1.4, 'confidence' => 0.7],
    ]);
});

it('drops answers below the configured minimum confidence', function () {
    fakeJev([
        'low' => ['type' => 'choice', 'choice' => 'thai', 'confidence' => 0.3],
        'none' => ['type' => 'choice', 'choice' => 'thai'],
        'ok' => ['type' => 'choice', 'choice' => 'thai', 'confidence' => 0.6],
    ]);

    expect(array_keys(app(JevService::class)->ask('s', [])))->toBe(['ok']);
});

it('ignores answers that are missing a value or have an unknown type', function () {
    fakeJev([
        'novalue' => ['type' => 'choice', 'confidence' => 0.9],
        'weird' => ['type' => 'bogus', 'bogus' => 'x', 'confidence' => 0.9],
        'junk' => 'nope',
    ]);

    expect(app(JevService::class)->ask('s', []))->toBe([]);
});

it('returns null without an HTTP call when no API key is configured', function () {
    config(['services.typesafe.key' => null]);

    expect(app(JevService::class)->ask('s', []))->toBeNull();
    Http::assertNothingSent();
});

it('returns cached answers without a second HTTP call for identical state and questions', function () use ($questions) {
    fakeJev(['cuisine' => ['type' => 'choice', 'choice' => 'italian', 'confidence' => 0.92]]);

    $first = app(JevService::class)->ask('same', $questions);
    $second = app(JevService::class)->ask('same', $questions);

    expect($second)->toBe($first);
    Http::assertSentCount(1);
});

it('makes a new HTTP call when the state differs', function () use ($questions) {
    fakeJev([]);

    app(JevService::class)->ask('one', $questions);
    app(JevService::class)->ask('two', $questions);

    Http::assertSentCount(2);
});

it('applies the confidence filter to cached answers on read', function () use ($questions) {
    fakeJev(['cuisine' => ['type' => 'choice', 'choice' => 'italian', 'confidence' => 0.7]]);

    expect(app(JevService::class)->ask('s', $questions))->toHaveKey('cuisine');

    config(['services.typesafe.min_confidence' => 0.9]);

    expect(app(JevService::class)->ask('s', $questions))->toBe([]);
    Http::assertSentCount(1);
});

it('returns null without an HTTP call once the daily quota is reached', function () {
    config(['services.typesafe.daily_quota' => 1]);
    fakeJev([]);

    app(JevService::class)->ask('one', []);

    expect(app(JevService::class)->ask('two', []))->toBeNull();
    Http::assertSentCount(1);
});

it('does not count cached responses toward the daily quota', function () {
    config(['services.typesafe.daily_quota' => 1]);
    fakeJev([]);

    app(JevService::class)->ask('one', []);
    app(JevService::class)->ask('one', []);

    expect(app(JevService::class)->ask('one', []))->toBe([]);
    Http::assertSentCount(1);
});

it('returns null for 401, 422, 429 and 529 responses', function (int $status) {
    Http::fake(['api.typesafe.ai/*' => Http::response(['error' => 'x'], $status)]);

    expect(app(JevService::class)->ask('s', []))->toBeNull();
})->with([401, 422, 429, 529]);

it('logs a warning for 401 and 422 but not 429 or 529', function (int $status, bool $logged) {
    Log::spy();
    Http::fake(['api.typesafe.ai/*' => Http::response(['error' => 'x'], $status)]);

    app(JevService::class)->ask('s', []);

    if ($logged) {
        Log::shouldHaveReceived('warning')->once();
    } else {
        Log::shouldNotHaveReceived('warning');
    }
})->with([[401, true], [422, true], [429, false], [529, false]]);

it('returns null when the request times out or the connection fails', function () {
    Http::fake(['api.typesafe.ai/*' => fn () => throw new ConnectionException('timeout')]);

    expect(app(JevService::class)->ask('s', []))->toBeNull();
});

it('does not cache failed responses', function () {
    Http::fake(['api.typesafe.ai/*' => Http::sequence()
        ->push(['error' => 'x'], 529)
        ->push(['answers' => ['a' => ['type' => 'noul', 'noul' => 0.5, 'confidence' => 0.9]]])]);

    expect(app(JevService::class)->ask('s', []))->toBeNull();

    $this->travel(61)->seconds();

    expect(app(JevService::class)->ask('s', []))->toHaveKey('a');
});

it('does not retry an identical request within 60 seconds of a failure', function () {
    Http::fake(['api.typesafe.ai/*' => Http::response(['error' => 'x'], 529)]);

    expect(app(JevService::class)->ask('s', []))->toBeNull()
        ->and(app(JevService::class)->ask('s', []))->toBeNull();
    Http::assertSentCount(1);
});

it('retries an identical request after the failure window expires', function () {
    Http::fake(['api.typesafe.ai/*' => Http::sequence()
        ->push(['error' => 'x'], 529)
        ->push(['answers' => ['a' => ['type' => 'noul', 'noul' => 0.5, 'confidence' => 0.9]]])]);

    app(JevService::class)->ask('s', []);
    $this->travel(61)->seconds();

    expect(app(JevService::class)->ask('s', []))->toHaveKey('a');
    Http::assertSentCount(2);
});

it('still sends a different request while another one is marked as failed', function () {
    Http::fake(['api.typesafe.ai/*' => Http::sequence()
        ->push(['error' => 'x'], 529)
        ->push(['answers' => ['a' => ['type' => 'noul', 'noul' => 0.5, 'confidence' => 0.9]]])]);

    app(JevService::class)->ask('one', []);

    expect(app(JevService::class)->ask('two', []))->toHaveKey('a');
    Http::assertSentCount(2);
});

it('remembers timeouts and connection errors as failures too', function () {
    $attempts = 0;
    Http::fake(['api.typesafe.ai/*' => function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('timeout');
    }]);

    app(JevService::class)->ask('s', []);
    app(JevService::class)->ask('s', []);

    expect($attempts)->toBe(1);
});
