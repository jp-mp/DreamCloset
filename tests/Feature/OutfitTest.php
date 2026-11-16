<?php

use App\Models\ClothingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

function outfitMember(): User
{
    $user = User::factory()->create();
    $user->forceFill(['latitude' => -33.45, 'longitude' => -70.66])->save();

    return $user;
}

function outfitForecast(): array
{
    return [
        'current' => ['apparent_temperature' => 9, 'weather_code' => 61, 'precipitation' => 1],
        'hourly' => [],
        'daily' => [
            'time' => ['2026-11-15', '2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20', '2026-11-21'],
            'apparent_temperature_min' => [9, 19, 19, 19, 19, 19, 19],
            'apparent_temperature_max' => [12, 27, 27, 27, 27, 27, 27],
            'precipitation_probability_max' => [80, 0, 0, 0, 0, 0, 0],
        ],
    ];
}

test('outfits require authentication and valid options', function () {
    Http::fake();
    $this->postJson(route('outfits.generate'), ['period' => 'today'])->assertUnauthorized();

    $this->actingAs(User::factory()->create())->postJson(route('outfits.generate'), ['period' => 'month', 'style' => 'other'])
        ->assertUnprocessable()->assertJsonValidationErrors(['period', 'style']);

    Http::assertNothingSent();
});

test('outfits require a saved location and clothing before consulting providers', function () {
    Http::fake();
    $this->actingAs(User::factory()->create())->postJson(route('outfits.generate'), ['period' => 'today'])
        ->assertUnprocessable()->assertJsonPath('code', 'location_required');

    $this->actingAs(outfitMember())->postJson(route('outfits.generate'), ['period' => 'week'])
        ->assertUnprocessable()->assertJsonPath('code', 'closet_empty');

    Http::assertNothingSent();
});

test('outfits receive 502 when the weather provider is unavailable', function () {
    $user = outfitMember();
    ClothingItem::factory()->for($user)->create();
    Http::preventStrayRequests();
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response([], 503)]);

    $this->actingAs($user)->postJson(route('outfits.generate'), ['period' => 'today'])->assertStatus(502);

    Http::assertSentCount(1);
});

test('generates a weather based outfit for each forecast day and prioritizes waterproof clothing in rain', function () {
    config(['services.groq.key' => null]);
    Http::preventStrayRequests();
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response(outfitForecast())]);
    $user = outfitMember();
    $warm = ClothingItem::factory()->for($user)->create(['category' => 'top', 'warmth' => 'warm']);
    $light = ClothingItem::factory()->for($user)->create(['category' => 'top', 'warmth' => 'light']);
    $raincoat = ClothingItem::factory()->for($user)->create(['category' => 'outerwear', 'warmth' => 'light', 'waterproof' => true]);
    $dryCoat = ClothingItem::factory()->for($user)->create(['category' => 'outerwear', 'warmth' => 'warm', 'waterproof' => false]);
    $otherUsersItem = ClothingItem::factory()->create(['category' => 'footwear', 'waterproof' => true]);

    $response = $this->actingAs($user)->postJson(route('outfits.generate'), ['period' => 'week'])
        ->assertOk()->assertJsonPath('source', 'rules')->assertJsonCount(7, 'outfits')
        ->assertJsonPath('outfits.0.rain', true)->assertJsonPath('outfits.0.garments.0.id', $warm->id)
        ->assertJsonPath('outfits.0.garments.1.id', $raincoat->id)
        ->assertJsonPath('outfits.0.missing', ['bottom', 'footwear'])
        ->assertJsonPath('outfits.1.garments.0.id', $light->id)->assertJsonCount(1, 'outfits.1.garments')
        ->assertJsonMissing(['id' => $dryCoat->id])->assertJsonMissing(['id' => $otherUsersItem->id]);

    $this->getJson(route('weather.saved'))->assertOk();
    $this->postJson(route('outfits.generate'), ['period' => 'week'])->assertExactJson($response->json());

    Http::assertSentCount(1);
});

