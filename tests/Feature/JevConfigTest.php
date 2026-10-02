<?php

use App\Models\Restaurant;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults the typesafe model to jev-latest', function () {
    expect(config('services.typesafe.model'))->toBe('jev-latest');
});

it('defaults the typesafe daily quota to 10000', function () {
    expect(config('services.typesafe.daily_quota'))->toBe(10000);
});

it('defaults the typesafe minimum confidence to 0.6', function () {
    expect(config('services.typesafe.min_confidence'))->toBe(0.6);
});

it('stores a null profiled_at for a new restaurant', function () {
    expect(Restaurant::factory()->create()->fresh()->profiled_at)->toBeNull();
});

it('casts profiled_at to a Carbon datetime', function () {
    $restaurant = Restaurant::factory()->create(['profiled_at' => '2026-01-02 03:04:05']);

    expect($restaurant->fresh()->profiled_at)->toBeInstanceOf(CarbonInterface::class);
});

it('has no typesafe api key configured in the test environment', function () {
    expect(config('services.typesafe.key'))->toBeEmpty();
});
