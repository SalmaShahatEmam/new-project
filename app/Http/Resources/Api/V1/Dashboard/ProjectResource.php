<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'deadline' => $this->end_date,
            'status' => $this->status,
            'user_id' => $this->user_id,
            'total_project_cost' => $this->total_project_cost,
            'total_project_materials' => $this->total_project_materials,
            'total_price_structural_with_tax' => $this->total_price_structural,
            'total_price_architectural_with_tax' => $this->total_price_architectural,
            'total_price_electrical_with_tax' => $this->total_price_electrical,
            'total_price_mechanical_with_tax' => $this->total_price_mechanical,
            'total_tax_structural' => $this->total_tax_structural,
            'total_tax_architectural' => $this->total_tax_architectural,
            'total_tax_electrical' => $this->total_tax_electrical,
            'total_tax_mechanical' => $this->total_tax_mechanical,
            'total_price_structural_before_tax' => $this->total_price_structural_before_tax,
            'total_price_architectural_before_tax' => $this->total_price_architectural_before_tax,
            'total_price_electrical_before_tax' => $this->total_price_electrical_before_tax,
            'total_price_mechanical_before_tax' => $this->total_price_mechanical_before_tax,
            'all_engineers_assigned' => $this->allEngineersAssigned(),

            'users' => $this->whenLoaded('users'),
            'resources' => ProjectMaterialResource::collection(
                $this->whenLoaded('resources')
            ),
            'reject_message' => $this->reject_message,
            'reopen_reason' => $this->reopen_reason,
            'total_price' => $this->total_price,
            'created_at' => $this->created_at,
        ];
    }
}
