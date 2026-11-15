<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClothingItemRequest;
use App\Http\Resources\ClothingItemResource;
use App\Models\ClothingItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
        $item = $request->user()->clothingItems()->create($request->validated());

        return new ClothingItemResource($item->refresh());
    }

    public function show(ClothingItem $clothingItem): ClothingItemResource
    {
        Gate::authorize('view', $clothingItem);

        return new ClothingItemResource($clothingItem);
    }

    public function update(StoreClothingItemRequest $request, ClothingItem $clothingItem): ClothingItemResource
    {
        $clothingItem->update($request->validated());

        return new ClothingItemResource($clothingItem);
    }

    public function destroy(ClothingItem $clothingItem): Response
    {
        Gate::authorize('delete', $clothingItem);
        $clothingItem->delete();

        return response()->noContent();
    }
}
