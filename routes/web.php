<?php

use App\Http\Controllers\EntryController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [EntryController::class, 'index'])->name('dashboard');
    Route::resource('entries', EntryController::class)
        ->except(['index', 'show'])->where(['entry' => '[0-9]+']);
});

require __DIR__.'/settings.php';
