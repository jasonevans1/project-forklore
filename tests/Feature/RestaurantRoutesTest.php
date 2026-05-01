<?php

use App\Models\User;

it('redirects guests to login when visiting /restaurants', function () {
    $this->get('/restaurants')
        ->assertRedirect('/login');
});

it('redirects guests to login when visiting /restaurants/create', function () {
    $this->get('/restaurants/create')
        ->assertRedirect('/login');
});

it('returns 200 for authenticated verified users on /restaurants', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/restaurants')
        ->assertOk();
});

it('returns 200 for authenticated verified users on /restaurants/create', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/restaurants/create')
        ->assertOk();
});

it('redirects unverified users from /restaurants', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $this->actingAs($user)
        ->get('/restaurants')
        ->assertRedirect();
});
