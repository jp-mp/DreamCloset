<?php

namespace App\Http\Controllers;

use App\Http\ClothingPhoto;
use App\Models\ClothingItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClothingPhotoController extends Controller
{
    public function preview(Request $request, ClothingPhoto $photos): Response
    {
        $request->validate(
            ['photo' => ['required', ...ClothingPhoto::RULES]],
            ClothingPhoto::MESSAGES,
        );

        $lock = Cache::lock('clothing-photo:rembg', 45);

        abort_unless($lock->get(), 429, 'El servicio de recorte está ocupado. Puedes guardar la foto original.', ['Retry-After' => '10']);

        try {
            $png = $photos->removeBackground($request->file('photo'));
        } catch (ConnectionException) {
            abort(503, 'El servicio de recorte no respondió. Comprueba que esté iniciado y vuelve a intentar.');
        } finally {
            $lock->release();
        }

        abort_if($png === null, 503, 'No pudimos recortar la foto. Puedes conservar la original o volver a intentar.');

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function show(ClothingItem $clothingItem): StreamedResponse
    {
        Gate::authorize('view', $clothingItem);
        $disk = Storage::disk(ClothingPhoto::DISK);

        abort_unless($clothingItem->photo_path && $disk->exists($clothingItem->photo_path), 404);

        return $disk->response($clothingItem->photo_path, headers: [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
