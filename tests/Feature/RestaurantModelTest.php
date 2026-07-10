<?php

use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Enums\PrimaryCuisine;
use App\Enums\RestaurantSource;
use App\Enums\ServiceLevel;
use App\Enums\ServiceOption;
use App\Models\Restaurant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the restaurants table with all required columns', function () {
    $columns = Schema::getColumnListing('restaurants');

    expect($columns)->toContain('id')
        ->toContain('owner_user_id')
        ->toContain('name')
        ->toContain('address')
        ->toContain('lat')
        ->toContain('lng')
        ->toContain('cuisine_tags')
        ->toContain('vibe_tags')
        ->toContain('price_level')
        ->toContain('source')
        ->toContain('patio_quality')
        ->toContain('indoor_vibe_when_cold')
        ->toContain('avg_duration_minutes')
        ->toContain('last_visited_at')
        ->toContain('visit_count')
        ->toContain('created_at')
        ->toContain('updated_at');
});

it('stores and retrieves cuisine_tags as an array', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'cuisine_tags' => ['Italian', 'Pizza'],
    ]);

    $fresh = $restaurant->fresh();

    expect($fresh->cuisine_tags)->toBeArray()->toBe(['Italian', 'Pizza']);
});

it('stores and retrieves vibe_tags as an array', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'vibe_tags' => ['Romantic', 'Quiet'],
    ]);

    $fresh = $restaurant->fresh();

    expect($fresh->vibe_tags)->toBeArray()->toBe(['Romantic', 'Quiet']);
});

it('casts source to the RestaurantSource enum', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'source' => RestaurantSource::Favorite,
    ]);

    $fresh = $restaurant->fresh();

    expect($fresh->source)->toBeInstanceOf(RestaurantSource::class)
        ->and($fresh->source)->toBe(RestaurantSource::Favorite);
});

it('casts patio_quality to the PatioQuality enum', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'patio_quality' => PatioQuality::Decent,
    ]);

    $fresh = $restaurant->fresh();

    expect($fresh->patio_quality)->toBeInstanceOf(PatioQuality::class)
        ->and($fresh->patio_quality)->toBe(PatioQuality::Decent);
});

it('casts indoor_vibe_when_cold to the IndoorVibe enum', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'indoor_vibe_when_cold' => IndoorVibe::Cozy,
    ]);

    $fresh = $restaurant->fresh();

    expect($fresh->indoor_vibe_when_cold)->toBeInstanceOf(IndoorVibe::class)
        ->and($fresh->indoor_vibe_when_cold)->toBe(IndoorVibe::Cozy);
});

it('casts last_visited_at as a datetime', function () {
    $user = User::factory()->create();
    $now = now();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'last_visited_at' => $now,
    ]);

    $fresh = $restaurant->fresh();

    expect($fresh->last_visited_at)->toBeInstanceOf(CarbonInterface::class);
});

it('defaults visit_count to zero', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
    ]);

    expect($restaurant->visit_count)->toBe(0);
});

it('returns the owning user via the user relationship', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
    ]);

    expect($restaurant->user->id)->toBe($user->id);
});

it('returns a user\'s restaurants via the hasMany relationship', function () {
    $user = User::factory()->create();
    Restaurant::factory()->count(3)->create([
        'owner_user_id' => $user->id,
    ]);

    expect($user->restaurants)->toHaveCount(3);
});

it('scopes restaurants to a specific owner via scopeOwnedBy', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Restaurant::factory()->count(2)->create(['owner_user_id' => $user1->id]);
    Restaurant::factory()->count(3)->create(['owner_user_id' => $user2->id]);

    $user1Restaurants = Restaurant::ownedBy($user1)->get();

    expect($user1Restaurants)->toHaveCount(2);
});

it('casts service_level to a ServiceLevel enum', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'service_level' => ServiceLevel::Casual,
    ]);

    $fresh = $restaurant->fresh();

    expect($fresh->service_level)->toBeInstanceOf(ServiceLevel::class)
        ->and($fresh->service_level)->toBe(ServiceLevel::Casual);
});

it('casts service_options to an array', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'service_options' => ['dine_in', 'takeout'],
    ]);

    $fresh = $restaurant->fresh();

    expect($fresh->service_options)->toBeArray()->toBe(['dine_in', 'takeout']);
});

it('casts primary_cuisine to a PrimaryCuisine enum', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'primary_cuisine' => PrimaryCuisine::Italian,
    ]);

    $fresh = $restaurant->fresh();

    expect($fresh->primary_cuisine)->toBeInstanceOf(PrimaryCuisine::class)
        ->and($fresh->primary_cuisine)->toBe(PrimaryCuisine::Italian);
});

it('allows null for all three classification fields', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'service_level' => null,
        'service_options' => null,
        'primary_cuisine' => null,
    ]);

    $fresh = $restaurant->fresh();

    expect($fresh->service_level)->toBeNull()
        ->and($fresh->service_options)->toBeNull()
        ->and($fresh->primary_cuisine)->toBeNull();
});

it('returns a friendly label for every ServiceLevel case', function () {
    foreach (ServiceLevel::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

it('returns a friendly label for every ServiceOption case', function () {
    foreach (ServiceOption::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

it('returns a friendly label for every PrimaryCuisine case', function () {
    foreach (PrimaryCuisine::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});
