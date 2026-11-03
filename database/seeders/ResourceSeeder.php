<?php

namespace Database\Seeders;

use App\Models\Resource;
use Illuminate\Database\Seeder;

class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = database_path('seeders/data/arcticture.php');

        if (!is_file($filePath)) {
            $this->command->error("File not found: {$filePath}");
            return;
        }

        $items = require $filePath;

        if (!is_array($items)) {
            $this->command->error('material.php did not return an array (got: ' . gettype($items) . ').');
            return;
        }

        $created = 0;
        $existing = 0;

        foreach ($items as $item) {
            $resource = Resource::firstOrCreate(
                ['resource_code' => $item['resource_code']],
                [
                    'category_id' => $item['category_id'],
                    'name' => $item['name'] ?? [
                        'en' => $item['resource_code'],
                        'ar' => $item['resource_code'],
                    ],
                    'description' => $item['description'] ?? null,
                    'unit_id' => $item['unit_id'] ?? null,
                ]
            );

            if ($resource->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        $this->command->info("Resources — created: {$created}, already existed (skipped): {$existing}.");
    }
}
