<?php

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
