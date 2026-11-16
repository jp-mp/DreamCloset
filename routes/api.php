<?php

use App\Http\Controllers\ClothingItemController;
use App\Http\Controllers\ClothingPhotoController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\OutfitController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/weather', [WeatherController::class, 'show'])->name('weather.show');

Route::prefix('v1/me')->middleware(['web', 'auth'])->group(function () {
    Route::get('/clothing', [ClothingItemController::class, 'index'])->name('clothing.index');
    Route::post('/clothing', [ClothingItemController::class, 'store'])->name('clothing.store');
    Route::post('/clothing/photo-preview', [ClothingPhotoController::class, 'preview'])
        ->middleware('throttle:clothing-photos')->name('clothing.photo-preview');
    Route::get('/clothing/{clothingItem}/photo', [ClothingPhotoController::class, 'show'])->name('clothing.photo');
    Route::get('/clothing/{clothingItem}', [ClothingItemController::class, 'show'])->name('clothing.show');
    Route::put('/clothing/{clothingItem}', [ClothingItemController::class, 'update'])->name('clothing.update');
    Route::patch('/clothing/{clothingItem}/style', [ClothingItemController::class, 'updateStyle'])->name('clothing.style');
    Route::delete('/clothing/{clothingItem}', [ClothingItemController::class, 'destroy'])->name('clothing.destroy');

    Route::get('/location', [LocationController::class, 'show'])->name('location.show');
    Route::put('/location', [LocationController::class, 'update'])->name('location.update');
    Route::delete('/location', [LocationController::class, 'destroy'])->name('location.destroy');
    Route::post('/outfits', OutfitController::class)->middleware('throttle:outfits')->name('outfits.generate');
    Route::get('/weather', [WeatherController::class, 'saved'])->name('weather.saved');
});
