<?php

namespace App\Http\Resources\Api\V1\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Api\V1\Dashboard\CityResource;

class UserClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data =  parent::toArray($request);

        $city["city"] = new CityResource($this->whenLoaded('city'));

        return array_merge($data , $city);
    }
}