test('uses a valid groq suggestion and caches it without exposing provider data', function () {
    config(['services.groq.key' => 'provider-test-key']);
    $user = outfitMember();
    $first = ClothingItem::factory()->for($user)->create(['category' => 'top']);
    ClothingItem::factory()->for($user)->create(['category' => 'top']);
    $privateItem = ClothingItem::factory()->create(['name' => 'Private Other Wardrobe']);
    $reason = 'Esta polera funciona con el abrigo disponible.';
    Http::preventStrayRequests();
    Http::fake([
        'api.open-meteo.com/v1/forecast*' => Http::response(outfitForecast()),
        'api.groq.com/openai/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['outfits' => [
                ['date' => '2026-11-15', 'garment_ids' => [$first->id], 'reason' => $reason],
            ]], JSON_THROW_ON_ERROR)]]],
        ]),
    ]);
    $this->actingAs($user);
    $request = ['period' => 'today', 'style' => 'formal'];

    $result = $this->postJson(route('outfits.generate'), $request)->assertOk()
        ->assertJsonPath('source', 'groq')->assertJsonCount(1, 'outfits')
        ->assertJsonPath('outfits.0.reason', $reason)->assertJsonPath('outfits.0.garments.0.id', $first->id);
    $this->postJson(route('outfits.generate'), $request)->assertExactJson($result->json());

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.groq.com')
        && $request->hasHeader('Authorization', 'Bearer provider-test-key')
        && $request['model'] === 'openai/gpt-oss-20b'
        && $request['response_format']['json_schema']['strict']
        && str_contains($request['messages'][1]['content'], '"style":"formal"')
        && ! str_contains($request['messages'][1]['content'], $user->email)
        && ! str_contains($request['messages'][1]['content'], $privateItem->name));
});

test('changes outfits with two compatible accessories without pairing two watches', function () {
    config(['services.groq.key' => null]);
    Http::preventStrayRequests();
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response(outfitForecast())]);
    $user = outfitMember();
    $clothes = ['top', 'bottom', 'footwear', 'outerwear'];
    foreach ($clothes as $category) {
        ClothingItem::factory()->for($user)->create(['category' => $category]);
    }
    $cap = ClothingItem::factory()->for($user)->create(['category' => 'accessory', 'name' => 'Gorra de béisbol', 'warmth' => 'warm']);
    $watch = ClothingItem::factory()->for($user)->create(['category' => 'accessory', 'name' => 'Reloj de vestir', 'warmth' => 'warm']);
    $smartwatch = ClothingItem::factory()->for($user)->create(['category' => 'accessory', 'name' => 'Smartwatch negro', 'warmth' => 'warm']);

    $first = $this->actingAs($user)->postJson(route('outfits.generate'), ['period' => 'today'])
        ->assertOk()->assertJsonCount(6, 'outfits.0.garments')
        ->assertJsonFragment(['id' => $cap->id])->assertJsonFragment(['id' => $smartwatch->id])
        ->assertJsonMissing(['id' => $watch->id]);
    $previous = ['2026-11-15' => array_column($first->json('outfits.0.garments'), 'id')];

    $this->postJson(route('outfits.generate'), [
        'period' => 'today',
        'previous' => $previous,
        'recent' => ['2026-11-15' => [$previous['2026-11-15']]],
    ])->assertOk()->assertJsonCount(6, 'outfits.0.garments')
        ->assertJsonFragment(['id' => $cap->id])->assertJsonFragment(['id' => $watch->id])
        ->assertJsonMissing(['id' => $smartwatch->id]);

    Http::assertSentCount(1);
});

test('keeps at most one accessory when the other types are unknown or duplicated', function () {
    config(['services.groq.key' => null]);
    Http::preventStrayRequests();
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response(outfitForecast())]);
    $user = outfitMember();
    ClothingItem::factory()->for($user)->create(['category' => 'accessory', 'name' => 'Favorito', 'warmth' => 'warm']);
    ClothingItem::factory()->for($user)->create(['category' => 'accessory', 'name' => 'Reloj con correa de cuero', 'warmth' => 'warm']);
    $watch = ClothingItem::factory()->for($user)->create(['category' => 'accessory', 'name' => 'Reloj deportivo', 'warmth' => 'warm']);

    $this->actingAs($user)->postJson(route('outfits.generate'), ['period' => 'today'])
        ->assertOk()->assertJsonCount(1, 'outfits.0.garments')->assertJsonPath('outfits.0.garments.0.id', $watch->id);

    Http::assertSentCount(1);
});

