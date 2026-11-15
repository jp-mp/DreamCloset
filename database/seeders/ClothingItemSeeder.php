<?php

namespace Database\Seeders;

use App\Models\ClothingItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClothingItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()
            ->has(ClothingItem::factory()->count(3))
            ->create();
    }
}
