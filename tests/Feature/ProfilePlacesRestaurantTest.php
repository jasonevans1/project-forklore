<?php

use App\Actions\ProfilePlacesRestaurant;
use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Enums\PrimaryCuisine;
use App\Enums\RestaurantSource;
use App\Enums\ServiceLevel;
use App\Models\Restaurant;
use App\Services\JevService;
use Illuminate\Support\Facades\Cache;

/**
 * @param  array<string, mixed>  $attributes
 */
function placesRestaurant(array $attributes = []): Restaurant
{
    return Restaurant::factory()->create($attributes + [
        'source' => RestaurantSource::Places,
        'places_id' => 'places-'.fake()->unique()->numerify('######'),
    ]);
}

/**
 * @return array{type: string, value: string|float, confidence: float}
 */
function answer(string $type, string|float $value): array
{
    return ['type' => $type, 'value' => $value, 'confidence' => 0.9];
}

it('sends one Jev request with the restaurant name, address, cuisine tags and price level as state', function () {
    $restaurant = placesRestaurant([
        'name' => 'Taco Place',
        'address' => '1 Main St',
        'cuisine_tags' => ['mexican'],
        'price_level' => null,
    ]);

    $this->mock(JevService::class)
        ->shouldReceive('ask')
        ->once()
        ->withArgs(function (mixed $state, mixed $questions): bool {
            return $state === ['name' => 'Taco Place', 'address' => '1 Main St', 'cuisine_tags' => ['mexican'], 'price_level' => null]
                && array_keys($questions) === ['primary_cuisine', 'patio_quality', 'indoor_vibe_when_cold', 'service_level', 'weather_dependent', 'vibe_energy', 'vibe_occasion', 'vibe_experience'];
        })
        ->andReturn([]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);
});

it('sets primary cuisine, patio quality, indoor vibe and service level from confident answers', function () {
    $restaurant = placesRestaurant();

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'primary_cuisine' => answer('choice', 'thai'),
        'patio_quality' => answer('choice', 'destination'),
        'indoor_vibe_when_cold' => answer('choice', 'cozy'),
        'service_level' => answer('choice', 'fine_dining'),
    ]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);
    $restaurant->refresh();

    expect($restaurant->primary_cuisine)->toBe(PrimaryCuisine::Thai)
        ->and($restaurant->patio_quality)->toBe(PatioQuality::Destination)
        ->and($restaurant->indoor_vibe_when_cold)->toBe(IndoorVibe::Cozy)
        ->and($restaurant->service_level)->toBe(ServiceLevel::FineDining);
});

it('adds the weather_dependent vibe tag when the noul answer is at least 0.5', function () {
    $restaurant = placesRestaurant(['vibe_tags' => ['weather_dependent', 'lively']]);
    $other = placesRestaurant(['vibe_tags' => ['lively']]);

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn(['weather_dependent' => answer('noul', 0.5)]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);
    app(ProfilePlacesRestaurant::class)->execute($other);

    expect($restaurant->refresh()->vibe_tags)->toBe(['weather_dependent', 'lively'])
        ->and($other->refresh()->vibe_tags)->toBe(['lively', 'weather_dependent']);
});

it('does not add weather_dependent when the noul answer is below 0.5', function () {
    $restaurant = placesRestaurant(['vibe_tags' => ['lively']]);

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn(['weather_dependent' => answer('noul', 0.4)]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);

    expect($restaurant->refresh()->vibe_tags)->toBe(['lively']);
});

it("keeps a field's current value when its answer is missing", function () {
    $restaurant = placesRestaurant([
        'primary_cuisine' => PrimaryCuisine::Pizza,
        'service_level' => ServiceLevel::Casual,
    ]);

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'patio_quality' => answer('choice', 'decent'),
    ]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);
    $restaurant->refresh();

    expect($restaurant->primary_cuisine)->toBe(PrimaryCuisine::Pizza)
        ->and($restaurant->service_level)->toBe(ServiceLevel::Casual)
        ->and($restaurant->patio_quality)->toBe(PatioQuality::Decent);
});

