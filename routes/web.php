<?php

use Illuminate\Support\Facades\Route;

// The gateway's website is its admin: the root opens it (guests land on the login page from there).
Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return auth()->user()?->is_admin
            ? redirect()->route('admin.dashboard')
            : redirect()->route('home');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
