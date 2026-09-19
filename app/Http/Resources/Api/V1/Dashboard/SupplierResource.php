<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            "name" => $this->name,
            "contact" => $this->contact,
            "map_link" => $this->map_link,
            "status" => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
