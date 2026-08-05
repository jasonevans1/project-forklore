<?php

use App\Models\User;

it('does not show a Repository link in the sidebar', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    $response->assertDontSee('github.com/laravel/livewire-starter-kit');
});

it('does not show a Documentation link in the sidebar', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    $response->assertDontSee('laravel.com/docs/starter-kits');
});

it('renders all six sidebar navigation items with their routes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    $response->assertSee('Dashboard');
    $response->assertSee(route('dashboard'), false);

    $response->assertSee('Quick Pick');
    $response->assertSee(route('pick'), false);

    $response->assertSee('Tonight');
    $response->assertSee(route('tonight'), false);

    $response->assertSee('Quiz');
    $response->assertSee(route('quiz'), false);

    $response->assertSee('Tournament');
    $response->assertSee(route('tournament'), false);

    $response->assertSee('History');
    $response->assertSee(route('history'), false);
});
