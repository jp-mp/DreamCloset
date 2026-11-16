<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClothingItemController;
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
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::view('/inicio', 'home')->name('home');
    Route::view('/mi-semana', 'week.index')->name('week.index');
    Route::view('/mi-closet', 'closet.index')->name('closet.index');
    Route::view('/mi-closet/agregar', 'closet.create')->name('closet.create');
    Route::get('/mi-closet/{clothingItem}/editar', [ClothingItemController::class, 'edit'])->name('closet.edit');
});
