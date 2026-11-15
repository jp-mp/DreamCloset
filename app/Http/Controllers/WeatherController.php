<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{
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
            $forecast = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'timezone' => 'auto',
                    'forecast_days' => 8,
                    'temperature_unit' => 'celsius',
                    'wind_speed_unit' => 'kmh',
                    'precipitation_unit' => 'mm',
                    'current' => 'temperature_2m,apparent_temperature,weather_code,precipitation,wind_speed_10m',
                    'hourly' => 'temperature_2m,apparent_temperature,precipitation_probability,weather_code,wind_speed_10m',
                    'daily' => 'temperature_2m_max,temperature_2m_min,apparent_temperature_max,apparent_temperature_min,precipitation_probability_max,uv_index_max,weather_code,wind_speed_10m_max',
                ])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Weather data is temporarily unavailable. Please try again later.',
            ], 502);
        }

        if (! is_array($forecast) || ! isset($forecast['current'], $forecast['hourly'], $forecast['daily'])) {
            return response()->json([
                'message' => 'The weather provider returned an unexpected response. Please try again later.',
            ], 502);
        }

        return response()->json($forecast);
    }
}
