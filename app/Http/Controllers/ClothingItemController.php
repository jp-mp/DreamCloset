<?php

namespace App\Http\Controllers;

use App\Http\ClothingPhoto;
use App\Http\Requests\StoreClothingItemRequest;
use App\Http\Resources\ClothingItemResource;
use App\Models\ClothingItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ClothingItemController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'category' => ['sometimes', 'required', Rule::in(ClothingItem::CATEGORIES)],
        ]);

        return ClothingItemResource::collection(
            $request->user()->clothingItems()
                ->when(isset($filters['category']), fn (Builder $query): Builder => $query->where('category', $filters['category']))
                ->latest('id')
                ->paginate(24)
                ->withQueryString()
        );
    }

    public function store(StoreClothingItemRequest $request): ClothingItemResource
    {
        $user = $request->user();
        $attributes = $request->safe()->except(['photo', 'remove_photo']);

        if ($request->hasFile('photo')) {
            $attributes['photo_path'] = $this->storePhoto($request->file('photo'), $user->id);
        }

        try {
            $item = $user->clothingItems()->create($attributes);
        } catch (Throwable $exception) {
            if (isset($attributes['photo_path'])) {
                Storage::disk(ClothingPhoto::DISK)->delete($attributes['photo_path']);
            }

            throw $exception;
        }

        return new ClothingItemResource($item->refresh());
    }

    public function show(ClothingItem $clothingItem): ClothingItemResource
    {
        Gate::authorize('view', $clothingItem);

        return new ClothingItemResource($clothingItem);
    }

    public function edit(ClothingItem $clothingItem): View
    {
        Gate::authorize('update', $clothingItem);

        return view('closet.create', ['item' => $clothingItem]);
    }

    public function update(StoreClothingItemRequest $request, ClothingItem $clothingItem): ClothingItemResource
    {
        $attributes = $request->safe()->except(['photo', 'remove_photo']);
        $replacement = $request->hasFile('photo')
            ? $this->storePhoto($request->file('photo'), $clothingItem->user_id)
            : null;

        if ($replacement !== null || $request->boolean('remove_photo')) {
            $attributes['photo_path'] = $replacement;
        }

        try {
            [$updated, $oldPhoto] = DB::transaction(function () use ($clothingItem, $attributes): array {
                $item = ClothingItem::query()->lockForUpdate()->findOrFail($clothingItem->id);
                $oldPhoto = $item->photo_path;
                $item->update($attributes);

                return [$item, $oldPhoto];
            }, 3);
        } catch (Throwable $exception) {
            if ($replacement !== null) {
                Storage::disk(ClothingPhoto::DISK)->delete($replacement);
            }

            throw $exception;
        }

        if ($oldPhoto !== null && $oldPhoto !== $updated->photo_path) {
            Storage::disk(ClothingPhoto::DISK)->delete($oldPhoto);
        }

        return new ClothingItemResource($updated);
    }

    public function updateStyle(Request $request, ClothingItem $clothingItem): ClothingItemResource
    {
        Gate::authorize('update', $clothingItem);

        $attributes = $request->validate([
            'style' => ['present', 'nullable', 'string', Rule::in(array_keys(ClothingItem::STYLES))],
        ]);
        $clothingItem->update($attributes);

        return new ClothingItemResource($clothingItem);
    }

    public function destroy(ClothingItem $clothingItem): Response
    {
        Gate::authorize('delete', $clothingItem);
        $photo = DB::transaction(function () use ($clothingItem): ?string {
            $item = ClothingItem::query()->lockForUpdate()->findOrFail($clothingItem->id);

            return $item->delete() ? $item->photo_path : null;
        }, 3);

        if ($photo !== null) {
            Storage::disk(ClothingPhoto::DISK)->delete($photo);
        }

        return response()->noContent();
    }

    private function storePhoto(UploadedFile $photo, int $userId): string
    {
        $path = $photo->store('clothing/'.$userId, ClothingPhoto::DISK);

        if ($path === false) {
            throw new RuntimeException('Could not store a clothing photo.');
        }

        return $path;
    }
}
