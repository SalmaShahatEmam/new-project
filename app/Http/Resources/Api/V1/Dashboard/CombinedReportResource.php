<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class CombinedReportResource extends JsonResource
{
    public function toArray($request): array
    {
        $this->loadMissing('site');

        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'site' => $this->site ? [
                'id' => $this->site->id,
                'name' => $this->site->getTranslation('name', app()->getLocale()),
            ] : null,
            'from_date' => $this->from_date?->format('Y-m-d'),
            'to_date' => $this->to_date?->format('Y-m-d'),
            'name' => $this->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
