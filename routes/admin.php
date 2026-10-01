<?php

use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HomepageConfigController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\UserController;
use App\Models\HomepageConfig;
use Illuminate\Support\Facades\Route;

// The database is shared with other storefronts; other clients' configs must stay unreachable here.
Route::bind('config', fn (string $value) => HomepageConfig::where('client_key', config('services.storefront.client_key'))->findOrFail($value));

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.updateStatus');

    Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
    Route::get('brands/{brand}', [BrandController::class, 'show'])->name('brands.show');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');

    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    Route::get('homepage', [HomepageConfigController::class, 'index'])->name('homepage.index');
    Route::get('homepage/create', [HomepageConfigController::class, 'create'])->name('homepage.create');
    Route::post('homepage', [HomepageConfigController::class, 'store'])->name('homepage.store');
    Route::get('homepage/{config}/edit', [HomepageConfigController::class, 'edit'])->name('homepage.edit');
    Route::patch('homepage/{config}', [HomepageConfigController::class, 'update'])->name('homepage.update');
    Route::delete('homepage/{config}', [HomepageConfigController::class, 'destroy'])->name('homepage.destroy');
    Route::post('homepage/{config}/sections/reorder', [HomepageConfigController::class, 'reorderSections'])->name('homepage.sections.reorder');
    Route::post('homepage/{config}/sections', [HomepageConfigController::class, 'storeSection'])->name('homepage.sections.store');
    Route::patch('homepage/{config}/sections/{section}', [HomepageConfigController::class, 'updateSection'])->name('homepage.sections.update');
    Route::delete('homepage/{config}/sections/{section}', [HomepageConfigController::class, 'destroySection'])->name('homepage.sections.destroy');
    Route::post('homepage/{config}/sections/{section}/items', [HomepageConfigController::class, 'storeItem'])->name('homepage.sections.items.store');
    Route::post('homepage/{config}/sections/{section}/items/reorder', [HomepageConfigController::class, 'reorderItems'])->name('homepage.sections.items.reorder');
    Route::post('homepage/{config}/sections/{section}/items/bulk-brands', [HomepageConfigController::class, 'storeBrandLogos'])->name('homepage.sections.items.bulk-brands');
    Route::post('homepage/{config}/sections/{section}/items/{item}', [HomepageConfigController::class, 'updateItem'])->name('homepage.sections.items.update');
    Route::delete('homepage/{config}/sections/{section}/items/{item}', [HomepageConfigController::class, 'destroyItem'])->name('homepage.sections.items.destroy');
});
