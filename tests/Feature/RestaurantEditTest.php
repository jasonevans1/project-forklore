<?php

use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Enums\RestaurantSource;
use App\Models\Restaurant;
use App\Models\User;
use Livewire\Livewire;

it('pre-populates the name field with the existing restaurant name', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'name' => 'Bella Italia',
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant])
        ->assertSet('name', 'Bella Italia');
});

it('pre-populates the address field with the existing restaurant address', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'address' => '123 Main St, Des Moines, IA',
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant])
        ->assertSet('address', '123 Main St, Des Moines, IA');
});

it('pre-populates the cuisine tags as a comma-separated string', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'cuisine_tags' => ['Italian', 'Pizza'],
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant])
        ->assertSet('cuisine_tags', 'Italian, Pizza');
});

it('pre-populates the vibe tags as a comma-separated string', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'vibe_tags' => ['romantic', 'casual'],
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant])
        ->assertSet('vibe_tags', 'romantic, casual');
});

it('pre-populates the price level with the existing value', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'price_level' => 3,
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant])
        ->assertSet('price_level', 3);
});

it('pre-populates patio quality with the existing value', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'patio_quality' => PatioQuality::Decent,
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant])
        ->assertSet('patio_quality', PatioQuality::Decent->value);
});

it('pre-populates indoor vibe with the existing value', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'indoor_vibe_when_cold' => IndoorVibe::Cozy,
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant])
        ->assertSet('indoor_vibe_when_cold', IndoorVibe::Cozy->value);
});

it('pre-populates the average duration with the existing value', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'avg_duration_minutes' => 90,
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant])
        ->assertSet('avg_duration_minutes', 90);
});

it('saves updated fields and redirects to the detail page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'name' => 'Old Name',
        'cuisine_tags' => ['Mexican'],
        'vibe_tags' => ['casual'],
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant])
        ->set('name', 'New Name')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('restaurants.show', $restaurant));
});

it('persists updated cuisine_tags as an array after splitting on comma', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'cuisine_tags' => ['Mexican'],
        'vibe_tags' => ['casual'],
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant])
        ->set('cuisine_tags', 'Italian, Pizza')
        ->call('save');

    expect($restaurant->fresh()->cuisine_tags)->toBe(['Italian', 'Pizza']);
});

it('persists updated vibe_tags as an array after splitting on comma', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'cuisine_tags' => ['Italian'],
        'vibe_tags' => ['casual'],
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant])
        ->set('vibe_tags', 'romantic, lively')
        ->call('save');

    expect($restaurant->fresh()->vibe_tags)->toBe(['romantic', 'lively']);
});

it('shows validation errors without redirecting when name is empty', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'cuisine_tags' => ['Italian'],
        'vibe_tags' => ['casual'],
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name'])
        ->assertNoRedirect();
});

it('shows validation errors without redirecting when cuisine tags are empty', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'cuisine_tags' => ['Italian'],
        'vibe_tags' => ['casual'],
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant])
        ->set('cuisine_tags', '')
        ->call('save')
        ->assertHasErrors(['cuisine_tags'])
        ->assertNoRedirect();
});

it('shows validation errors without redirecting when vibe tags become empty after trimming', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'cuisine_tags' => ['Italian'],
        'vibe_tags' => ['casual'],
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant])
        ->set('vibe_tags', ',  ,  ')
        ->call('save')
        ->assertHasErrors(['vibe_tags'])
        ->assertNoRedirect();
});

it('forbids a non-owner from loading the edit page', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $otherUser = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs($otherUser)
        ->get(route('restaurants.edit', $restaurant))
        ->assertForbidden();
});

it('forbids a non-owner from invoking save via the edit page', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $attacker = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Original Name',
        'cuisine_tags' => ['Italian'],
        'vibe_tags' => ['casual'],
    ]);

    // Owner mounts the component, gets a valid Livewire session
    $component = Livewire::actingAs($owner)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant]);

    // Ownership transfers to another user mid-session (simulates tampered Livewire snapshot)
    $restaurant->update(['owner_user_id' => $attacker->id]);

    // save() re-authorizes against current DB; returns 403 because original owner is no longer the owner
    $component->set('name', 'Hacked Name')
        ->call('save')
        ->assertForbidden();

    // Data must remain unchanged
    $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id, 'name' => 'Original Name']);
});

it('does not overwrite source or visit count on save', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $restaurant = Restaurant::factory()->create([
        'owner_user_id' => $user->id,
        'source' => RestaurantSource::Favorite,
        'visit_count' => 5,
        'cuisine_tags' => ['Italian'],
        'vibe_tags' => ['casual'],
    ]);

    Livewire::actingAs($user)
        ->test('pages::restaurants.edit', ['restaurant' => $restaurant])
        ->set('name', 'Updated Name')
        ->call('save');

    $fresh = $restaurant->fresh();
    expect($fresh->source)->toBe(RestaurantSource::Favorite)
        ->and($fresh->visit_count)->toBe(5);
});
