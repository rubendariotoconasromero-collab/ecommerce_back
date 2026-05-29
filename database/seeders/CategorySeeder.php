<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Envases Industriales'],
            ['name' => 'Menaje y Hogar'],
            ['name' => 'Embalajes Flexibles'],
            ['name' => 'Botellas y Tapas'],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(
                ['name' => $cat['name']],
                [
                    'slug'      => Str::slug($cat['name']), 
                    'is_active' => true
                ]
            );
        }

        $count = count($categories);
        $this->command->info("CategorySeeder: {$count} categorías sembradas correctamente.");
    }
}
