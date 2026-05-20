<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::livewire('restaurants', 'pages::restaurants.index')->name('restaurants.index');
    Route::livewire('restaurants/create', 'pages::restaurants.create')->name('restaurants.create');
    Route::livewire('restaurants/{restaurant}', 'pages::restaurants.show')->name('restaurants.show');
    Route::livewire('restaurants/{restaurant}/edit', 'pages::restaurants.edit')->name('restaurants.edit');

    Route::livewire('restaurants/{restaurant}/events', 'pages::restaurants.events.index')->name('restaurants.events.index');
    Route::livewire('restaurants/{restaurant}/events/create', 'pages::restaurants.events.create')->name('restaurants.events.create');
    Route::livewire('restaurants/{restaurant}/events/{event}/edit', 'pages::restaurants.events.edit')->name('restaurants.events.edit');
});
