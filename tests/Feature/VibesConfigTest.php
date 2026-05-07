<?php

it('loads the vibes config and returns an array keyed by dimension', function () {
    $vibes = config('vibes');

    expect($vibes)->toBeArray();
    expect(array_keys($vibes))->toBe(['energy', 'occasion', 'experience']);
});

it('defines an energy dimension with lively, quiet, and moderate', function () {
    $energy = config('vibes.energy');

    expect($energy)->toBe(['lively', 'quiet', 'moderate']);
});

it('defines an occasion dimension with casual, date_night, special_occasion, and quick', function () {
    $occasion = config('vibes.occasion');

    expect($occasion)->toBe(['casual', 'date_night', 'special_occasion', 'quick']);
});

it('defines an experience dimension with cozy, trendy, classic, and adventurous', function () {
    $experience = config('vibes.experience');

    expect($experience)->toBe(['cozy', 'trendy', 'classic', 'adventurous']);
});

it('can be flattened to a single list of all twelve valid tags', function () {
    $vibes = config('vibes');
    $flat = array_merge(...array_values($vibes));

    expect($flat)->toHaveCount(11);
    expect($flat)->toBe([
        'lively', 'quiet', 'moderate',
        'casual', 'date_night', 'special_occasion', 'quick',
        'cozy', 'trendy', 'classic', 'adventurous',
    ]);
});
