<?php

use App\Enums\PatioQuality;
use App\Enums\PrimaryCuisine;
use App\Enums\ServiceLevel;
use App\Models\HouseholdState;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\QuizAnswers;
use App\Services\QuizService;
use App\Services\WeatherData;
use App\Services\WeatherService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const SCORING_USER_LAT = 41.58;
const SCORING_USER_LNG = -93.62;

/** avg_duration_minutes that yields exactly $hungerScore for a full_meal answer (ideal 75). */
function durationForHungerScore(int $hungerScore): int
{
    for ($gap = 0; $gap < 5000; $gap++) {
        if (25 - intdiv($gap * 2, 5) === $hungerScore) {
            return 75 + $gap;
        }
    }

    throw new RuntimeException('Unreachable hunger score');
}

/**
 * @param  array<string, mixed>  $attributes
 */
function scoringRestaurant(User $user, array $attributes, int $hungerScore): Restaurant
{
    return Restaurant::factory()->for($user, 'user')->withServiceLevel(ServiceLevel::Casual)->create(array_merge([
        'vibe_tags' => [],
        'patio_quality' => PatioQuality::None,
        'visit_count' => 0,
        'primary_cuisine' => PrimaryCuisine::American,
    ], $attributes, ['avg_duration_minutes' => durationForHungerScore($hungerScore)]));
}

function scoringWeather(float $celsius = 22.2, string $conditions = 'Clear', float $precipitation = 0.0): WeatherData
{
    return new WeatherData($celsius, $conditions, $precipitation, 2.0, CarbonImmutable::now()->addHours(4), 'metric');
}

/**
 * Asserts restaurant A out-scores restaurant B by exactly $diff points: A wins when its
 * margin is +1 over that (B created first), and B wins when it is -1 (A created first).
 *
 * @param  array<string, mixed>  $aAttributes
 * @param  array<string, mixed>  $bAttributes
 */
function expectScoreAdvantage(
    array $aAttributes,
    array $bAttributes,
    int $diff,
    ?QuizAnswers $answers = null,
    ?WeatherData $weather = null,
    ?Closure $prepare = null,
): void {
    $answers ??= new QuizAnswers;
    $hungerB = 10 + min($diff, 0);

    foreach ([1, -1] as $margin) {
        $user = User::factory()->create();
        if ($prepare !== null) {
            $prepare($user);
        }
        $hungerA = $hungerB - $diff + $margin;

        if ($margin === 1) {
            $b = scoringRestaurant($user, $bAttributes, $hungerB);
            $a = scoringRestaurant($user, $aAttributes, $hungerA);
        } else {
            $a = scoringRestaurant($user, $aAttributes, $hungerA);
            $b = scoringRestaurant($user, $bAttributes, $hungerB);
        }

        $winner = app(QuizService::class)->topMatch($user, $answers, $weather);

        expect($winner->id)->toBe($margin === 1 ? $a->id : $b->id);
    }
}

/**
 * @return array{0: float, 1: float}
 */
function scoringOffsetCoordinates(float $miles): array
{
    $north = $miles * 0.6;
    $east = $miles * 0.8;
    $lat = SCORING_USER_LAT + rad2deg($north / 3958.8);
    $lng = SCORING_USER_LNG + rad2deg($east / 3958.8 / cos(deg2rad(SCORING_USER_LAT)));

    return [round($lat, 7), round($lng, 7)];
}

// ---------------------------------------------------------------------------
// Scoring weights
// ---------------------------------------------------------------------------

it('adds exactly 30 points for an energy match', function () {
    expectScoreAdvantage(['vibe_tags' => ['lively']], [], 30, new QuizAnswers(energy: 'lively'));
});

it('scores familiarity=new as +30 for unvisited and -20 for visited places', function () {
    expectScoreAdvantage(['visit_count' => 0], ['visit_count' => 3], 50, new QuizAnswers(familiarity: 'new'));
});

it('adds exactly 30 points for visited places when familiarity=familiar, regardless of visit count', function (int $visits) {
    expectScoreAdvantage(['visit_count' => $visits], ['visit_count' => 0], 30, new QuizAnswers(familiarity: 'familiar'));
})->with([1, 2, 5]);