it('ignores a choice that is not a valid enum value', function () {
    $restaurant = placesRestaurant(['primary_cuisine' => PrimaryCuisine::Pizza]);

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'primary_cuisine' => answer('choice', 'martian'),
    ]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);

    expect($restaurant->refresh()->primary_cuisine)->toBe(PrimaryCuisine::Pizza);
});

it('sets profiled_at after Jev returns answers', function () {
    $restaurant = placesRestaurant();

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn(['patio_quality' => answer('choice', 'decent')]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);

    expect($restaurant->refresh()->profiled_at)->not->toBeNull();
});

it('sets profiled_at when Jev returns no confident answers', function () {
    $restaurant = placesRestaurant();

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);

    expect($restaurant->refresh()->profiled_at)->not->toBeNull();
});

it('leaves the restaurant unchanged and profiled_at null when Jev returns null', function () {
    $restaurant = placesRestaurant(['primary_cuisine' => PrimaryCuisine::Pizza]);

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn(null);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);
    $restaurant->refresh();

    expect($restaurant->profiled_at)->toBeNull()
        ->and($restaurant->primary_cuisine)->toBe(PrimaryCuisine::Pizza)
        ->and(Cache::has("jev_profiling:{$restaurant->id}"))->toBeFalse();
});

it('does not call Jev for a restaurant that is already profiled', function () {
    $restaurant = placesRestaurant(['profiled_at' => now()]);

    $this->mock(JevService::class)->shouldNotReceive('ask');

    app(ProfilePlacesRestaurant::class)->execute($restaurant);
});

it('profiles an unprofiled favorite restaurant', function () {
    $restaurant = placesRestaurant(['source' => RestaurantSource::Favorite]);

    $this->mock(JevService::class)->shouldReceive('ask')->once()->andReturn(['patio_quality' => answer('choice', 'decent')]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);
    $restaurant->refresh();

    expect($restaurant->profiled_at)->not->toBeNull()
        ->and($restaurant->patio_quality)->toBe(PatioQuality::Decent);
});

it('re-reads the restaurant and skips it when it was profiled after being loaded', function () {
    $restaurant = placesRestaurant(['primary_cuisine' => PrimaryCuisine::Pizza]);
    $stale = Restaurant::find($restaurant->id);

    Restaurant::whereKey($restaurant->id)->update(['profiled_at' => now()]);

    $this->mock(JevService::class)->shouldNotReceive('ask');

    app(ProfilePlacesRestaurant::class)->execute($stale);

    expect($restaurant->refresh()->primary_cuisine)->toBe(PrimaryCuisine::Pizza);
});

it('fills a null primary cuisine and service level in only-empty-fields mode', function () {
    $restaurant = placesRestaurant(['primary_cuisine' => null, 'service_level' => null]);

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'primary_cuisine' => answer('choice', 'thai'),
        'service_level' => answer('choice', 'fine_dining'),
    ]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant, onlyEmptyFields: true);
    $restaurant->refresh();

    expect($restaurant->primary_cuisine)->toBe(PrimaryCuisine::Thai)
        ->and($restaurant->service_level)->toBe(ServiceLevel::FineDining);
});

it('keeps a user-set primary cuisine and service level in only-empty-fields mode', function () {
    $restaurant = placesRestaurant(['primary_cuisine' => PrimaryCuisine::Pizza, 'service_level' => ServiceLevel::Casual]);

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'primary_cuisine' => answer('choice', 'thai'),
        'service_level' => answer('choice', 'fine_dining'),
    ]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant, onlyEmptyFields: true);
    $restaurant->refresh();

    expect($restaurant->primary_cuisine)->toBe(PrimaryCuisine::Pizza)
        ->and($restaurant->service_level)->toBe(ServiceLevel::Casual);
});

