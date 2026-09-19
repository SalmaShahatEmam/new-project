<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class ResourceResource extends JsonResource
{
    public function toArray($request): array
    {
        $this->load([
            'category',
            'unit',
            'resourcePrices',

        ]);

        return [
            'id' => $this->id,
            'resource_code' => $this->resource_code,
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ];
            }),
            'unit_id' => $this->unit_id,
            'unit' => $this->whenLoaded('unit', function () {
                return [
                    'id' => $this->unit->id,
                    'name' => $this->unit->name,
                ];
            }),
            'prices' => $this->whenLoaded('resourcePrices', function () {
                return $this->resourcePrices->map(function ($resourcePrice) {
                    return [
                        'id' => $resourcePrice->id,
                        'price' => $resourcePrice->price,
                        'location_id' => $resourcePrice->location_id,
                        'location_name' => $resourcePrice?->location?->name,
                    ];
                });
            }),
            'locations' => $this->whenLoaded('resourcePrices', function () {
    return $this->resourcePrices
        ->load('location')
        ->pluck('location.name')
        ->filter()
        ->unique()
        ->values();
}),

            'unit_of_measure' => $this->unit_of_measure,
            'active' => $this->active,

            'created_at' => $this->created_at,
        ];
    }
}
