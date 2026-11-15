<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

test('returns 401 to guests for saved location and weather', function (string $method, string $path) {
    $this->json($method, $path)->assertUnauthorized();
})->with([
    ['GET', '/api/v1/me/location'],
    ['PUT', '/api/v1/me/location'],
    ['DELETE', '/api/v1/me/location'],
    ['GET', '/api/v1/me/weather'],
]);

test('returns no saved location for a new account', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/v1/me/location')
        ->assertOk()->assertExactJson(['data' => null]);
});

test('saves and reads only the authenticated users location', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create(['location_name' => 'London', 'latitude' => 51.5, 'longitude' => -0.12]);
    $location = ['name' => 'Santiago', 'latitude' => -33.349426, 'longitude' => -70.679114];

    $this->actingAs($user)->putJson('/api/v1/me/location', $location)
        ->assertOk()->assertExactJson(['data' => $location]);
    $this->getJson('/api/v1/me/location?user_id='.$otherUser->id)
        ->assertOk()->assertExactJson(['data' => $location]);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'location_name' => 'Santiago']);
    $this->assertDatabaseHas('users', ['id' => $otherUser->id, 'location_name' => 'London', 'latitude' => 51.5]);
});

test('returns 422 for invalid locations and leaves the saved location unchanged', function (array $attributes, array $fields) {
    $user = User::factory()->create(['location_name' => 'Santiago', 'latitude' => -33.3, 'longitude' => -70.6]);

    $this->actingAs($user)->putJson('/api/v1/me/location', $attributes)
        ->assertUnprocessable()->assertJsonValidationErrors($fields);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'location_name' => 'Santiago', 'latitude' => -33.3, 'longitude' => -70.6]);
})->with([
    'missing coordinates' => [[], ['latitude', 'longitude']],
    'non numeric coordinates' => [['latitude' => 'north', 'longitude' => 'west'], ['latitude', 'longitude']],
    'coordinates out of range' => [['latitude' => 91, 'longitude' => -181], ['latitude', 'longitude']],
    'name too long' => [['latitude' => 0, 'longitude' => 0, 'name' => str_repeat('a', 121)], ['name']],
    'forged owner' => [['latitude' => 0, 'longitude' => 0, 'user_id' => 999], ['user_id']],
]);

test('forgets only the authenticated users location', function () {
    $user = User::factory()->create(['location_name' => 'Santiago', 'latitude' => -33.3, 'longitude' => -70.6]);
    $otherUser = User::factory()->create(['location_name' => 'London', 'latitude' => 51.5, 'longitude' => -0.12]);

    $this->actingAs($user)->deleteJson('/api/v1/me/location')->assertNoContent();
    $this->getJson('/api/v1/me/location')->assertExactJson(['data' => null]);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'location_name' => null, 'latitude' => null, 'longitude' => null]);
    $this->assertDatabaseHas('users', ['id' => $otherUser->id, 'location_name' => 'London']);
});

test('returns 422 without calling the weather provider if no location is saved', function () {
    Http::preventStrayRequests();
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response([])]);

    $this->actingAs(User::factory()->create())->getJson('/api/v1/me/weather')
        ->assertUnprocessable()->assertJsonPath('message', 'Choose and save a location first.');

    Http::assertNothingSent();
});

test('fetches weather for the saved location and ignores supplied coordinates or owners', function () {
    Http::preventStrayRequests();
    $forecast = ['current' => ['temperature_2m' => 22], 'hourly' => [], 'daily' => []];
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response($forecast)]);
    $user = User::factory()->create(['latitude' => 0, 'longitude' => 0]);
    $otherUser = User::factory()->create(['latitude' => 51.5, 'longitude' => -0.12]);

    $this->actingAs($user)->getJson('/api/v1/me/weather?latitude=51.5&longitude=-0.12&user_id='.$otherUser->id)
        ->assertOk()->assertExactJson($forecast);

    Http::assertSent(fn (Request $request) => $request['latitude'] === 0.0 && $request['longitude'] === 0.0);
});

test('saved location persists across logout and a fresh login', function () {
    $user = User::factory()->create(['email' => 'ana@example.com']);
    $this->actingAs($user)->putJson('/api/v1/me/location', ['name' => 'Santiago', 'latitude' => -33.3, 'longitude' => -70.6])->assertOk();
    $this->post('/logout')->assertRedirectToRoute('login');
    $this->post('/login', ['email' => 'ana@example.com', 'password' => 'password'])->assertRedirectToRoute('account.show');

    $this->getJson('/api/v1/me/location')->assertOk()->assertJsonPath('data.name', 'Santiago');
});

test('private location writes require csrf protection', function () {
    $user = User::factory()->create();
    $this->app['env'] = 'local';

    $this->actingAs($user)->putJson('/api/v1/me/location', ['latitude' => 0, 'longitude' => 0])
        ->assertStatus(419);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'latitude' => null, 'longitude' => null]);
});
