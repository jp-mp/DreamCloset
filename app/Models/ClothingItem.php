<?php

namespace App\Models;

use Database\Factories\ClothingItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'category', 'color', 'season', 'warmth', 'waterproof', 'photo_path', 'style'])]
class ClothingItem extends Model
{
    public const CATEGORIES = ['top', 'bottom', 'outerwear', 'footwear'];

    public const SEASONS = ['all', 'spring', 'summer', 'autumn', 'winter'];

    public const STYLES = [
        'casual' => 'Casual',
        'formal' => 'Formal',
        'sporty' => 'Deportivo',
    ];

    public const WARMTH_LEVELS = ['light', 'medium', 'warm'];

    /** @use HasFactory<ClothingItemFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['waterproof' => 'boolean'];
    }
}
