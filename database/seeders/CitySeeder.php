<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cities = [
            [
                'name' => [
                    'en' => 'Riyadh',
                    'ar' => 'الرياض'
                ],
            ],
            [
                'name' => [
                    'en' => 'Jeddah',
                    'ar' => 'جدة'
                ],
            ],
            [
                'name' => [
                    'en' => 'makkah',
                    'ar' => 'مكة المكرمة'
                ],
            ],
        ];

        foreach ($cities as $city) {
            \App\Models\City::create($city);
        }
    }
}
