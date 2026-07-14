<?php

use App\Enums\QuizQuestion;
use App\Services\QuizAnswers;

test('it returns false for shouldSkip on every question when service level is not set', function () {
    $answers = new QuizAnswers;

    foreach (QuizQuestion::cases() as $question) {
        expect($question->shouldSkip($answers))->toBeFalse();
    }
});

test('it returns true for energy shouldSkip when service level is quick_easy', function () {
    $answers = new QuizAnswers(serviceLevel: 'quick_easy');

    expect(QuizQuestion::Energy->shouldSkip($answers))->toBeTrue();
});

test('it returns true for familiarity shouldSkip when service level is quick_easy', function () {
    $answers = new QuizAnswers(serviceLevel: 'quick_easy');

    expect(QuizQuestion::Familiarity->shouldSkip($answers))->toBeTrue();
});

test('it returns false for energy and familiarity shouldSkip when service level is casual_sit_down', function () {
    $answers = new QuizAnswers(serviceLevel: 'casual_sit_down');

    expect(QuizQuestion::Energy->shouldSkip($answers))->toBeFalse()
        ->and(QuizQuestion::Familiarity->shouldSkip($answers))->toBeFalse();
});

test('it defaults dineInTakeout to either and serviceLevel to casual_sit_down when not provided', function () {
    $answers = new QuizAnswers;

    expect($answers->dineInTakeout)->toBe('either')
        ->and($answers->serviceLevel)->toBe('casual_sit_down');
});

test('it accepts the new distance bucket values under_2_miles, 2_to_5_miles, and 5_to_15_miles', function () {
    foreach (['under_2_miles', '2_to_5_miles', '5_to_15_miles'] as $distance) {
        $answers = new QuizAnswers(distance: $distance);

        expect($answers->distance)->toBe($distance);
    }
});

test('it returns false for shouldSkip on dine-in/takeout, service level, cuisine, hunger, and distance regardless of service level', function () {
    $unaffectedQuestions = [
        QuizQuestion::DineInTakeout,
        QuizQuestion::ServiceLevel,
        QuizQuestion::Cuisine,
        QuizQuestion::Hunger,
        QuizQuestion::Distance,
    ];

    foreach (['quick_easy', 'casual_sit_down', 'nicer_night_out', 'special_occasion'] as $serviceLevel) {
        $answers = new QuizAnswers(serviceLevel: $serviceLevel);

        foreach ($unaffectedQuestions as $question) {
            expect($question->shouldSkip($answers))->toBeFalse();
        }
    }
});
