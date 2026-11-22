<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectMaterialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $quantity = (float) $this->quantity;
        $price = (float) $this->price;
        $priceAfterTax = $this->price_after_tax !== null
            ? (float) $this->price_after_tax
            : $price + ($price * (float) $this->tax / 100);

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'engineer_id' => $this->engineer_id,
            'resource_id' => $this->resource_id,
            'code' => $this->resource_code,
            'quantity' => $this->quantity,
            'unit' => $this->r_unit,
            'price' => $this->price,
            'tax' => $this->tax,
            'price_after_tax' => $priceAfterTax,
            'total_price_with_tax' => $priceAfterTax * $quantity,
            'total_before_tax' => $price * $quantity,
            'total_tax' => ($priceAfterTax - $price) * $quantity,

            'location_id' => $this->supply_location,
            'location' => $this->location ? [
                'id' => $this->location->id,
                'name' => $this->location->name,
            ] : null,
            'section_name' => $this->section_name,
            'resource_name' => $this->resource_name,
            'note' => $this->note,
        ];
    }
}
