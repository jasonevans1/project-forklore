<?php

use App\Enums\PrimaryCuisine;
use App\Enums\ServiceLevel;
use App\Enums\ServiceOption;
use App\Models\User;
use Livewire\Livewire;

it('renders the restaurant name field on the create page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('activeTab', 'manual')
        ->assertSee(__('Name'));
});

it('renders the cuisine tags field on the create page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('activeTab', 'manual')
        ->assertSee(__('Cuisine tags'));
});

it('renders the patio quality select on the create page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('activeTab', 'manual')
        ->assertSee(__('Patio quality'));
});

it('renders the indoor vibe select on the create page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->set('activeTab', 'manual')
        ->assertSee(__('Indoor vibe when cold'));
});

it('renders friendly service level labels in the create form', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    $component = Livewire::test('pages::restaurants.create')
        ->set('activeTab', 'manual');

    foreach (ServiceLevel::cases() as $case) {
        $component->assertSee($case->label());
    }
});

it('renders friendly primary cuisine labels in the create form', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    $component = Livewire::test('pages::restaurants.create')
        ->set('activeTab', 'manual');

    foreach (PrimaryCuisine::cases() as $case) {
        $component->assertSee($case->label());
    }
});

it('renders a checkbox per service option with friendly labels', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    $component = Livewire::test('pages::restaurants.create')
        ->set('activeTab', 'manual');

    foreach (ServiceOption::cases() as $case) {
        $component->assertSee($case->label());
    }
});
