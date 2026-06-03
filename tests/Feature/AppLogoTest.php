<?php

use App\Models\User;

it('displays Forklore as the sidebar brand name', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Forklore');
});

it('does not display Laravel Starter Kit anywhere in the authenticated layout', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertDontSee('Laravel Starter Kit');
});
