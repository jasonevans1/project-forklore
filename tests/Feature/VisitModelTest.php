<?php

use App\Enums\ModeUsed;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// --- Partner relationship ---

it('adds partner_id column to the users table', function () {
    $columns = Schema::getColumnListing('users');

    expect($columns)->toContain('partner_id');
});

it('allows partner_id to be null', function () {
    $user = User::factory()->create();

    expect($user->partner_id)->toBeNull();
});

it('returns the partner user when partner_id is set', function () {
    $partner = User::factory()->create();
    $user = User::factory()->create(['partner_id' => $partner->id]);

    expect($user->partner->id)->toBe($partner->id);
});

it('returns null when the user has no partner', function () {
    $user = User::factory()->create();

    expect($user->partner)->toBeNull();
});

it('sets partner_id to null when the partner user is deleted', function () {
    $partner = User::factory()->create();
    $user = User::factory()->create(['partner_id' => $partner->id]);

    $partner->delete();

    expect($user->fresh()->partner_id)->toBeNull();
});

// --- Visit model structure ---

it('creates the visits table with all required columns', function () {
    $columns = Schema::getColumnListing('visits');

    expect($columns)->toContain('id')
        ->toContain('user_id')
        ->toContain('restaurant_id')
        ->toContain('visited_at')
        ->toContain('mode_used')
        ->toContain('created_at')
        ->toContain('updated_at');
});

it('stores mode_used as a string in the database', function () {
    $visit = Visit::factory()->create(['mode_used' => ModeUsed::Quiz]);

    $raw = DB::table('visits')->where('id', $visit->id)->value('mode_used');

    expect($raw)->toBe('quiz');
});

it('casts mode_used to the ModeUsed enum', function () {
    $visit = Visit::factory()->create(['mode_used' => ModeUsed::Tournament]);

    expect($visit->fresh()->mode_used)->toBeInstanceOf(ModeUsed::class)
        ->and($visit->fresh()->mode_used)->toBe(ModeUsed::Tournament);
});

it('casts visited_at to a datetime', function () {
    $visit = Visit::factory()->create(['visited_at' => now()]);

    expect($visit->fresh()->visited_at)->toBeInstanceOf(CarbonInterface::class);
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $visit = Visit::factory()->create(['user_id' => $user->id]);

    expect($visit->user->id)->toBe($user->id);
});

it('belongs to a restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $visit = Visit::factory()->create(['restaurant_id' => $restaurant->id]);

    expect($visit->restaurant->id)->toBe($restaurant->id);
});

// --- HasMany relationships ---

it('a user has many visits', function () {
    $user = User::factory()->create();
    Visit::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->visits)->toHaveCount(3);
});

it('a restaurant has many visits', function () {
    $restaurant = Restaurant::factory()->create();
    Visit::factory()->count(2)->create(['restaurant_id' => $restaurant->id]);

    expect($restaurant->visits)->toHaveCount(2);
});

// --- Integration ---

it('creates two users as partners and logs a visit for each', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $userA->update(['partner_id' => $userB->id]);
    $userB->update(['partner_id' => $userA->id]);

    $restaurant = Restaurant::factory()->create();

    Visit::factory()->create(['user_id' => $userA->id, 'restaurant_id' => $restaurant->id, 'mode_used' => ModeUsed::QuickPick]);
    Visit::factory()->create(['user_id' => $userB->id, 'restaurant_id' => $restaurant->id, 'mode_used' => ModeUsed::Quiz]);

    expect($userA->fresh()->partner->id)->toBe($userB->id)
        ->and($userB->fresh()->partner->id)->toBe($userA->id)
        ->and($userA->visits)->toHaveCount(1)
        ->and($userB->visits)->toHaveCount(1)
        ->and($userA->visits->first()->user_id)->toBe($userA->id)
        ->and($userB->visits->first()->user_id)->toBe($userB->id);
});
