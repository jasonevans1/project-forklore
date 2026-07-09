<?php

use App\Enums\PrimaryCuisine;
use App\Enums\ServiceLevel;
use App\Enums\ServiceOption;
use App\Models\Restaurant;
use Database\Seeders\RestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;

uses(RefreshDatabase::class);

it('seeds restaurants with vibe_tags that all exist in the vibes taxonomy', function () {
    $this->seed(RestaurantSeeder::class);

    $validTags = Arr::flatten(config('vibes'));

    Restaurant::all()->each(function (Restaurant $restaurant) use ($validTags) {
        foreach ($restaurant->vibe_tags as $tag) {
            expect($validTags)->toContain($tag);
        }
    });
});

it('populates service_level for every seeded restaurant', function () {
    $this->seed(RestaurantSeeder::class);

    Restaurant::all()->each(function (Restaurant $restaurant) {
        expect($restaurant->service_level)->toBeInstanceOf(ServiceLevel::class);
    });
});

it('populates service_options with at least one valid option for every seeded restaurant', function () {
    $this->seed(RestaurantSeeder::class);

    Restaurant::all()->each(function (Restaurant $restaurant) {
        expect($restaurant->service_options)->toBeArray()->not->toBeEmpty();

        foreach ($restaurant->service_options as $option) {
            expect(ServiceOption::tryFrom($option))->not->toBeNull();
        }
    });
});

it('populates primary_cuisine for every seeded restaurant', function () {
    $this->seed(RestaurantSeeder::class);

    Restaurant::all()->each(function (Restaurant $restaurant) {
        expect($restaurant->primary_cuisine)->toBeInstanceOf(PrimaryCuisine::class);
    });
});

it('assigns the expected classification to known restaurants', function () {
    $this->seed(RestaurantSeeder::class);

    $fongs = Restaurant::where('name', "Fong's Pizza")->firstOrFail();
    expect($fongs->service_level)->toBe(ServiceLevel::Casual)
        ->and($fongs->service_options)->toEqualCanonicalizing(['dine_in', 'takeout', 'delivery'])
        ->and($fongs->primary_cuisine)->toBe(PrimaryCuisine::Pizza);

    $proof = Restaurant::where('name', 'Proof')->firstOrFail();
    expect($proof->service_level)->toBe(ServiceLevel::FineDining)
        ->and($proof->service_options)->toEqualCanonicalizing(['dine_in'])
        ->and($proof->primary_cuisine)->toBe(PrimaryCuisine::Mediterranean);

    $eateryA = Restaurant::where('name', 'Eatery A')->firstOrFail();
    expect($eateryA->service_level)->toBe(ServiceLevel::UpscaleCasual)
        ->and($eateryA->service_options)->toEqualCanonicalizing(['dine_in', 'takeout'])
        ->and($eateryA->primary_cuisine)->toBe(PrimaryCuisine::AsianGeneral);
});
