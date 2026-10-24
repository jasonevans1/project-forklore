<?php

use App\Concerns\ComputesRestaurantPresentation;
use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Models\Restaurant;
use App\Services\WeatherData;
use App\Services\WeatherService;
use Carbon\CarbonImmutable;

function presenter(?float $lat, ?float $lng): object
{
    return new class($lat, $lng)
    {
        use ComputesRestaurantPresentation;

        public function __construct(public ?float $lat, public ?float $lng) {}

        public function tagline(Restaurant $restaurant): string
        {
            return $this->resolveTagline($restaurant);
        }

        public function distance(Restaurant $restaurant): ?string
        {
            return $this->resolveDistanceLabel($restaurant);
        }
    };
}

function fakeWeather(float $celsius, string $conditions = 'Clear', float $precipitation = 0.0): void
{
    $weather = new WeatherData($celsius, $conditions, $precipitation, 1.0, CarbonImmutable::now(), 'metric');
    test()->mock(WeatherService::class)->allows('fetch')->andReturn($weather);
}

function presentedRestaurant(PatioQuality $patio = PatioQuality::None, IndoorVibe $vibe = IndoorVibe::Neutral, array $attrs = []): Restaurant
{
    return Restaurant::factory()->make($attrs + ['patio_quality' => $patio, 'indoor_vibe_when_cold' => $vibe]);
}

it('skips the weather lookup unless both coordinates are present', function (?float $lat, ?float $lng) {
    test()->mock(WeatherService::class)->shouldNotReceive('fetch');

    expect(presenter($lat, $lng)->tagline(presentedRestaurant()))->toBe('Your pick for tonight ✨');
})->with([[41.5, null], [null, -93.6], [null, null]]);

it('falls back to patio taglines without weather', function () {
    test()->mock(WeatherService::class)->allows('fetch')->andReturnNull();
    $p = presenter(41.5, -93.6);

    expect($p->tagline(presentedRestaurant(PatioQuality::Destination)))->toBe('Amazing patio spot 🌞')
        ->and($p->tagline(presentedRestaurant(PatioQuality::Decent)))->toBe('Great patio vibes 🌤️')
        ->and($p->tagline(presentedRestaurant(PatioQuality::None)))->toBe('Your pick for tonight ✨');
});

it('shows perfect patio weather for mild dry weather', function (float $celsius) {
    fakeWeather($celsius);

    expect(presenter(41.5, -93.6)->tagline(presentedRestaurant(PatioQuality::Decent)))
        ->toBe('Perfect patio weather tonight 🌿');
})->with([20.0, 25.0, 18.4, 29.3]);

it('does not show patio weather outside the mild range', function (float $celsius) {
    fakeWeather($celsius);

    expect(presenter(41.5, -93.6)->tagline(presentedRestaurant(PatioQuality::Decent)))
        ->toBe('Great patio vibes 🌤️');
})->with([18.2, 29.6, 10.0, 35.0]);

it('does not show patio weather for restaurants without a patio', function () {
    fakeWeather(22.0);

    expect(presenter(41.5, -93.6)->tagline(presentedRestaurant(PatioQuality::None)))
        ->toBe('Your pick for tonight ✨');
});

it('treats precipitation, or rain in the conditions, as rain', function (string $conditions, float $precipitation) {
    fakeWeather(22.0, $conditions, $precipitation);

    $p = presenter(41.5, -93.6);

    expect($p->tagline(presentedRestaurant(PatioQuality::Decent, IndoorVibe::Cozy)))
        ->toBe('Cozy escape on a rainy night ☔')
        ->and($p->tagline(presentedRestaurant(PatioQuality::Decent)))
        ->toBe('Great patio vibes 🌤️');
})->with([
    'precipitation only' => ['Clear', 0.1],
    'capitalised conditions' => ['Rain', 0.0],
    'light rain' => ['light rain', 0.0],
]);

it('is not rainy with dry clear weather', function () {
    fakeWeather(22.0, 'Clouds', 0.0);

    expect(presenter(41.5, -93.6)->tagline(presentedRestaurant(PatioQuality::None, IndoorVibe::Cozy)))
        ->toBe('Your pick for tonight ✨');
});

it('requires a cozy restaurant for the rainy tagline', function () {
    fakeWeather(22.0, 'Rain', 1.0);

    expect(presenter(41.5, -93.6)->tagline(presentedRestaurant(PatioQuality::None, IndoorVibe::Neutral)))
        ->toBe('Your pick for tonight ✨');
});

it('shows the cold tagline for cozy restaurants below 40F', function () {
    fakeWeather(0.0);

    expect(presenter(41.5, -93.6)->tagline(presentedRestaurant(PatioQuality::None, IndoorVibe::Cozy)))
        ->toBe('Warm and cozy on a cold night ❄️');
});

it('does not show the cold tagline for non-cozy restaurants or warm weather', function () {
    $p = presenter(41.5, -93.6);

    fakeWeather(0.0);
    expect($p->tagline(presentedRestaurant(PatioQuality::None, IndoorVibe::Neutral)))->toBe('Your pick for tonight ✨');

    fakeWeather(5.0);
    expect($p->tagline(presentedRestaurant(PatioQuality::None, IndoorVibe::Cozy)))->toBe('Your pick for tonight ✨');
});

it('converts celsius to fahrenheit for the cold threshold', function () {
    $cozy = presentedRestaurant(PatioQuality::None, IndoorVibe::Cozy);

    fakeWeather(4.0);
    expect(presenter(41.5, -93.6)->tagline($cozy))->toBe('Warm and cozy on a cold night ❄️');

    fakeWeather(4.8);
    expect(presenter(41.5, -93.6)->tagline($cozy))->toBe('Your pick for tonight ✨');
});

it('formats the distance in miles using the haversine formula', function () {
    $restaurant = presentedRestaurant(attrs: ['lat' => 41.8781, 'lng' => -87.6298]);

    expect(presenter(41.5868, -93.625)->distance($restaurant))->toBe('309.7 mi');
});

it('is symmetric and uses the correct axes for distance', function () {
    expect(presenter(10.0, 0.0)->distance(presentedRestaurant(attrs: ['lat' => 0.0, 'lng' => 0.0])))->toBe('690.9 mi')
        ->and(presenter(0.0, 10.0)->distance(presentedRestaurant(attrs: ['lat' => 0.0, 'lng' => 0.0])))->toBe('690.9 mi')
        ->and(presenter(45.0, 10.0)->distance(presentedRestaurant(attrs: ['lat' => 45.0, 'lng' => 0.0])))->toBe('488.3 mi');
});

it('reports zero distance for identical coordinates', function () {
    expect(presenter(41.5, -93.6)->distance(presentedRestaurant(attrs: ['lat' => 41.5, 'lng' => -93.6])))->toBe('0.0 mi');
});

it('returns no distance label when any coordinate is missing', function (?float $userLat, ?float $userLng, ?float $lat, ?float $lng) {
    $restaurant = presentedRestaurant(attrs: ['lat' => $lat, 'lng' => $lng]);

    expect(presenter($userLat, $userLng)->distance($restaurant))->toBeNull();
})->with([
    'user lat missing' => [null, -93.6, 41.5, -93.6],
    'user lng missing' => [41.5, null, 41.5, -93.6],
    'restaurant lat missing' => [41.5, -93.6, null, -93.6],
    'restaurant lng missing' => [41.5, -93.6, 41.5, null],
]);
