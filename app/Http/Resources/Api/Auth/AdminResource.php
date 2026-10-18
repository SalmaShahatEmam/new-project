<?php

namespace App\Http\Resources\Api\Auth;

use Illuminate\Http\Request;

class AdminResource extends UserResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data =  parent::toArray($request);
        $data['role'] = $this->getRoleNames();
        $data['status'] = $this->status;
        return $data;
    }
}
