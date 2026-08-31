<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations=[
               ['code' => 'QSM', "name" =>[ 'en' => 'Qassim', 'ar' => 'القصيم']],
        ['code' => 'NJR', "name" =>[ 'en' => 'Najran', 'ar' => 'نجران']],
        ['code' => 'JZN', "name" =>[ 'en' => 'Jazan', 'ar' => 'جازان']],
        ['code' => 'BHA', "name" =>[ 'en' => 'Al-Baha', 'ar' => 'الباحة']]
        ];

    foreach ($locations as $location) {
        Location::create([
            'code' => $location['code'],
            'name' => [
                'en' => $location['en'],
                'ar' => $location['ar'],
            ],
        ]);
    }
    }
}
