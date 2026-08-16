<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Ajusco',
                'slug' => 'ajusco',
                'color' => '#dc2626',
                'street' => 'Av. Aztecas',
                'external_number' => '270',
                'postal_code' => '04300',
                'neighborhood' => 'Ajusco',
                'municipality' => 'Coyoacán',
                'address_state' => 'Ciudad de México',
                'attendance_latitude' => 19.31918,
                'attendance_longitude' => -99.16152,
            ],
            [
                'name' => 'Cecilia',
                'slug' => 'cecilia',
                'color' => '#db2777',
                'street' => 'Calle Cecilia',
                'external_number' => '42',
                'postal_code' => '03510',
                'neighborhood' => 'Nativitas',
                'municipality' => 'Benito Juárez',
                'address_state' => 'Ciudad de México',
                'attendance_latitude' => 19.37901,
                'attendance_longitude' => -99.14096,
            ],
            [
                'name' => 'Diana',
                'slug' => 'diana',
                'color' => '#7c3aed',
                'street' => 'Río Lerma',
                'external_number' => '156',
                'postal_code' => '06500',
                'neighborhood' => 'Cuauhtémoc',
                'municipality' => 'Cuauhtémoc',
                'address_state' => 'Ciudad de México',
                'attendance_latitude' => 19.42542,
                'attendance_longitude' => -99.17187,
            ],
            [
                'name' => 'Lago',
                'slug' => 'lago',
                'color' => '#0284c7',
                'street' => 'Lago Alberto',
                'external_number' => '320',
                'postal_code' => '11320',
                'neighborhood' => 'Anáhuac',
                'municipality' => 'Miguel Hidalgo',
                'address_state' => 'Ciudad de México',
                'attendance_latitude' => 19.44393,
                'attendance_longitude' => -99.18418,
            ],
        ];

        foreach ($branches as $branch) {
            $address = "{$branch['street']} {$branch['external_number']}, {$branch['neighborhood']}, {$branch['municipality']}, {$branch['address_state']}";

            Branch::updateOrCreate(
                ['slug' => $branch['slug']],
                array_merge($branch, [
                    'address' => $address,
                    'maps_url' => 'https://maps.google.com/?q=' . rawurlencode($address),
                    'attendance_geofence_radius_meters' => 120,
                    'active' => true,
                ])
            );
        }
    }
}
