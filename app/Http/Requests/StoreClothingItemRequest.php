<?php

namespace App\Http\Requests;

use App\Http\ClothingPhoto;
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

    public function messages(): array
    {
        return ClothingPhoto::MESSAGES;
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
            'category' => ['required', Rule::in(array_keys(ClothingItem::CATEGORIES))],
            'color' => ['nullable', 'string', 'max:50'],
            'season' => ['sometimes', 'required', Rule::in(ClothingItem::SEASONS)],
            'warmth' => ['required', Rule::in(ClothingItem::WARMTH_LEVELS)],
            'waterproof' => ['sometimes', 'boolean'],
            'style' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(ClothingItem::STYLES))],
            'photo' => ['sometimes', 'nullable', Rule::prohibitedIf(fn (): bool => $this->boolean('remove_photo')), ...ClothingPhoto::RULES],
            'remove_photo' => $this->route('clothingItem') ? ['sometimes', 'boolean'] : ['prohibited'],
            'user_id' => ['prohibited'],
        ];
    }
}
