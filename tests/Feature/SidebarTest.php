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
