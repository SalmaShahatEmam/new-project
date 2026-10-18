<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        $this->call([
            SettingSeeder::class,
            PermissionSeeder::class,
            SitesAdminPermissionSeeder::class,
            EstimationEngineerPermissionSeeder::class,
            PurchaseEngineerPermissionSeeder::class,
            SiteEngineerPermissionSeeder::class,
            LocationSeeder::class,
            UserSeeder::class,
            SiteSeeder::class,
            SiteReportSeeder::class,
        ]);
        Schema::enableForeignKeyConstraints();
    }
}