test('falls back to owned clothing when groq returns an invalid outfit or rejects the call', function (string $failure) {
    config(['services.groq.key' => 'provider-test-key']);
    $user = outfitMember();
    $owned = ClothingItem::factory()->for($user)->create(['category' => 'top']);
    $foreign = ClothingItem::factory()->create(['category' => 'top']);
    $content = json_encode(['outfits' => [
        ['date' => '2026-11-15', 'garment_ids' => [$foreign->id], 'reason' => 'Otro clóset.'],
    ]], JSON_THROW_ON_ERROR);
    $provider = match ($failure) {
        'invalid id' => Http::response(['choices' => [['message' => ['content' => $content]]]]),
        'limited' => Http::response([], 429, ['Retry-After' => 60]),
        default => Http::failedConnection(),
    };

    Http::preventStrayRequests();
    Http::fake([
        'api.open-meteo.com/v1/forecast*' => Http::response(outfitForecast()),
        'api.groq.com/openai/v1/chat/completions' => $provider,
    ]);
    $this->actingAs($user)->postJson(route('outfits.generate'), ['period' => 'today'])
        ->assertOk()->assertJsonPath('source', 'rules')->assertJsonPath('outfits.0.garments.0.id', $owned->id)
        ->assertJsonMissing(['id' => $foreign->id]);

    if ($failure !== 'invalid id') {
        $this->postJson(route('outfits.generate'), ['period' => 'week'])->assertOk()->assertJsonPath('source', 'rules');
    }

    Http::assertSentCount(2);
})->with(['invalid id', 'limited', 'offline']);

test('automatic outfits use the cached forecast without waiting for Groq', function () {
    config(['services.groq.key' => 'provider-test-key']);
    Http::preventStrayRequests();
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response(outfitForecast())]);
    $user = outfitMember();
    $garment = ClothingItem::factory()->for($user)->create();

    $this->actingAs($user)->getJson(route('weather.saved'))->assertOk();
    $this->postJson(route('outfits.generate'), ['period' => 'today', 'automatic' => true])
        ->assertOk()->assertJsonPath('source', 'rules')->assertJsonPath('outfits.0.garments.0.id', $garment->id);

    Http::assertSentCount(1);
});

test('manual styles change the local selection while the weather still determines waterproof layers', function () {
    config(['services.groq.key' => null]);
    Http::preventStrayRequests();
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response(outfitForecast())]);
    $user = outfitMember();
    $formal = ClothingItem::factory()->for($user)->create(['category' => 'top', 'warmth' => 'warm', 'style' => 'formal', 'season' => 'all']);
    $sporty = ClothingItem::factory()->for($user)->create(['category' => 'top', 'warmth' => 'warm', 'style' => 'sporty', 'season' => 'all']);
    ClothingItem::factory()->for($user)->create(['category' => 'top', 'warmth' => 'warm', 'style' => null, 'season' => 'all']);
    $raincoat = ClothingItem::factory()->for($user)->create(['category' => 'outerwear', 'warmth' => 'warm', 'waterproof' => true]);
    ClothingItem::factory()->for($user)->create(['category' => 'outerwear', 'warmth' => 'warm', 'style' => 'formal', 'waterproof' => false]);

    $this->actingAs($user)->postJson(route('outfits.generate'), ['period' => 'today', 'style' => 'formal'])
        ->assertOk()->assertJsonPath('outfits.0.garments.0.id', $formal->id)->assertJsonPath('outfits.0.garments.1.id', $raincoat->id);

    $this->postJson(route('outfits.generate'), ['period' => 'today', 'style' => 'sporty'])
        ->assertOk()->assertJsonPath('outfits.0.garments.0.id', $sporty->id);

    Http::assertSentCount(1);
});

