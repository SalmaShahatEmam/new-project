<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class ResourceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            "resource_code" => $this->resource_code,
            "name" => $this->name,
            "description" => $this->description,
            "category_id" => $this->category_id,
            "category" => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ];
            }),
        "unit_of_measure" => $this->unit_of_measure,

            'created_at' => $this->created_at,
        ];
    }
}
