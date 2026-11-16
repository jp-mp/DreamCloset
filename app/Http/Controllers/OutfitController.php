<?php

namespace App\Http\Controllers;

use App\Http\OutfitRecommendations;
use App\Http\Resources\ClothingItemResource;
use App\Http\WeatherForecast;
use App\Models\ClothingItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OutfitController extends Controller
{
    public function __invoke(Request $request, WeatherForecast $weather, OutfitRecommendations $outfits): JsonResponse
    {
        $options = $request->validate([
            'period' => ['required', 'string', Rule::in(['today', 'week'])],
            'style' => ['nullable', 'string', Rule::in(array_keys(ClothingItem::STYLES))],
            'automatic' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        if ($user->latitude === null || $user->longitude === null) {
            return response()->json(['code' => 'location_required', 'message' => 'Guarda tu ubicación para recomendar prendas según el clima.'], 422);
        }

        $items = $user->clothingItems()->latest('id')->get();

        if ($items->isEmpty()) {
            return response()->json(['code' => 'closet_empty', 'message' => 'Agrega prendas a tu clóset para generar un outfit.'], 422);
        }

        try {
            $forecast = $weather->get($user->latitude, $user->longitude);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);
            $forecast = null;
        }

        $result = $forecast === null ? null : $outfits->generate(
            $user->id,
            $user->latitude,
            $options['period'],
            $options['style'] ?? null,
            $forecast,
            $items,
            automatic: $request->boolean('automatic'),
        );

        if ($result === null) {
            return response()->json(['message' => 'El pronóstico no está disponible. Vuelve a intentar en unos minutos.'], 502);
        }

        $wardrobe = $items->keyBy('id');

        $result['outfits'] = array_map(function (array $outfit) use ($wardrobe): array {
            $garments = array_map(fn (int $id): ClothingItem => $wardrobe[$id], $outfit['garment_ids']);
            unset($outfit['garment_ids']);

            return [...$outfit, 'garments' => ClothingItemResource::collection($garments)];
        }, $result['outfits']);

        return response()->json($result, headers: ['Cache-Control' => 'private, no-store']);
    }
}
