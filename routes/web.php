<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClothingItemController;
use App\Http\Controllers\DailyDrinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return to_route('home');
});

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/account', [AuthController::class, 'account'])->name('account.show');
    Route::patch('/account/name', [AuthController::class, 'updateName'])->name('account.name');
    Route::patch('/account/easter-eggs', [AuthController::class, 'updateEasterEggs'])->name('account.easter-eggs');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/inicio', [DailyDrinkController::class, 'showHome'])->name('home');
    Route::post('/inicio/otro-trago', [DailyDrinkController::class, 'another'])->name('daily-drink.another');
    Route::view('/mi-semana', 'week.index')->name('week.index');
    Route::view('/mi-closet', 'closet.index')->name('closet.index');
    Route::view('/mi-closet/agregar', 'closet.create')->name('closet.create');
    Route::get('/mi-closet/{clothingItem}/editar', [ClothingItemController::class, 'edit'])->name('closet.edit');
});
