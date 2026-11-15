<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClothingItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'color' => $this->color,
            'season' => $this->season,
            'warmth' => $this->warmth,
            'waterproof' => $this->waterproof,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
