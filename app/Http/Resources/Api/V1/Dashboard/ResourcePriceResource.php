<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourcePriceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       
        return [
            "resource_basics" => [
                "name" => $this->resource?->resource?->name,
                "category" => $this->resource?->resource?->category->name,
            ],
            'id' => $this->id,
            'resource_id' => $this->resource_id,
            'price' => $this->price,
            'currency' => $this->currency,
            "location_id" => $this->location_id,
            "location_name" => $this->location?->name,
            "effective_date" => $this->effective_date,
            "tax_value" => $this->tax_value,
            "price_after_tax" => $this->price_after_tax,
            "supplier_id" => $this->supplier_id,
            "supplier_name" => $this->supplier?->name,
            "supplier_map_link" => $this->supplier?->map_link,
           
                    'updated_by_id' => $this->updatedBy->id,
                    'updated_by_name' => $this->updatedBy->name,
             
            'created_at' => $this->created_at,
        ];
    }
}
