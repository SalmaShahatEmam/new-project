<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SiteProblemResource extends JsonResource
{
    public function toArray($request): array
    {
        $this->loadMissing(['site', 'user', 'founder', 'assignee']);

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
            'founded_by' => $this->founded_by,
            'founder' => $this->founder ? [
                'id' => $this->founder->id,
                'name' => $this->founder->name,
            ] : null,
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null,
            'explanation' => $this->explanation,
            'causes' => $this->causes,
            'notes' => $this->notes,
            'status' => $this->status,
            'priority' => $this->priority,
            'suggested_solutions' => $this->suggested_solutions ?? [],
            'attachments' => collect($this->attachments ?? [])->map(fn (array $attachment) => [
                ...$attachment,
                'url' => Storage::disk('public')->url($attachment['path']),
            ]),
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
