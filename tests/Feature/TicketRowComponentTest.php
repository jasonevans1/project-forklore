<?php

it('renders the restaurant name', function () {
    $this->blade('<x-ticket-row name="Noodle Palace" />')
        ->assertSee('Noodle Palace');
});

it('renders a trailing badge when a badge label is passed', function () {
    $this->blade('<x-ticket-row name="Noodle Palace" badge-label="Quick Pick" />')
        ->assertSee('data-flux-badge', false)
        ->assertSee('Quick Pick');
});

it('omits the badge when no badge label is passed', function () {
    $this->blade('<x-ticket-row name="Noodle Palace" />')
        ->assertDontSee('data-flux-badge', false);
});

it('renders slot content as the metadata line', function () {
    $this->blade('<x-ticket-row name="Noodle Palace">Italian, Pizza</x-ticket-row>')
        ->assertSee('Italian, Pizza');
});

it('renders without error when no metadata slot content is passed', function () {
    $this->blade('<x-ticket-row name="Noodle Palace" />')
        ->assertSee('Noodle Palace');
});

it('renders the root as a link with wire navigate when an href is passed', function () {
    $this->blade('<x-ticket-row name="Noodle Palace" href="/restaurants/1" />')
        ->assertSeeInOrder(['<a', 'href="/restaurants/1"', 'wire:navigate'], false);
});

it('renders the root as a non-link element when no href is passed', function () {
    $this->blade('<x-ticket-row name="Noodle Palace" />')
        ->assertDontSee('<a ', false)
        ->assertDontSee('wire:navigate', false);
});

it('renders the trailing slot instead of the badge when both are provided', function () {
    $this->blade(
        '<x-ticket-row name="Noodle Palace" badge-label="Quick Pick"><x-slot:trailing>Trailing Content</x-slot:trailing></x-ticket-row>'
    )
        ->assertSee('Trailing Content')
        ->assertDontSee('data-flux-badge', false);
});
