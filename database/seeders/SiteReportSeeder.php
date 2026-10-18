<?php

namespace Database\Seeders;

use App\Enum\SiteReportTypeEnum;
use App\Models\Resource;
use App\Models\Site;
use App\Models\SiteReport;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SiteReportSeeder extends Seeder
{
    public function run(): void
    {
        $sites = Site::withoutGlobalScopes()->with('users')->get();
        $users = User::query()->get();

        if ($sites->isEmpty()) {
            throw new RuntimeException('No sites found. Run SiteSeeder before SiteReportSeeder.');
        }

        if ($users->isEmpty()) {
            throw new RuntimeException('No users found. Run UserSeeder before SiteReportSeeder.');
        }

        $resourceIds = Resource::query()->pluck('id');
        $supplierIds = Supplier::query()->pluck('id');
        $faker = fake();

        foreach ($sites as $site) {
            $siteUsers = $site->users;

            if ($siteUsers->isEmpty()) {
                $siteUsers = $users;
            }

            foreach (SiteReportTypeEnum::cases() as $type) {
                if (SiteReport::query()
                    ->where('site_id', $site->id)
                    ->where('report_type', $type->value)
                    ->exists()) {
                    continue;
                }

                $hasCost = $type !== SiteReportTypeEnum::ISSUES;
                $hasResource = in_array($type, [
                    SiteReportTypeEnum::MATERIALS,
                    SiteReportTypeEnum::EQUIPMENT,
                ], true);

                SiteReport::query()->create([
                    'site_id' => $site->id,
                    'user_id' => $siteUsers->random()->id,
                    'report_type' => $type,
                    'resource_id' => $hasResource && $resourceIds->isNotEmpty()
                        ? $resourceIds->random()
                        : null,
                    'quantity' => $hasCost ? (string) $faker->numberBetween(1, 100) : null,
                    'price' => $hasCost
                        ? number_format($faker->randomFloat(2, 10, 10000), 2, '.', '')
                        : null,
                    'tax' => $hasCost ? (string) $faker->numberBetween(0, 15) : null,
                    'supplier_id' => $type === SiteReportTypeEnum::MATERIALS && $supplierIds->isNotEmpty()
                        ? $supplierIds->random()
                        : null,
                    'report_content' => match ($type) {
                        SiteReportTypeEnum::LABOR => $faker->numberBetween(3, 20)
                            . ' workers completed ' . strtolower($faker->sentence()),
                        SiteReportTypeEnum::MATERIALS => 'Delivered materials: ' . $faker->sentence(),
                        SiteReportTypeEnum::EQUIPMENT => 'Equipment update: ' . $faker->sentence(),
                        SiteReportTypeEnum::ISSUES => 'Site issue: ' . $faker->sentence(),
                    },
                    'notes' => $faker->optional()->sentence(),
                ]);
            }
        }
    }
}
