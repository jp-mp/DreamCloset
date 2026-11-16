<?php

namespace App\Http;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class ClothingPhoto
{
    public const DISK = 'local';

    public const RULES = ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'];

    public const MESSAGES = [
        'photo.uploaded' => 'La foto supera el límite de subida del servidor.',
        'photo.image' => 'Selecciona una imagen válida.',
        'photo.mimes' => 'Selecciona una foto JPG, PNG o WebP.',
        'photo.max' => 'La foto debe pesar como máximo 5 MB.',
        'photo.dimensions' => 'La foto debe medir como máximo 6000 × 6000 píxeles.',
    ];

    public function removeBackground(UploadedFile $photo): ?string
    {
        $url = rtrim((string) config('services.rembg.url'), '/');

        if ($url === '') {
            return null;
        }

        $response = Http::accept('image/png')
            ->connectTimeout(2)
            ->timeout(35)
            ->withoutRedirecting()
            ->attach('file', $photo->getContent(), 'garment.'.$photo->extension(), ['Content-Type' => $photo->getMimeType()])
            ->post($url.'/api/remove', ['model' => config('services.rembg.model')]);

        if (! $response->successful()) {
            return null;
        }

        $png = $response->body();

        if (strlen($png) > 5 * 1024 * 1024) {
            return null;
        }

        $image = @getimagesizefromstring($png);

        return $image && $image['mime'] === 'image/png' && $image[0] <= 6000 && $image[1] <= 6000 ? $png : null;
    }
}
