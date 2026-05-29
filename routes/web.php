<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('pick', 'pages::pick')->name('pick');
    Route::livewire('tonight', 'pages::tonight')->name('tonight');
});

require __DIR__.'/settings.php';
require __DIR__.'/restaurants.php';
