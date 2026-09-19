<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            "name" => $this->name,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
