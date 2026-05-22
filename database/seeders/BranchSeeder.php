<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Lago',
                'slug' => 'lago',
            ],
            [
                'name' => 'Ajusco',
                'slug' => 'ajusco',
            ],
            [
                'name' => 'Diana',
                'slug' => 'diana',
            ],
            [
                'name' => 'Cecilia',
                'slug' => 'cecilia',
            ],
        ];

        foreach ($branches as $branch) {

            Branch::updateOrCreate(
                [
                    'slug' => $branch['slug']
                ],
                [
                    'name' => $branch['name'],
                    'active' => true,
                ]
            );
        }
    }
}