<?php

use App\Models\ClothingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

test('guests receive 401 when requesting previews or clothing photos', function () {
    Http::fake();

    $this->postJson(route('clothing.photo-preview'))->assertUnauthorized();
    $this->getJson(route('clothing.photo', ['clothingItem' => 1]))->assertUnauthorized();

    Http::assertNothingSent();
});

test('owners can save and view private clothing photos and deleting a garment removes its photo', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $photo = UploadedFile::fake()->image('original.webp', 100, 100);

    $response = $this->actingAs($owner)->post(route('clothing.store'), [
        'name' => 'Polera verde',
        'category' => 'top',
        'warmth' => 'light',
        'photo' => $photo,
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonMissingPath('data.photo_path');

    $item = $owner->clothingItems()->firstOrFail();
    $url = route('clothing.photo', $item, absolute: false);
    $response->assertJsonPath('data.photo_url', $url);
    Storage::disk('local')->assertExists($item->photo_path);
    $this->getJson(route('clothing.index'))->assertJsonPath('data.0.photo_url', $url);

    $image = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
    expect($image->baseResponse->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
    expect($image->streamedContent())->toBe($photo->getContent());

    $this->actingAs($otherUser)->getJson($url)->assertNotFound();
    $this->actingAs($owner)->deleteJson(route('clothing.destroy', $item))->assertNoContent();
    Storage::disk('local')->assertMissing($item->photo_path);
});

test('existing clothing without photos still loads', function () {
    $item = ClothingItem::factory()->create();

    $this->actingAs($item->user)->getJson(route('clothing.show', $item))->assertOk()->assertJsonPath('data.photo_url', null);
    $this->getJson(route('clothing.photo', $item))->assertNotFound();
});

test('passes the uploaded image to the configured rembg model and returns the png without saving', function () {
    Storage::fake('local');
    config(['services.rembg.url' => 'http://127.0.0.1:7000']);
    $png = UploadedFile::fake()->image('cutout.png', 20, 20)->getContent();
    Http::preventStrayRequests();
    Http::fake(['127.0.0.1:7000/api/remove' => Http::response($png, headers: ['Content-Type' => 'image/png'])]);

    $response = $this->actingAs(User::factory()->create())->post(route('clothing.photo-preview'), [
        'photo' => UploadedFile::fake()->image('prenda.jpg', 20, 20),
    ])->assertOk()->assertHeader('Content-Type', 'image/png');

    expect($response->getContent())->toBe($png);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    $this->assertDatabaseCount('clothing_items', 0);
    Http::assertSent(fn (Request $request) => $request->hasFile('file')
        && $request->hasFile('model', 'u2netp'));
});

test('invalid photos receive 422 before storage or background removal', function () {
    Storage::fake('local');
    Http::fake();
    $this->actingAs(User::factory()->create());
    $fakePhoto = UploadedFile::fake()->createWithContent('malicious.png', '<svg></svg>');

    $this->postJson(route('clothing.photo-preview'), ['photo' => $fakePhoto])
        ->assertUnprocessable()->assertJsonValidationErrors('photo');
    $this->postJson(route('clothing.store'), [
        'name' => 'Polera', 'category' => 'top', 'warmth' => 'light', 'photo' => $fakePhoto,
    ])->assertUnprocessable()->assertJsonValidationErrors('photo');

    Http::assertNothingSent();
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('returns 503 when rembg fails or sends an invalid png and keeps the original upload available', function (bool $connectionFails) {
    Storage::fake('local');
    config(['services.rembg.url' => 'http://127.0.0.1:7000']);
    Http::preventStrayRequests();
    Http::fake(['127.0.0.1:7000/api/remove' => $connectionFails ? Http::failedConnection() : Http::response('invalid')]);
    $this->actingAs(User::factory()->create());

    $message = $connectionFails
        ? 'El servicio de recorte no respondió. Comprueba que esté iniciado y vuelve a intentar.'
        : 'No pudimos recortar la foto. Puedes conservar la original o volver a intentar.';

    $this->postJson(route('clothing.photo-preview'), ['photo' => UploadedFile::fake()->image('original.jpg')])
        ->assertServiceUnavailable()->assertJsonPath('message', $message);
    $this->postJson(route('clothing.store'), [
        'name' => 'Polera', 'category' => 'top', 'warmth' => 'light', 'photo' => UploadedFile::fake()->image('original.jpg'),
    ])->assertCreated();

    Http::assertSentCount(1);
})->with([true, false]);

test('returns 429 without calling rembg when a preview is already in progress', function () {
    Http::fake();
    $lock = Cache::lock('clothing-photo:rembg', 45);
    $lock->get();

    $this->actingAs(User::factory()->create())->postJson(route('clothing.photo-preview'), [
        'photo' => UploadedFile::fake()->image('original.jpg'),
    ])->assertTooManyRequests()->assertHeader('Retry-After', '10');

    Http::assertNothingSent();
    $lock->release();
});

test('a multipart edit saves the selected replacement and removes the previous photo', function () {
    $disk = Storage::fake('local');
    $item = ClothingItem::factory()->create(['name' => 'Polera de antes', 'waterproof' => true, 'photo_path' => 'clothing/original.jpg']);
    $disk->put($item->photo_path, 'old photo data');
    $replacement = UploadedFile::fake()->image('prenda-sin-fondo.png', 30, 40);

    $this->actingAs($item->user)->post(route('clothing.update', $item), [
        '_method' => 'PUT', 'name' => 'Polera editada', 'category' => 'top', 'warmth' => 'light',
        'waterproof' => '0', 'photo' => $replacement,
    ], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.name', 'Polera editada')->assertJsonPath('data.waterproof', false)
        ->assertJsonPath('data.edit_url', route('closet.edit', $item, absolute: false));

    $currentPhoto = $item->fresh()->photo_path;
    $disk->assertMissing($item->photo_path)->assertExists($currentPhoto);
    expect($disk->get($currentPhoto))->toBe($replacement->getContent());
    $this->get(route('clothing.photo', $item))->assertHeader('Content-Type', 'image/png')->assertOk();
});

test('an edit keeps the current photo unless removal is requested', function () {
    $disk = Storage::fake('local');
    $item = ClothingItem::factory()->create(['photo_path' => 'clothing/current.jpg']);
    $disk->put($item->photo_path, 'current photo data');
    $attributes = ['name' => 'Chaqueta', 'category' => 'outerwear', 'warmth' => 'medium'];
    $this->actingAs($item->user);

    $this->putJson(route('clothing.update', $item), $attributes)->assertOk()
        ->assertJsonPath('data.photo_url', route('clothing.photo', $item, absolute: false));
    $disk->assertExists($item->photo_path);

    $this->putJson(route('clothing.update', $item), [...$attributes, 'remove_photo' => true])->assertOk()
        ->assertJsonPath('data.photo_url', null);
    $disk->assertMissing($item->photo_path);
    $this->getJson(route('clothing.show', $item))->assertOk()->assertJsonPath('data.name', 'Chaqueta');
});

test('photo edits by another user or conflicting photo inputs leave the saved photo intact', function () {
    $disk = Storage::fake('local');
    $item = ClothingItem::factory()->create(['photo_path' => 'clothing/current.jpg']);
    $disk->put($item->photo_path, 'current photo data');
    $attributes = ['name' => 'Chaqueta', 'category' => 'outerwear', 'warmth' => 'medium'];
    $url = route('clothing.update', $item);

    $this->actingAs(User::factory()->create())->putJson($url, [...$attributes, 'remove_photo' => true])->assertNotFound();
    $this->actingAs($item->user)->putJson($url, [
        ...$attributes, 'remove_photo' => true, 'photo' => UploadedFile::fake()->image('nueva.png'),
    ])->assertUnprocessable()->assertJsonValidationErrors('photo');

    expect($item->fresh()->photo_path)->toBe($item->photo_path);
    expect($disk->allFiles())->toBe([$item->photo_path]);
});
