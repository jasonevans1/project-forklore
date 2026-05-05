<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::livewire('restaurants', 'pages::restaurants.index')->name('restaurants.index');
    Route::livewire('restaurants/create', 'pages::restaurants.create')->name('restaurants.create');
});
