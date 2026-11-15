<?php

namespace Database\Factories;

use App\Models\ClothingItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClothingItem>
 */
class ClothingItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'category' => fake()->randomElement(ClothingItem::CATEGORIES),
            'color' => fake()->safeColorName(),
            'season' => 'all',
            'warmth' => 'medium',
            'waterproof' => false,
        ];
    }
}
