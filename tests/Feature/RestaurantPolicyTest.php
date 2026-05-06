<?php

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows the owner to view their own restaurant', function () {
    $owner = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    expect($owner->can('view', $restaurant))->toBeTrue();
});

it('denies a non-owner from viewing a restaurant', function () {
    $owner = User::factory()->create();
    $nonOwner = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    expect($nonOwner->can('view', $restaurant))->toBeFalse();
});

it('allows the owner to update their own restaurant', function () {
    $owner = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    expect($owner->can('update', $restaurant))->toBeTrue();
});

it('denies a non-owner from updating a restaurant', function () {
    $owner = User::factory()->create();
    $nonOwner = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    expect($nonOwner->can('update', $restaurant))->toBeFalse();
});

it('allows the owner to delete their own restaurant', function () {
    $owner = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    expect($owner->can('delete', $restaurant))->toBeTrue();
});

it('denies a non-owner from deleting a restaurant', function () {
    $owner = User::factory()->create();
    $nonOwner = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    expect($nonOwner->can('delete', $restaurant))->toBeFalse();
});
