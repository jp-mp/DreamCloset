<?php

use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/weather', [WeatherController::class, 'show'])->name('weather.show');
