<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SiteSeeder extends Seeder
{
    public function run(): void
    {
        $locations = Location::query()->get();
        $users = User::query()->get();

        if ($locations->isEmpty()) {
            throw new RuntimeException('No locations found. Run LocationSeeder before SiteSeeder.');
        }

        if ($users->isEmpty()) {
            throw new RuntimeException('No users found. Run UserSeeder before SiteSeeder.');
        }

        $faker = fake();

        foreach (range(1, 8) as $index) {
            $user = $users->random();
            $site = Site::withoutGlobalScopes()->firstOrCreate(
                ['name->ar' => 'موقع إنشاءات ' . $index],
                [
                    'name' => [
                        'en' => $faker->company() . ' Construction Site',
                        'ar' => 'موقع إنشاءات ' . $index,
                    ],
                    'description' => [
                        'en' => $faker->paragraph(),
                        'ar' => 'مشروع إنشاءات تجريبي',
                    ],
                    'contract_date' => $faker->dateTimeBetween('-2 years', 'now'),
                    'duration_in_months' => (string) $faker->numberBetween(3, 24),
                    'location_id' => $locations->random()->id,
                ]
            );

            $site->users()->syncWithoutDetaching([$user->id]);
        }
    }
}
