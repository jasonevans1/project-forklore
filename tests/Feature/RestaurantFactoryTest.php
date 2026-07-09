<?php

use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Enums\PrimaryCuisine;
use App\Enums\RestaurantSource;
use App\Enums\ServiceLevel;
use App\Enums\ServiceOption;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;

uses(RefreshDatabase::class);

it('creates a restaurant with factory defaults', function () {
    $restaurant = Restaurant::factory()->create();

    expect($restaurant->exists)->toBeTrue()
        ->and($restaurant->name)->toBeString()->not->toBeEmpty()
        ->and($restaurant->address)->toBeString()->not->toBeEmpty()
        ->and($restaurant->source)->toBe(RestaurantSource::Favorite)
        ->and($restaurant->patio_quality)->toBe(PatioQuality::None)
        ->and($restaurant->indoor_vibe_when_cold)->toBe(IndoorVibe::Neutral)
        ->and($restaurant->last_visited_at)->toBeNull()
        ->and($restaurant->visit_count)->toBe(0);
});

it('creates a restaurant belonging to a specific user', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $user->id]);

    expect($restaurant->owner_user_id)->toBe($user->id)
        ->and($restaurant->user->id)->toBe($user->id);
});

it('generates cuisine_tags as a non-empty array', function () {
    $restaurant = Restaurant::factory()->create();

    expect($restaurant->cuisine_tags)->toBeArray()->not->toBeEmpty();
});

it('generates vibe_tags as a non-empty array', function () {
    $restaurant = Restaurant::factory()->create();

    expect($restaurant->vibe_tags)->toBeArray()->not->toBeEmpty();
});

it('creates a restaurant with vibe_tags entries that all exist in the vibes taxonomy', function () {
    $validTags = Arr::flatten(config('vibes'));
    $restaurant = Restaurant::factory()->create();

    foreach ($restaurant->vibe_tags as $tag) {
        expect($validTags)->toContain($tag);
    }
});

it('creates a restaurant whose vibe_tags are stored as an array', function () {
    $restaurant = Restaurant::factory()->create();

    expect($restaurant->vibe_tags)->toBeArray();
});

it('generates a valid price_level between 1 and 4', function () {
    $restaurant = Restaurant::factory()->create();

    expect($restaurant->price_level)->toBeInt()->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(4);
});

it('generates a valid RestaurantSource enum value', function () {
    $restaurant = Restaurant::factory()->create();

    expect($restaurant->source)->toBeInstanceOf(RestaurantSource::class);
});

it('generates an address string', function () {
    $restaurant = Restaurant::factory()->create();

    expect($restaurant->address)->toBeString()->not->toBeEmpty()
        ->toContain('Des Moines, IA');
});

it('generates a valid service_level for factory restaurants', function () {
    $restaurant = Restaurant::factory()->create();

    expect($restaurant->service_level)->toBeInstanceOf(ServiceLevel::class);
});

it('generates a non-empty array of valid service options for factory restaurants', function () {
    $restaurant = Restaurant::factory()->create();

    expect($restaurant->service_options)->toBeArray()->not->toBeEmpty();

    foreach ($restaurant->service_options as $option) {
        expect(ServiceOption::tryFrom($option))->not->toBeNull();
    }
});

it('generates a valid primary_cuisine for factory restaurants', function () {
    $restaurant = Restaurant::factory()->create();

    expect($restaurant->primary_cuisine)->toBeInstanceOf(PrimaryCuisine::class);
});

it('never generates drive_thru for fine dining or upscale casual restaurants', function () {
    foreach ([ServiceLevel::FineDining, ServiceLevel::UpscaleCasual] as $serviceLevel) {
        $restaurants = Restaurant::factory()->count(20)->withServiceLevel($serviceLevel)->make();

        foreach ($restaurants as $restaurant) {
            expect($restaurant->service_options)->not->toContain(ServiceOption::DriveThru->value);
        }
    }
});

it('always includes dine_in for fine dining restaurants', function () {
    $restaurants = Restaurant::factory()->count(20)->withServiceLevel(ServiceLevel::FineDining)->make();

    foreach ($restaurants as $restaurant) {
        expect($restaurant->service_options)->toContain(ServiceOption::DineIn->value);
    }
});
