<?php

use App\Models\User;
use Livewire\Livewire;

it('renders the restaurant name field on the create page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->assertSee(__('Name'));
});

it('renders the cuisine tags field on the create page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->assertSee(__('Cuisine tags'));
});

it('renders the patio quality select on the create page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->assertSee(__('Patio quality'));
});

it('renders the indoor vibe select on the create page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::restaurants.create')
        ->assertSee(__('Indoor vibe when cold'));
});

