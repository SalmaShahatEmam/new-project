<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray($request): array
    {
        $this->loadMissing(['site', 'user', 'resource', 'supplier']);

        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'site' => $this->site ? [
                'id' => $this->site->id,
                'name' => $this->translateValue($this->site->name),
            ] : null,
            'user_id' => $this->user_id,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'report_type' => $this->report_type?->value ?? $this->report_type,
            'resource_id' => $this->resource_id,
            'resource' => $this->resource ? [
                'id' => $this->resource->id,
                'name' => $this->translateValue($this->resource->name),
            ] : null,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'tax' => $this->tax,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->translateValue($this->supplier->name),
            ] : null,
            'report_content' => $this->report_content,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function translateValue($value): ?string
    {
        if (is_array($value)) {
            return $value['en'] ?? $value['ar'] ?? null;
        }

        return $value;
    }
}