test('an outfit can include an accessory as a complement to its essential garments', function () {
    config(['services.groq.key' => null]);
    Http::preventStrayRequests();
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response(outfitForecast())]);
    $member = outfitMember();
    foreach (['top', 'bottom', 'footwear'] as $category) {
        ClothingItem::factory()->for($member)->create(['category' => $category]);
    }
    $accessory = ClothingItem::factory()->for($member)->create(['category' => 'accessory', 'name' => 'Reloj']);

    $this->actingAs($member)->postJson(route('outfits.generate'), ['period' => 'today', 'automatic' => true])
        ->assertOk()->assertJsonCount(4, 'outfits.0.garments')
        ->assertJsonPath('outfits.0.garments.3.id', $accessory->id)
        ->assertJsonPath('outfits.0.missing', []);
});

test('weekly outfits vary across similar days and keep the only waterproof layer', function () {
    config(['services.groq.key' => null]);
    $forecast = outfitForecast();
    $forecast['daily']['precipitation_probability_max'] = array_fill(0, 7, 80);
    $forecast['daily']['apparent_temperature_min'] = array_fill(0, 7, 9);
    $forecast['daily']['apparent_temperature_max'] = array_fill(0, 7, 12);
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response($forecast)]);
    $member = outfitMember();

    foreach (['top', 'bottom', 'footwear'] as $category) {
        ClothingItem::factory()->count(2)->for($member)->create([
            'category' => $category, 'warmth' => 'warm', 'waterproof' => true, 'season' => 'all',
        ]);
    }
    $raincoat = ClothingItem::factory()->for($member)->create([
        'category' => 'outerwear', 'waterproof' => true, 'warmth' => 'warm',
    ]);
    $dryCoat = ClothingItem::factory()->for($member)->create([
        'category' => 'outerwear', 'waterproof' => false, 'warmth' => 'warm',
    ]);

    $days = $this->actingAs($member)->postJson(route('outfits.generate'), ['period' => 'week'])
        ->assertOk()->assertJsonCount(7, 'outfits')->assertJsonMissing(['id' => $dryCoat->id])->json('outfits');

    expect(collect($days)->map(fn (array $day) => collect($day['garments'])->pluck('id')->all())->unique())->toHaveCount(7);
    expect(collect($days)->every(fn (array $day): bool => in_array($raincoat->id, array_column($day['garments'], 'id'), true)))->toBeTrue();
});

test('asking for another daily outfit changes eligible garments while keeping rain protection', function () {
    config(['services.groq.key' => null]);
    Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response(outfitForecast())]);
    $member = outfitMember();
    ClothingItem::factory()->count(3)->for($member)->create([
        'category' => 'top', 'warmth' => 'warm', 'season' => 'all', 'style' => 'formal',
    ]);
    $sportsTop = ClothingItem::factory()->for($member)->create([
        'category' => 'top', 'warmth' => 'warm', 'season' => 'all', 'style' => 'sporty',
    ]);
    $raincoat = ClothingItem::factory()->for($member)->create([
        'category' => 'outerwear', 'warmth' => 'warm', 'waterproof' => true,
    ]);

    $request = ['period' => 'today', 'style' => 'formal'];
    $first = $this->actingAs($member)->postJson(route('outfits.generate'), $request)->assertOk()->json('outfits.0');
    $previous = array_column($first['garments'], 'id');
    $second = $this->postJson(route('outfits.generate'), [
        ...$request, 'previous' => [$first['date'] => array_map('strval', $previous)],
    ])->assertOk()->assertJsonMissing(['id' => $sportsTop->id])->json('outfits.0.garments');

    expect(array_column($second, 'id'))->not->toBe($previous)->toContain($raincoat->id);
    $third = $this->postJson(route('outfits.generate'), [
        ...$request,
        'previous' => [$first['date'] => array_column($second, 'id')],
        'recent' => [$first['date'] => [$previous]],
    ])->assertOk()->json('outfits.0.garments');

    expect(array_column($third, 'id'))->not->toBe($previous)->not->toBe(array_column($second, 'id'));
    Http::assertSentCount(1);
});
