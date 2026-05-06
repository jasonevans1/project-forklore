<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::livewire('restaurants', 'pages::restaurants.index')->name('restaurants.index');
    Route::livewire('restaurants/create', 'pages::restaurants.create')->name('restaurants.create');
    Route::livewire('restaurants/{restaurant}', 'pages::restaurants.show')->name('restaurants.show');
    Route::livewire('restaurants/{restaurant}/edit', 'pages::restaurants.edit')->name('restaurants.edit');
});
