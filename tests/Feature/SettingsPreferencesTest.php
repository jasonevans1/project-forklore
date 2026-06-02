<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
});

// ---------------------------------------------------------------------------
// Route access
// ---------------------------------------------------------------------------

it('is accessible to authenticated users', function () {
    $this->actingAs($this->user)
        ->get(route('preferences.edit'))
        ->assertOk();
});

it('redirects guests to the login page', function () {
    $this->get(route('preferences.edit'))
        ->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Page content
// ---------------------------------------------------------------------------

it('shows vibe tag options from the config', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.preferences')
        ->assertSee('lively')
        ->assertSee('cozy')
        ->assertSee('casual');
});

it('pre-selects the user current preferred_vibe_tags', function () {
    $this->user->update(['preferred_vibe_tags' => ['cozy', 'lively']]);

    Livewire::actingAs($this->user)
        ->test('pages::settings.preferences')
        ->assertSet('preferredVibeTags', ['cozy', 'lively']);
});

it('defaults to an empty array when the user has no preferred tags', function () {
    $this->user->update(['preferred_vibe_tags' => null]);

    Livewire::actingAs($this->user)
        ->test('pages::settings.preferences')
        ->assertSet('preferredVibeTags', []);
});

// ---------------------------------------------------------------------------
// Saving
// ---------------------------------------------------------------------------

it('persists the selected vibe tags to the database', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.preferences')
        ->set('preferredVibeTags', ['cozy', 'casual'])
        ->call('save')
        ->assertHasNoErrors();

    expect($this->user->fresh()->preferred_vibe_tags)->toBe(['cozy', 'casual']);
});

it('shows a success toast after saving', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.preferences')
        ->set('preferredVibeTags', ['cozy'])
        ->call('save')
        ->assertHasNoErrors();
});

it('allows saving an empty array (no preferences)', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.preferences')
        ->set('preferredVibeTags', [])
        ->call('save')
        ->assertHasNoErrors();

    expect($this->user->fresh()->preferred_vibe_tags)->toBe([]);
});

it('rejects a tag not in the vibes taxonomy', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.preferences')
        ->set('preferredVibeTags', ['not-a-real-tag'])
        ->call('save')
        ->assertHasErrors(['preferredVibeTags.0']);
});