it('ignores visit count when familiarity=either', function () {
    expectScoreAdvantage(['visit_count' => 4], ['visit_count' => 0], 0, new QuizAnswers(familiarity: 'either'));
});

it('adds the partner preference boost of 25 only on the partner turn', function () {
    $partnerTurn = function (User $user): void {
        $partner = User::factory()->create(['preferred_vibe_tags' => ['cozy']]);
        $user->update(['partner_id' => $partner->id]);
        HouseholdState::recordPick($user->refresh());
    };

    expectScoreAdvantage(['vibe_tags' => ['cozy']], [], 25, null, null, $partnerTurn);
});

it('gives no partner boost when it is not the partner turn', function () {
    $ownTurn = function (User $user): void {
        $partner = User::factory()->create(['preferred_vibe_tags' => ['cozy']]);
        $user->update(['partner_id' => $partner->id]);
        HouseholdState::recordPick($partner->refresh());
    };

    expectScoreAdvantage(['vibe_tags' => ['cozy']], [], 0, null, null, $ownTurn);
});

it('gives no partner boost when there is no recorded pick', function () {
    $noPick = function (User $user): void {
        $partner = User::factory()->create(['preferred_vibe_tags' => ['cozy']]);
        $user->update(['partner_id' => $partner->id]);
    };

    expectScoreAdvantage(['vibe_tags' => ['cozy']], [], 0, null, null, $noPick);
});

it('handles a partner turn when the partner has no preferred vibe tags', function () {
    $partnerTurn = function (User $user): void {
        $partner = User::factory()->create(['preferred_vibe_tags' => null]);
        $user->update(['partner_id' => $partner->id]);
        HouseholdState::recordPick($user->refresh());
    };

    expectScoreAdvantage(['vibe_tags' => ['cozy']], [], 0, null, null, $partnerTurn);
});

// ---------------------------------------------------------------------------
// Hunger
// ---------------------------------------------------------------------------

it('scores durations around the ideal for each hunger answer at the exact 2/5 slope', function (string $hunger, int $ideal) {
    foreach ([[$ideal + 2, $ideal - 3], [$ideal - 2, $ideal + 3]] as [$closeDuration, $farDuration]) {
        $user = User::factory()->create();
        $far = Restaurant::factory()->for($user, 'user')->create(['vibe_tags' => [], 'avg_duration_minutes' => $farDuration]);
        $close = Restaurant::factory()->for($user, 'user')->create(['vibe_tags' => [], 'avg_duration_minutes' => $closeDuration]);

        $winner = app(QuizService::class)->topMatch($user, new QuizAnswers(hunger: $hunger));

        expect($winner->id)->toBe($close->id);
    }
})->with([
    'quick_bite' => ['quick_bite', 45],
    'full_meal' => ['full_meal', 75],
    'feast' => ['feast', 120],
]);

// ---------------------------------------------------------------------------
// Weather
// ---------------------------------------------------------------------------

it('applies the destination, decent and none patio bonuses in ideal weather', function (PatioQuality $patio, int $diff) {
    expectScoreAdvantage(['patio_quality' => $patio], ['patio_quality' => PatioQuality::None], $diff, null, scoringWeather());
})->with([
    'destination' => [PatioQuality::Destination, 40],
    'decent' => [PatioQuality::Decent, 20],
    'none' => [PatioQuality::None, 0],
]);

it('only boosts patios inside the 65-85°F window', function (float $celsius, int $diff) {
    expectScoreAdvantage(
        ['patio_quality' => PatioQuality::Destination],
        ['patio_quality' => PatioQuality::None],
        $diff,
        null,
        scoringWeather($celsius),
    );
})->with([
    'exactly 65F' => [18.333333333333332, 40],
    'exactly 85F' => [29.444444444444443, 40],
    '65.5F' => [(65.5 - 32) * 5 / 9, 40],
    '84.5F' => [(84.5 - 32) * 5 / 9, 40],
    '64.5F' => [(64.5 - 32) * 5 / 9, 0],
    '85.5F' => [(85.5 - 32) * 5 / 9, 0],
    'freezing' => [-5.0, 0],
]);

