<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        $this->loadMissing('parent');
        return [
            'id' => $this->id,
            "name" => $this->name,
            'parent_id' => $this->parent_id,
            "parent_cat" => $this->whenLoaded('parent', function () {
                return [
                    'id' => $this->parent->id,
                    'name' => $this->parent->name,
                ];
            }),
            'created_at' => $this->created_at,
        ];
    }
}
