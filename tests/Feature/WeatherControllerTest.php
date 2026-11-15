<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('returns current hourly and daily weather in local time', function () {
    Http::preventStrayRequests();
    $forecast = [
        'timezone' => 'America/Santiago',
        'utc_offset_seconds' => -10800,
        'current_units' => ['temperature_2m' => '°C'],
        'current' => ['time' => '2026-11-15T12:00', 'temperature_2m' => 22.5],
        'hourly_units' => ['temperature_2m' => '°C'],
        'hourly' => ['time' => ['2026-11-15T00:00'], 'temperature_2m' => [14.5]],
        'daily_units' => ['uv_index_max' => ''],
        'daily' => ['time' => ['2026-11-15'], 'uv_index_max' => [8.2]],
    ];
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response($forecast)]);

    $this->get('/api/v1/weather?latitude=-33.349426&longitude=-70.679114')
        ->assertOk()
        ->assertExactJson($forecast);

    Http::assertSent(fn (Request $request) => $request['latitude'] === -33.349426
        && $request['longitude'] === -70.679114
        && $request['timezone'] === 'auto'
        && $request['forecast_days'] === 8
        && $request['temperature_unit'] === 'celsius'
        && str_contains($request['daily'], 'uv_index_max')
        && str_contains($request['hourly'], 'temperature_2m'));
});

test('returns 422 for missing or invalid coordinates without calling the provider', function (string $query, array $fields) {
    Http::preventStrayRequests();
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response([])]);

    $this->get('/api/v1/weather'.$query)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($fields);

    Http::assertNothingSent();
})->with([
    'missing coordinates' => ['', ['latitude', 'longitude']],
    'non numeric latitude' => ['?latitude=hello&longitude=0', ['latitude']],
    'non numeric longitude' => ['?latitude=0&longitude=hello', ['longitude']],
    'latitude too low' => ['?latitude=-91&longitude=0', ['latitude']],
    'latitude too high' => ['?latitude=91&longitude=0', ['latitude']],
    'longitude too low' => ['?latitude=0&longitude=-181', ['longitude']],
    'longitude too high' => ['?latitude=0&longitude=181', ['longitude']],
]);

test('returns 502 when the provider rejects the request', function () {
    Http::preventStrayRequests();
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response(['reason' => 'Unavailable'], 503)]);

    $this->getJson('/api/v1/weather?latitude=0&longitude=0')
        ->assertStatus(502)
        ->assertJsonPath('message', 'Weather data is temporarily unavailable. Please try again later.');

    Http::assertSentCount(1);
});

test('returns 502 when the provider cannot be reached', function () {
    Http::preventStrayRequests();
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::failedConnection()]);

    $this->getJson('/api/v1/weather?latitude=0&longitude=0')
        ->assertStatus(502)
        ->assertJsonPath('message', 'Weather data is temporarily unavailable. Please try again later.');

    Http::assertSentCount(1);
});

test('returns 502 when the provider sends an unexpected payload', function () {
    Http::preventStrayRequests();
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response('not JSON')]);

    $this->getJson('/api/v1/weather?latitude=0&longitude=0')
        ->assertStatus(502)
        ->assertJsonPath('message', 'The weather provider returned an unexpected response. Please try again later.');

    Http::assertSentCount(1);
});