it('never changes patio quality or indoor vibe in only-empty-fields mode', function () {
    $restaurant = placesRestaurant(['patio_quality' => PatioQuality::None, 'indoor_vibe_when_cold' => IndoorVibe::Neutral]);

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'patio_quality' => answer('choice', 'destination'),
        'indoor_vibe_when_cold' => answer('choice', 'cozy'),
    ]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant, onlyEmptyFields: true);
    $restaurant->refresh();

    expect($restaurant->patio_quality)->toBe(PatioQuality::None)
        ->and($restaurant->indoor_vibe_when_cold)->toBe(IndoorVibe::Neutral);
});

it('still adds the weather_dependent tag in only-empty-fields mode', function () {
    $restaurant = placesRestaurant(['vibe_tags' => ['lively']]);

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn(['weather_dependent' => answer('noul', 0.8)]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant, onlyEmptyFields: true);

    expect($restaurant->refresh()->vibe_tags)->toBe(['lively', 'weather_dependent']);
});

it('sets profiled_at in only-empty-fields mode', function () {
    $restaurant = placesRestaurant();

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant, onlyEmptyFields: true);

    expect($restaurant->refresh()->profiled_at)->not->toBeNull();
});

it('does not call Jev while another profiling of the same restaurant is in flight', function () {
    $restaurant = placesRestaurant();
    Cache::put("jev_profiling:{$restaurant->id}", true, 60);

    $this->mock(JevService::class)->shouldNotReceive('ask');

    app(ProfilePlacesRestaurant::class)->execute($restaurant);

    expect($restaurant->refresh()->profiled_at)->toBeNull();
});

it("asks one vibe question per vibes config group with that group's tags as choices", function () {
    $restaurant = placesRestaurant();

    $this->mock(JevService::class)
        ->shouldReceive('ask')
        ->once()
        ->withArgs(function (mixed $state, array $questions): bool {
            return $questions['vibe_occasion']['type'] === 'choice'
                && array_keys($questions['vibe_occasion']['criteria']) === config('vibes.occasion')
                && $questions['vibe_occasion']['criteria']['date_night'] === 'Date night'
                && array_keys($questions['vibe_energy']['criteria']) === config('vibes.energy');
        })
        ->andReturn([]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);
});

it('sets one vibe tag per group from confident answers when the restaurant has no vibe tags', function () {
    $restaurant = placesRestaurant(['vibe_tags' => []]);

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'vibe_energy' => answer('choice', 'lively'),
        'vibe_occasion' => answer('choice', 'date_night'),
    ]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);

    expect($restaurant->refresh()->vibe_tags)->toBe(['lively', 'date_night']);
});

it('keeps existing vibe tags and ignores vibe answers when the restaurant already has vibe tags', function () {
    $restaurant = placesRestaurant(['vibe_tags' => ['quiet']]);

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'vibe_energy' => answer('choice', 'lively'),
    ]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);

    expect($restaurant->refresh()->vibe_tags)->toBe(['quiet']);
});

it('treats a restaurant whose only vibe tag is weather_dependent as having no vibe tags', function () {
    $restaurant = placesRestaurant(['vibe_tags' => ['weather_dependent']]);

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'vibe_energy' => answer('choice', 'lively'),
    ]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);

    expect($restaurant->refresh()->vibe_tags)->toBe(['weather_dependent', 'lively']);
});

it("ignores a vibe answer that is not in its group's tags", function () {
    $restaurant = placesRestaurant(['vibe_tags' => []]);

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'vibe_energy' => answer('choice', 'date_night'),
        'vibe_occasion' => answer('choice', 'casual'),
    ]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);

    expect($restaurant->refresh()->vibe_tags)->toBe(['casual']);
});

it('combines suggested vibe tags with the weather_dependent tag without duplicates', function () {
    $restaurant = placesRestaurant(['vibe_tags' => ['weather_dependent']]);

    $this->mock(JevService::class)->shouldReceive('ask')->andReturn([
        'vibe_energy' => answer('choice', 'lively'),
        'weather_dependent' => answer('noul', 0.9),
    ]);

    app(ProfilePlacesRestaurant::class)->execute($restaurant);

    expect($restaurant->refresh()->vibe_tags)->toBe(['weather_dependent', 'lively']);
});
