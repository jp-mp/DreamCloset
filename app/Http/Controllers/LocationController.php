<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LocationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->locationResponse($request->user());
    }

    public function update(Request $request): JsonResponse
    {
        $location = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'user_id' => ['prohibited'],
        ]);

        $user = $request->user();
        $user->location_name = $location['name'] ?? null;
        $user->latitude = $location['latitude'];
        $user->longitude = $location['longitude'];
        $user->save();

        return $this->locationResponse($user->refresh());
    }

    public function destroy(Request $request): Response
    {
        $user = $request->user();
        $user->location_name = null;
        $user->latitude = null;
        $user->longitude = null;
        $user->save();

        return response()->noContent();
    }

    private function locationResponse(User $user): JsonResponse
    {
        return response()->json([
            'data' => $user->latitude === null || $user->longitude === null ? null : [
                'name' => $user->location_name,
                'latitude' => $user->latitude,
                'longitude' => $user->longitude,
            ],
        ]);
    }
}
