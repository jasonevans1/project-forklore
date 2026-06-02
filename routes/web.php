<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('pick', 'pages::pick')->name('pick');
    Route::livewire('tonight', 'pages::tonight')->name('tonight');
    Route::livewire('quiz', 'pages::quiz')->name('quiz');
    Route::livewire('tournament', 'pages::tournament')->name('tournament');
    Route::livewire('history', 'pages::history')->name('history');
});

require __DIR__.'/settings.php';
require __DIR__.'/restaurants.php';
