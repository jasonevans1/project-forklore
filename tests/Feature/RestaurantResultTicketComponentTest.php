<?php

use App\Models\Restaurant;

it('renders the restaurant name', function () {
    $restaurant = Restaurant::factory()->make(['name' => 'Noodle Palace']);

    $this->blade(
        '<x-restaurant-result-ticket :restaurant="$restaurant" badge-label="Quick Pick" />',
        ['restaurant' => $restaurant],
    )->assertSee('Noodle Palace');
});

it('renders the badge label passed in', function () {
    $restaurant = Restaurant::factory()->make();

    $this->blade(
        '<x-restaurant-result-ticket :restaurant="$restaurant" badge-label="Quick Pick" />',
        ['restaurant' => $restaurant],
    )->assertSee('Quick Pick');
});

it('renders the event label between the badge and the restaurant name when provided', function () {
    $restaurant = Restaurant::factory()->make(['name' => 'The Tap Room']);

    $this->blade(
        '<x-restaurant-result-ticket :restaurant="$restaurant" badge-label="Tonight" event-label="Trivia starts at 7pm" />',
        ['restaurant' => $restaurant],
    )->assertSeeInOrder(['Tonight', 'Trivia starts at 7pm', 'The Tap Room']);
});

it('omits the event label when none is provided', function () {
    $restaurant = Restaurant::factory()->make();

    $this->blade(
        '<x-restaurant-result-ticket :restaurant="$restaurant" badge-label="Quick Pick" />',
        ['restaurant' => $restaurant],
    )->assertDontSee('Trivia starts at 7pm');
});

it('renders cuisine tag badges when the restaurant has cuisine tags', function () {
    $restaurant = Restaurant::factory()->make(['cuisine_tags' => ['Italian', 'Pizza']]);

    $this->blade(
        '<x-restaurant-result-ticket :restaurant="$restaurant" badge-label="Quick Pick" />',
        ['restaurant' => $restaurant],
    )->assertSee('Italian')->assertSee('Pizza');
});

it('omits the cuisine tags block when the restaurant has no cuisine tags', function () {
    $restaurant = Restaurant::factory()->make(['cuisine_tags' => []]);

    $this->blade(
        '<x-restaurant-result-ticket :restaurant="$restaurant" badge-label="Quick Pick" />',
        ['restaurant' => $restaurant],
    )->assertDontSee('cuisine-tags', false);
});

it('renders the price level as one contiguous run of dollar signs when present', function () {
    $restaurant = Restaurant::factory()->make(['price_level' => 3]);

    $this->blade(
        '<x-restaurant-result-ticket :restaurant="$restaurant" badge-label="Quick Pick" />',
        ['restaurant' => $restaurant],
    )->assertSee('$$$');
});

it('omits the price level when it is null', function () {
    $restaurant = Restaurant::factory()->make(['price_level' => null]);

    $this->blade(
        '<x-restaurant-result-ticket :restaurant="$restaurant" badge-label="Quick Pick" />',
        ['restaurant' => $restaurant],
    )->assertDontSee('$');
});

it('renders the distance label when provided', function () {
    $restaurant = Restaurant::factory()->make();

    $this->blade(
        '<x-restaurant-result-ticket :restaurant="$restaurant" badge-label="Quick Pick" distance-label="2.3 mi" />',
        ['restaurant' => $restaurant],
    )->assertSee('2.3 mi');
});

it('renders the tagline when provided', function () {
    $restaurant = Restaurant::factory()->make();

    $this->blade(
        '<x-restaurant-result-ticket :restaurant="$restaurant" badge-label="Quick Pick" tagline="Perfect patio weather tonight" />',
        ['restaurant' => $restaurant],
    )->assertSee('Perfect patio weather tonight');
});

it('renders nothing for the tagline when passed an empty string', function () {
    $restaurant = Restaurant::factory()->make();

    $this->blade(
        '<x-restaurant-result-ticket :restaurant="$restaurant" badge-label="Quick Pick" tagline="" />',
        ['restaurant' => $restaurant],
    )->assertDontSee('italic', false);
});

it('renders the address when present', function () {
    $restaurant = Restaurant::factory()->make(['address' => '123 Main St, Des Moines, IA']);

    $this->blade(
        '<x-restaurant-result-ticket :restaurant="$restaurant" badge-label="Quick Pick" />',
        ['restaurant' => $restaurant],
    )->assertSee('123 Main St, Des Moines, IA');
});

it('omits the address when the restaurant has none', function () {
    $restaurant = Restaurant::factory()->make(['address' => null]);

    $this->blade(
        '<x-restaurant-result-ticket :restaurant="$restaurant" badge-label="Quick Pick" />',
        ['restaurant' => $restaurant],
    )->assertDontSee('Main St');
});
