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
