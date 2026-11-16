<?php

namespace App\Http;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeatherForecast
{
    /** @return array<string, mixed>|null */
    public function get(float $latitude, float $longitude): ?array
    {
        return Cache::remember('weather:'.hash('sha256', "$latitude,$longitude"), now()->addMinutes(10), function () use ($latitude, $longitude): ?array {
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

            return is_array($forecast)
                && is_array($forecast['current'] ?? null)
                && is_array($forecast['hourly'] ?? null)
                && is_array($forecast['daily'] ?? null) ? $forecast : null;
        });
    }
}
