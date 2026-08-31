<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            "code" => $this->code,
            'name' => $this->name,
            'created_at' => $this->created_at,
        ];
    }
}
