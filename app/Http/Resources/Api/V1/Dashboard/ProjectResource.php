<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use ArrayAccess;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray($request): array
    {
        $this->load("users");
        return [
            "id" => $this->id,
            "name" => $this->name,
            "description" => $this->description,
            "deadline" => $this->deadline,
            "status" => $this->status,
            "user_id" => $this->user_id,
           "users" =>$this->users,
          
            'created_at' => $this->created_at,
          
        ];
    }
}
