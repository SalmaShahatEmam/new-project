<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    public function toArray($request): array
    {
        $this->loadMissing(['location', 'project', 'users']);
        $this->loadCount([
            'reports as site_report_labour' => fn ($query) => $query->where('report_type', 'labor'),
            'reports as site_report_material' => fn ($query) => $query->where('report_type', 'materials'),
            'problems as site_problem_count',
            'reports as site_equipment' => fn ($query) => $query->where('report_type', 'equipment'),
        ]);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'contract_date' => $this->contract_date,
            'duration_in_months' => $this->duration_in_months,
            'location_id' => $this->location_id,
            'status' => $this->status,
            'location' => $this->location ? [
                'id' => $this->location->id,
                'name' => $this->location->name,
            ] : null,
            'project_id' => $this->project_id,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ] : null,
            'users' => $this->users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'type' => $user->type,
            ]),
            'created_at' => $this->created_at,
            'site_report_count' => $this->site_report_count,
            'site_problem_count' => $this->site_problem_count,
            'site_report_labour_count' => $this->site_report_labour,
            'site_report_material_count' => $this->site_report_material,
            'site_equipment_count' => $this->site_equipment,
        ];
    }
}
