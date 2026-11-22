<?php

namespace App\Http\Resources\Ai;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'metadata' => $this->metadata,
            'messages_count' => $this->whenCounted('messages', $this->messages_count),
            'latest_message' => $this->whenLoaded('messages', function () {
                $last = $this->messages->last();
                return $last ? [
                    'id' => $last->id,
                    'role' => $last->role,
                    'content' => $last->content,
                    'created_at' => $last->created_at?->toISOString(),
                ] : null;
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
