<?php

use App\Actions\ClassifyEventDescription;
use App\Enums\EventRecurrence;
use App\Enums\EventType;
use App\Services\JevService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('sends one Jev request with the description as state and type and recurrence questions', function () {
    $this->mock(JevService::class)
        ->shouldReceive('ask')
        ->once()
        ->withArgs(function (array|string $state, array $questions): bool {
            return $state === ['event_description' => 'Trivia every Wednesday']
                && array_keys($questions) === ['type', 'recurrence']
                && $questions['type']['type'] === 'choice'
                && array_keys($questions['type']['criteria']) === array_column(EventType::cases(), 'value')
                && $questions['recurrence']['type'] === 'choice'
                && array_keys($questions['recurrence']['criteria']) === array_column(EventRecurrence::cases(), 'value');
        })
        ->andReturn([]);

    app(ClassifyEventDescription::class)->execute('Trivia every Wednesday');
});

it('returns the event type from a confident answer', function () {
    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'type' => ['type' => 'choice', 'value' => 'trivia', 'confidence' => 0.9],
    ]);

    expect(app(ClassifyEventDescription::class)->execute('Trivia night'))
        ->toBe(['type' => EventType::Trivia, 'recurrence' => null]);
});

it('returns the recurrence from a confident answer', function () {
    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'recurrence' => ['type' => 'choice', 'value' => 'weekly', 'confidence' => 0.9],
    ]);

    expect(app(ClassifyEventDescription::class)->execute('Every Friday'))
        ->toBe(['type' => null, 'recurrence' => EventRecurrence::Weekly]);
});

it('returns null for a choice that is not a valid enum value', function () {
    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'type' => ['type' => 'choice', 'value' => 'karaoke', 'confidence' => 0.9],
        'recurrence' => ['type' => 'choice', 'value' => 'daily', 'confidence' => 0.9],
    ]);

    expect(app(ClassifyEventDescription::class)->execute('Karaoke daily'))
        ->toBe(['type' => null, 'recurrence' => null]);
});

it('returns nulls when Jev returns null', function () {
    $this->mock(JevService::class)->shouldReceive('ask')->andReturn(null);

    expect(app(ClassifyEventDescription::class)->execute('Anything'))
        ->toBe(['type' => null, 'recurrence' => null]);
});
