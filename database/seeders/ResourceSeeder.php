<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Resource;
use App\Models\resourcePrice;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ResourceSeeder extends Seeder
{protected int $defaultUpdatedBy = 1;
    protected string $locationCode = 'DEFAULT';
 
    protected int $created = 0;
    protected int $skippedExisting = 0;
    protected int $skippedMissingResource = 0;
 
    public function run(): void
    {
        $filePath = database_path('seeders/data/resources_array.php');
 
        if (!file_exists($filePath)) {
            $this->command->error("File not found: {$filePath}");
            return;
        }
 
        $location = Location::where('code', $this->locationCode)->first();
 
        if (!$location) {
            $this->command->error("No location found with code '{$this->locationCode}'.");
            $this->command->line('Run: SELECT id, code FROM locations; and either create one with that code, or change $locationCode / pass the id directly below.');
            return;
        }
 
        $items = include $filePath;
 
        if (!is_array($items)) {
            $this->command->error('resources_array.php did not return an array (got: ' . gettype($items) . ').');
            return;
        }
 
        foreach ($items as $item) {
            if ($item['price'] === null) {
                continue;
            }
 
            $resource = Resource::where('resource_code', $item['resource_code'])->first();
 
            if (!$resource) {
                $this->skippedMissingResource++;
                continue;
            }
 
            $alreadyPriced = resourcePrice::where('resource_id', $resource->id)
                ->where('location_id', $location->id)
                ->where('is_current', true)
                ->exists();
 
            if ($alreadyPriced) {
                $this->skippedExisting++;
                continue;
            }
 
            resourcePrice::create([
                'resource_id' => $resource->id,
                'location_id' => $location->id,
                'price' => $item['price'],
                'currency' => 'SAR',
                'effective_date' => now()->toDateString(),
                'updated_by' => $this->defaultUpdatedBy,
                'is_current' => true,
            ]);
 
            $this->created++;
        }
 
        $this->command->info("Prices — created: {$this->created}, already existed (skipped): {$this->skippedExisting}, resource not found (skipped): {$this->skippedMissingResource}.");
    }
    
}
