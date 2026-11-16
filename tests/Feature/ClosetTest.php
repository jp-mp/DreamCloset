<?php

use App\Models\ClothingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('returns 401 to guests for every private closet action', function (string $method, string $path) {
    $this->json($method, $path)->assertUnauthorized();
})->with([
    ['GET', '/api/v1/me/clothing'],
    ['POST', '/api/v1/me/clothing'],
    ['GET', '/api/v1/me/clothing/1'],
    ['PUT', '/api/v1/me/clothing/1'],
    ['PATCH', '/api/v1/me/clothing/1/style'],
    ['DELETE', '/api/v1/me/clothing/1'],
]);

test('lists only the authenticated users clothing', function () {
    $user = User::factory()->create();
    $ownItem = ClothingItem::factory()->for($user)->create();
    $otherItem = ClothingItem::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/me/clothing?user_id='.$otherItem->user_id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownItem->id)
        ->assertJsonMissing(['id' => $otherItem->id]);
});

test('filters the users closet by category', function () {
    $user = User::factory()->create();
    $jacket = ClothingItem::factory()->for($user)->create(['category' => 'outerwear']);
    ClothingItem::factory()->for($user)->create(['category' => 'top']);
    ClothingItem::factory()->create(['category' => 'outerwear']);

    $this->actingAs($user)->getJson('/api/v1/me/clothing?category=outerwear')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $jacket->id);
});

test('creates clothing owned by the logged in user with defaults', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/me/clothing', [
        'name' => 'Polera blanca', 'category' => 'top', 'warmth' => 'light',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Polera blanca')
        ->assertJsonPath('data.season', 'all')
        ->assertJsonPath('data.waterproof', false)
        ->assertJsonMissingPath('data.user_id');

    $this->assertDatabaseHas('clothing_items', ['user_id' => $user->id, 'name' => 'Polera blanca']);
});

test('returns 422 for invalid clothing and saves nothing', function (array $attributes, array $fields) {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/me/clothing', $attributes)
        ->assertUnprocessable()->assertJsonValidationErrors($fields);

    $this->assertDatabaseCount('clothing_items', 0);
})->with([
    'required fields' => [[], ['name', 'category', 'warmth']],
    'invalid choices' => [['name' => 'Jacket', 'category' => 'other', 'warmth' => 'hot', 'season' => 'other', 'waterproof' => 'maybe'], ['category', 'warmth', 'season', 'waterproof']],
    'length limits' => [['name' => str_repeat('a', 101), 'category' => 'top', 'warmth' => 'light', 'color' => str_repeat('a', 51)], ['name', 'color']],
    'wrong types' => [['name' => [], 'category' => 'top', 'warmth' => 'light', 'color' => []], ['name', 'color']],
    'forged owner' => [['name' => 'Jacket', 'category' => 'outerwear', 'warmth' => 'warm', 'user_id' => 999], ['user_id']],
]);

test('owners can read update and delete their own clothing', function () {
    $user = User::factory()->create();
    $item = ClothingItem::factory()->for($user)->create();
    $path = '/api/v1/me/clothing/'.$item->id;
    $this->actingAs($user);

    $this->getJson($path)->assertOk()->assertJsonPath('data.id', $item->id);
    $this->putJson($path, ['name' => 'Rain jacket', 'category' => 'outerwear', 'warmth' => 'medium', 'waterproof' => true])
        ->assertOk()->assertJsonPath('data.waterproof', true);

    $this->assertDatabaseHas('clothing_items', ['id' => $item->id, 'user_id' => $user->id, 'name' => 'Rain jacket']);
    $this->deleteJson($path)->assertNoContent();
    $this->assertModelMissing($item);
});

test('returns 404 for another users clothing without changing it', function (string $method) {
    $user = User::factory()->create();
    $otherItem = ClothingItem::factory()->create(['name' => 'Private jacket']);

    $this->actingAs($user)->json($method, '/api/v1/me/clothing/'.$otherItem->id, [
        'name' => 'Stolen jacket', 'category' => 'outerwear', 'warmth' => 'warm',
    ])->assertNotFound();

    $this->assertDatabaseHas('clothing_items', ['id' => $otherItem->id, 'user_id' => $otherItem->user_id, 'name' => 'Private jacket']);
})->with(['GET', 'PUT', 'DELETE']);

test('returns 422 when an owner tries to reassign clothing', function () {
    $item = ClothingItem::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($item->user)->putJson('/api/v1/me/clothing/'.$item->id, [
        'name' => 'Jacket', 'category' => 'outerwear', 'warmth' => 'warm', 'user_id' => $otherUser->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('user_id');

    $this->assertDatabaseHas('clothing_items', ['id' => $item->id, 'user_id' => $item->user_id]);
});

test('returns 404 before validating edits to another users clothing', function () {
    $otherItem = ClothingItem::factory()->create(['name' => 'Private jacket']);

    $this->actingAs(User::factory()->create())->putJson('/api/v1/me/clothing/'.$otherItem->id, [])
        ->assertNotFound();

    $this->assertDatabaseHas('clothing_items', ['id' => $otherItem->id, 'name' => 'Private jacket']);
});

test('returns 422 for an unsupported category filter', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/v1/me/clothing?category=unknown')
        ->assertUnprocessable()->assertJsonValidationErrors('category');
});

test('private clothing writes require csrf protection', function () {
    $user = User::factory()->create();
    $this->app['env'] = 'local';

    $this->actingAs($user)->postJson('/api/v1/me/clothing', [
        'name' => 'Jacket', 'category' => 'outerwear', 'warmth' => 'warm',
    ])->assertStatus(419);

    $this->assertDatabaseCount('clothing_items', 0);
});

test('a member can label existing garments by style without altering the rest of the item', function () {
    $item = ClothingItem::factory()->create(['name' => 'Camisa', 'category' => 'top', 'style' => null]);
    $url = route('clothing.style', $item);

    $this->actingAs($item->user)->patchJson($url, ['style' => 'formal', 'name' => 'Ignorar'])
        ->assertOk()->assertJsonPath('data.style', 'formal')->assertJsonPath('data.name', 'Camisa');

    $this->patchJson($url, ['style' => 'inventado'])
        ->assertUnprocessable()->assertJsonValidationErrors('style');

    $this->actingAs(User::factory()->create())->patchJson($url, ['style' => 'casual'])->assertNotFound();
    $this->assertDatabaseHas('clothing_items', ['id' => $item->id, 'style' => 'formal', 'name' => 'Camisa']);

    $this->actingAs($item->user)->patchJson($url, ['style' => null])
        ->assertOk()->assertJsonPath('data.style', null);
    $this->assertDatabaseHas('clothing_items', ['id' => $item->id, 'style' => null]);
});

test('only the owner can open the prefilled garment editor', function () {
    $item = ClothingItem::factory()->create(['name' => 'Chaqueta del clóset', 'waterproof' => true]);
    $url = route('closet.edit', $item);
    $this->withoutVite();

    $this->get($url)->assertRedirectToRoute('login');
    $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
    $this->actingAs($item->user)->get($url)
        ->assertOk()->assertViewHas('item', fn (ClothingItem $saved): bool => $saved->is($item))
        ->assertSeeText('Editar prenda')->assertSee('value="Chaqueta del clóset"', false)
        ->assertSee('name="waterproof" value="1" checked', false);
});
