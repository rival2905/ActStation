<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Database\Seeder;

class SportCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Basketball',
                'slug' => 'basketball',
                'icon' => 'fas fa-basketball-ball',
                'is_active' => true,
            ],
            [
                'name' => 'Futsal',
                'slug' => 'futsal',
                'icon' => 'fas fa-futbol',
                'is_active' => true,
            ],
            [
                'name' => 'Badminton',
                'slug' => 'badminton',
                'icon' => 'fas fa-table-tennis',
                'is_active' => true,
            ],
            [
                'name' => 'Sepak Bola',
                'slug' => 'sepak-bola',
                'icon' => 'fas fa-futbol',
                'is_active' => true,
            ],
            [
                'name' => 'Bola Voli',
                'slug' => 'bola-voli',
                'icon' => 'fas fa-volleyball-ball',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            $sportCategory = SportCategory::firstOrCreate(
                ['slug' => $category['slug']],
                $category
            );

            if ($sportCategory->slug === 'basketball') {
                $developmentLevels = [
                    [
                        'name' => 'Usia Dini',
                        'min_age' => 0,
                        'max_age' => 12,
                        'sort_order' => 1,
                        'is_active' => true,
                    ],
                    [
                        'name' => 'Remaja',
                        'min_age' => 13,
                        'max_age' => 16,
                        'sort_order' => 2,
                        'is_active' => true,
                    ],
                    [
                        'name' => 'Taruna',
                        'min_age' => 17,
                        'max_age' => 23,
                        'sort_order' => 3,
                        'is_active' => true,
                    ],
                    [
                        'name' => 'Senior',
                        'min_age' => 24,
                        'max_age' => null,
                        'sort_order' => 4,
                        'is_active' => true,
                    ],
                ];

                foreach ($developmentLevels as $level) {
                    DevelopmentLevel::firstOrCreate(
                        [
                            'sport_category_id' => $sportCategory->id,
                            'name' => $level['name'],
                        ],
                        $level
                    );
                }
            }
        }
    }
}
