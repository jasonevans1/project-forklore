<?php

use App\Models\User;

it('shows the Forklore app name on the landing page', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Forklore');
});

it('shows a login link on the landing page for guests', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Log in');
});

it('shows a register link on the landing page when registration is enabled', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Register');
});

it('shows a dashboard link on the landing page for authenticated users', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Dashboard');
});

it('does not mention Laravel on the landing page', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertDontSee('Laravel');
});

it('lists all four decision modes with their descriptions', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Quick Pick');
    $response->assertSee('One tap. Weather-aware, time-aware, skips recently visited places.');
    $response->assertSee('Something Happening Tonight');
    $response->assertSee('Filters to restaurants with live events starting soon.');
    $response->assertSee('Guided Quiz');
    $response->assertSee('5 questions scored against favorites, returns the best match.');
    $response->assertSee('Tournament');
    $response->assertSee('Head-to-head bracket of 4 or 8 favorites until one wins.');
});

it('shows the footer tagline', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Every decision ends with one restaurant.');
});
