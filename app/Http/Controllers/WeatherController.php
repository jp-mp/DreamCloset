<?php

namespace App\Http\Controllers;

use App\Http\WeatherForecast;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    public function __construct(private WeatherForecast $weather) {}

    public function show(Request $request): JsonResponse
    {
        $coordinates = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return $this->forecast((float) $coordinates['latitude'], (float) $coordinates['longitude']);
    }

    public function saved(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->latitude === null || $user->longitude === null) {
            return response()->json(['message' => 'Choose and save a location first.'], 422);
        }

        return $this->forecast($user->latitude, $user->longitude);
    }

    private function forecast(float $latitude, float $longitude): JsonResponse
    {
        try {
            $forecast = $this->weather->get($latitude, $longitude);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Weather data is temporarily unavailable. Please try again later.',
            ], 502);
        }

        if ($forecast === null) {
            return response()->json([
                'message' => 'The weather provider returned an unexpected response. Please try again later.',
            ], 502);
        }

        return response()->json($forecast);
    }
}
