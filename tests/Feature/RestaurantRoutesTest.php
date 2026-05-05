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

it('returns 200 for authenticated users on /restaurants', function () {
    $this->actingAs(User::factory()->create())
        ->get('/restaurants')
        ->assertOk();
});

it('returns 200 for authenticated users on /restaurants/create', function () {
    $this->actingAs(User::factory()->create())
        ->get('/restaurants/create')
        ->assertOk();
});