it('treats any precipitation or rain conditions as bad weather with no patio boost', function (string $conditions, float $precipitation) {
    expectScoreAdvantage(
        ['patio_quality' => PatioQuality::Destination],
        ['patio_quality' => PatioQuality::None],
        0,
        null,
        scoringWeather(22.2, $conditions, $precipitation),
    );
})->with([
    'light precipitation' => ['Clear', 0.5],
    'tiny precipitation' => ['Clear', 0.1],
    'rain with no precipitation' => ['Light Rain', 0.0],
    'rain and precipitation' => ['Rain', 1.0],
]);

it('penalises weather_dependent restaurants by exactly 50 in bad weather', function (string $conditions, float $precipitation) {
    expectScoreAdvantage(
        ['vibe_tags' => ['weather_dependent']],
        [],
        -50,
        null,
        scoringWeather(22.2, $conditions, $precipitation),
    );
})->with([
    'precipitation only' => ['Clear', 0.4],
    'rain only' => ['Light Rain', 0.0],
]);

it('does not penalise weather_dependent restaurants in good weather', function (float $celsius) {
    expectScoreAdvantage(['vibe_tags' => ['weather_dependent']], [], 0, null, scoringWeather($celsius));
})->with([22.2, -5.0]);

// ---------------------------------------------------------------------------
// Weather resolution
// ---------------------------------------------------------------------------

it('fetches weather for the answer coordinates when none is supplied', function () {
    $weather = Mockery::mock(WeatherService::class);
    $weather->shouldReceive('fetch')->once()->with(SCORING_USER_LAT, SCORING_USER_LNG)->andReturn(scoringWeather());
    $service = new QuizService($weather);

    $patio = Restaurant::factory()->for($this->user = User::factory()->create(), 'user')->create([
        'vibe_tags' => [], 'patio_quality' => PatioQuality::Destination, 'avg_duration_minutes' => null,
    ]);
    Restaurant::factory()->for($this->user, 'user')->create([
        'vibe_tags' => [], 'patio_quality' => PatioQuality::None, 'avg_duration_minutes' => 75,
    ]);

    $winner = $service->topMatch($this->user, new QuizAnswers(lat: SCORING_USER_LAT, lng: SCORING_USER_LNG));

    expect($winner->id)->toBe($patio->id);
});

it('does not fetch weather unless both coordinates are present', function (?float $lat, ?float $lng) {
    $weather = Mockery::mock(WeatherService::class);
    $weather->shouldNotReceive('fetch');
    $service = new QuizService($weather);

    $user = User::factory()->create();
    Restaurant::factory()->for($user, 'user')->create();

    expect($service->topMatch($user, new QuizAnswers(lat: $lat, lng: $lng)))->not->toBeNull();
    expect($service->runnerUp($user, new QuizAnswers(lat: $lat, lng: $lng), Restaurant::factory()->for($user, 'user')->create()))->not->toBeNull();
})->with([
    'no coordinates' => [null, null],
    'lat only' => [SCORING_USER_LAT, null],
    'lng only' => [null, SCORING_USER_LNG],
]);

it('uses the supplied weather for the runner-up instead of fetching', function () {
    $user = User::factory()->create();
    $winner = Restaurant::factory()->for($user, 'user')->create(['vibe_tags' => ['lively'], 'avg_duration_minutes' => 75]);
    $patio = Restaurant::factory()->for($user, 'user')->create([
        'vibe_tags' => [], 'patio_quality' => PatioQuality::Destination, 'avg_duration_minutes' => null,
    ]);
    Restaurant::factory()->for($user, 'user')->create([
        'vibe_tags' => [], 'patio_quality' => PatioQuality::None, 'avg_duration_minutes' => 45,
    ]);

    $runnerUp = app(QuizService::class)->runnerUp($user, new QuizAnswers, $winner, scoringWeather());

    expect($runnerUp->id)->toBe($patio->id);
});

// ---------------------------------------------------------------------------
// Hard filters
// ---------------------------------------------------------------------------

it('applies the cuisine filter even when another cuisine scores higher', function () {
    $user = User::factory()->create();
    $italian = Restaurant::factory()->for($user, 'user')->create([
        'vibe_tags' => [], 'primary_cuisine' => PrimaryCuisine::Italian, 'avg_duration_minutes' => 300,
    ]);
    Restaurant::factory()->for($user, 'user')->create([
        'vibe_tags' => ['lively'], 'primary_cuisine' => PrimaryCuisine::Mexican, 'avg_duration_minutes' => 75,
    ]);

    $winner = app(QuizService::class)->topMatch($user, new QuizAnswers(energy: 'lively', cuisine: 'italian'));

    expect($winner->id)->toBe($italian->id);
});

