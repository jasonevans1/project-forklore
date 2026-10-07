<?php

use App\Actions\ParseVibe;
use App\Enums\PrimaryCuisine;
use App\Services\JevService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

function choice(string $value): array
{
    return ['type' => 'choice', 'value' => $value, 'confidence' => 0.9];
}

it('sends one Jev request with the vibe text as state and six choice questions', function () {
    $this->mock(JevService::class)
        ->shouldReceive('ask')
        ->once()
        ->withArgs(function (array|string $state, array $questions): bool {
            return $state === ['vibe' => 'cozy date night']
                && array_keys($questions) === ['energy', 'hunger', 'familiarity', 'cuisine', 'serviceLevel', 'dineInTakeout']
                && collect($questions)->every(fn (array $q): bool => $q['type'] === 'choice')
                && array_keys($questions['energy']['criteria']) === ['lively', 'moderate', 'quiet']
                && array_keys($questions['dineInTakeout']['criteria']) === ['dine_in', 'takeout', 'either'];
        })
        ->andReturn([]);

    app(ParseVibe::class)->execute('cozy date night');
});

it('returns confident answers mapped to quiz answer fields', function () {
    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'energy' => choice('quiet'),
        'hunger' => choice('full_meal'),
        'familiarity' => choice('new'),
        'cuisine' => choice('italian'),
        'serviceLevel' => choice('nicer_night_out'),
        'dineInTakeout' => choice('dine_in'),
    ]);

    expect(app(ParseVibe::class)->execute('date night'))->toBe([
        'energy' => 'quiet',
        'hunger' => 'full_meal',
        'familiarity' => 'new',
        'cuisine' => 'italian',
        'serviceLevel' => 'nicer_night_out',
        'dineInTakeout' => 'dine_in',
    ]);
});

it('omits fields Jev did not answer confidently', function () {
    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'energy' => choice('lively'),
    ]);

    expect(app(ParseVibe::class)->execute('fun'))->toBe(['energy' => 'lively']);
});

it('maps a none cuisine answer to a present null cuisine', function () {
    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'cuisine' => choice('none'),
    ]);

    expect(app(ParseVibe::class)->execute('anything'))->toBe(['cuisine' => null]);
});

it('omits fields whose values are outside the allowed options', function () {
    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'energy' => choice('wild'),
        'cuisine' => choice('other'),
        'hunger' => choice('feast'),
    ]);

    expect(app(ParseVibe::class)->execute('x'))->toBe(['hunger' => 'feast']);
});

it('does not offer the other cuisine as a choice', function () {
    $this->mock(JevService::class)
        ->shouldReceive('ask')
        ->once()
        ->withArgs(function (array|string $state, array $questions): bool {
            $keys = array_keys($questions['cuisine']['criteria']);

            return ! in_array('other', $keys, true)
                && in_array('none', $keys, true)
                && in_array(PrimaryCuisine::Pizza->value, $keys, true);
        })
        ->andReturn([]);

    app(ParseVibe::class)->execute('x');
});

it('returns null when Jev is unavailable', function () {
    $this->mock(JevService::class)->shouldReceive('ask')->andReturn(null);

    expect(app(ParseVibe::class)->execute('x'))->toBeNull();
});
