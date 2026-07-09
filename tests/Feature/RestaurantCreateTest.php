<?php

use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Enums\PrimaryCuisine;
use App\Enums\RestaurantSource;
use App\Enums\ServiceLevel;
use App\Enums\ServiceOption;
use App\Models\Restaurant;
use App\Models\User;
use Livewire\Livewire;

it('requires name to save a restaurant', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', '')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->call('save')
        ->assertHasErrors(['name']);
});

it('requires non-empty cuisine_tags to save a restaurant', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Test Place')
        ->set('cuisine_tags', '')
        ->set('vibe_tags', ['casual'])
        ->call('save')
        ->assertHasErrors(['cuisine_tags']);
});

it('requires non-empty vibe_tags to save a restaurant', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Test Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', [])
        ->call('save')
        ->assertHasErrors(['vibe_tags']);
});

it('saves a restaurant with valid data and redirects to the index', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Test Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('restaurants.index'));

    $this->assertDatabaseHas('restaurants', ['name' => 'Test Place']);
});

it('sets the owner to the authenticated user on save', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Owned Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->call('save');

    $this->assertDatabaseHas('restaurants', [
        'name' => 'Owned Place',
        'owner_user_id' => $user->id,
    ]);
});

it('sets source to favorite on save', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Fav Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->call('save');

    $this->assertDatabaseHas('restaurants', [
        'name' => 'Fav Place',
        'source' => RestaurantSource::Favorite->value,
    ]);
});

it('splits comma-separated cuisine_tags into an array on save', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Tag Split Place')
        ->set('cuisine_tags', 'Italian, Pizza')
        ->set('vibe_tags', ['casual'])
        ->call('save');

    $restaurant = Restaurant::where('name', 'Tag Split Place')->first();

    expect($restaurant->cuisine_tags)->toBe(['Italian', 'Pizza']);
});

it('surfaces validation errors without redirecting', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', '')
        ->set('cuisine_tags', '')
        ->set('vibe_tags', [])
        ->call('save')
        ->assertHasErrors(['name', 'cuisine_tags', 'vibe_tags'])
        ->assertNoRedirect();
});

it('saves optional fields when provided', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Optional Fields Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->set('address', '123 Main St')
        ->set('price_level', 2)
        ->set('avg_duration_minutes', 90)
        ->set('patio_quality', PatioQuality::Decent->value)
        ->set('indoor_vibe_when_cold', IndoorVibe::Cozy->value)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('restaurants', [
        'name' => 'Optional Fields Place',
        'address' => '123 Main St',
        'price_level' => 2,
        'avg_duration_minutes' => 90,
        'patio_quality' => PatioQuality::Decent->value,
        'indoor_vibe_when_cold' => IndoorVibe::Cozy->value,
    ]);
});

it('saves the vibe_tags array directly to the database without splitting', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Direct Array Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual', 'cozy'])
        ->call('save');

    $restaurant = Restaurant::where('name', 'Direct Array Place')->first();

    expect($restaurant->vibe_tags)->toBe(['casual', 'cozy']);
});

it('rejects an empty vibe_tags array with a validation error', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Empty Vibe Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', [])
        ->call('save')
        ->assertHasErrors(['vibe_tags']);
});

it('rejects vibe_tags not in the taxonomy with a validation error', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Bad Vibe Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['not_a_real_vibe'])
        ->call('save')
        ->assertHasErrors(['vibe_tags.*']);
});

it('saves a restaurant with valid vibe tags selected from the taxonomy', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Taxonomy Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual', 'lively'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('restaurants.index'));

    $restaurant = Restaurant::where('name', 'Taxonomy Place')->first();

    expect($restaurant->vibe_tags)->toBe(['casual', 'lively']);
});

it('saves service_level when creating a restaurant', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Service Level Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->set('service_level', ServiceLevel::Casual->value)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('restaurants', [
        'name' => 'Service Level Place',
        'service_level' => ServiceLevel::Casual->value,
    ]);
});

it('saves service_options when creating a restaurant', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Service Options Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->set('service_options', [ServiceOption::DineIn->value, ServiceOption::Takeout->value])
        ->call('save')
        ->assertHasNoErrors();

    $restaurant = Restaurant::where('name', 'Service Options Place')->first();

    expect($restaurant->service_options)->toEqualCanonicalizing([
        ServiceOption::DineIn->value,
        ServiceOption::Takeout->value,
    ]);
});

it('saves primary_cuisine when creating a restaurant', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Primary Cuisine Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->set('primary_cuisine', PrimaryCuisine::Italian->value)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('restaurants', [
        'name' => 'Primary Cuisine Place',
        'primary_cuisine' => PrimaryCuisine::Italian->value,
    ]);
});

it('creates a restaurant with the classification fields omitted', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Omitted Classification Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->call('save')
        ->assertHasNoErrors();

    $restaurant = Restaurant::where('name', 'Omitted Classification Place')->first();

    expect($restaurant->service_level)->toBeNull()
        ->and($restaurant->service_options)->toBeNull()
        ->and($restaurant->primary_cuisine)->toBeNull();
});

it('creates a restaurant when the classification selects are explicitly blank', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Blank Classification Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->set('service_level', '')
        ->set('primary_cuisine', '')
        ->set('service_options', [])
        ->call('save')
        ->assertHasNoErrors();

    $restaurant = Restaurant::where('name', 'Blank Classification Place')->first();

    expect($restaurant->service_level)->toBeNull()
        ->and($restaurant->service_options)->toBeNull()
        ->and($restaurant->primary_cuisine)->toBeNull();
});

it('rejects an invalid service_level value', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Invalid Service Level Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->set('service_level', 'not_real')
        ->call('save')
        ->assertHasErrors(['service_level']);
});

it('rejects an invalid service option value in the array', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Invalid Service Option Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->set('service_options', ['not_a_real_option'])
        ->call('save')
        ->assertHasErrors(['service_options.*']);
});

it('rejects an invalid primary_cuisine value', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('name', 'Invalid Primary Cuisine Place')
        ->set('cuisine_tags', 'Italian')
        ->set('vibe_tags', ['casual'])
        ->set('primary_cuisine', 'not_real')
        ->call('save')
        ->assertHasErrors(['primary_cuisine']);
});

it('does not expose the source field as user input', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    $this->get(route('restaurants.create'))
        ->assertOk()
        ->assertDontSee('name="source"', escape: false);
});
