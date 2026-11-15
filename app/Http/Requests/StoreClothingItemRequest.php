<?php

namespace App\Http\Requests;

use App\Models\ClothingItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreClothingItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        $item = $this->route('clothingItem');

        if ($item instanceof ClothingItem) {
            Gate::authorize('update', $item);
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::in(ClothingItem::CATEGORIES)],
            'color' => ['nullable', 'string', 'max:50'],
            'season' => ['sometimes', 'required', Rule::in(ClothingItem::SEASONS)],
            'warmth' => ['required', Rule::in(ClothingItem::WARMTH_LEVELS)],
            'waterproof' => ['sometimes', 'boolean'],
            'user_id' => ['prohibited'],
        ];
    }
}