it('counts cuisine exclusions including restaurants without a primary cuisine', function () {
    $user = User::factory()->create();
    Restaurant::factory()->for($user, 'user')->create(['primary_cuisine' => PrimaryCuisine::Italian]);
    Restaurant::factory()->for($user, 'user')->create(['primary_cuisine' => PrimaryCuisine::Mexican]);
    Restaurant::factory()->for($user, 'user')->create(['primary_cuisine' => null]);

    $service = app(QuizService::class);

    expect($service->filterExclusionCounts($user, new QuizAnswers(cuisine: 'italian'))['cuisine'])->toBe(2)
        ->and($service->filterExclusionCounts($user, new QuizAnswers(cuisine: null))['cuisine'])->toBe(0);
});

it('counts service level exclusions for every service level answer', function (string $answer, int $excluded) {
    $user = User::factory()->create();
    foreach (ServiceLevel::cases() as $level) {
        Restaurant::factory()->for($user, 'user')->withServiceLevel($level)->create();
    }

    $counts = app(QuizService::class)->filterExclusionCounts($user, new QuizAnswers(serviceLevel: $answer));

    expect($counts['serviceLevel'])->toBe($excluded);
})->with([
    'quick_easy' => ['quick_easy', 3],
    'casual_sit_down' => ['casual_sit_down', 4],
    'nicer_night_out' => ['nicer_night_out', 4],
    'special_occasion' => ['special_occasion', 4],
    'no_preference' => ['no_preference', 0],
]);

it('keeps both fast_food and fast_casual for quick_easy', function () {
    $user = User::factory()->create();
    foreach ([ServiceLevel::FastFood, ServiceLevel::FastCasual] as $level) {
        $restaurant = Restaurant::factory()->for($user, 'user')->withServiceLevel($level)->create();
        Restaurant::query()->whereKeyNot($restaurant->id)->where('owner_user_id', $user->id)->update(['visit_count' => 0]);
        $winner = app(QuizService::class)->topMatch($user, new QuizAnswers(serviceLevel: 'quick_easy'));
        expect($winner)->not->toBeNull();
        Restaurant::query()->delete();
    }
});

it('applies each distance bucket edge precisely', function (string $bucket, float $miles, bool $included) {
    $user = User::factory()->create();
    [$lat, $lng] = scoringOffsetCoordinates($miles);
    Restaurant::factory()->for($user, 'user')->create(['lat' => $lat, 'lng' => $lng]);

    $result = app(QuizService::class)->topMatch(
        $user,
        new QuizAnswers(distance: $bucket, lat: SCORING_USER_LAT, lng: SCORING_USER_LNG),
        scoringWeather(),
    );

    expect($result !== null)->toBe($included);
})->with([
    'under_2 close' => ['under_2_miles', 0.5, true],
    'under_2 just inside' => ['under_2_miles', 1.9998, true],
    'under_2 just outside' => ['under_2_miles', 2.0002, false],
    '2_to_5 just inside low' => ['2_to_5_miles', 2.0002, true],
    '2_to_5 just outside low' => ['2_to_5_miles', 1.9998, false],
    '2_to_5 just inside high' => ['2_to_5_miles', 4.95, true],
    '2_to_5 just outside high' => ['2_to_5_miles', 5.05, false],
    '5_to_15 just inside low' => ['5_to_15_miles', 5.05, true],
    '5_to_15 just outside low' => ['5_to_15_miles', 4.95, false],
    '5_to_15 just inside high' => ['5_to_15_miles', 14.95, true],
    '5_to_15 just outside high' => ['5_to_15_miles', 15.05, false],
]);

it('excludes restaurants without coordinates when a distance bucket applies', function () {
    $user = User::factory()->create();
    Restaurant::factory()->for($user, 'user')->create(['lat' => null, 'lng' => SCORING_USER_LNG]);

    $result = app(QuizService::class)->topMatch(
        $user,
        new QuizAnswers(distance: 'under_2_miles', lat: 0.0, lng: SCORING_USER_LNG),
        scoringWeather(),
    );

    expect($result)->toBeNull();
});
