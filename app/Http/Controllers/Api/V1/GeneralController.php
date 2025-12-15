<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\City;

use App\Traits\ApiResponseTrait;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Dashboard\CityResource;

class GeneralController extends Controller
{
    use ApiResponseTrait;

    public function citiesIndex()
    {
        $cities = City::where("is_active" , true)->get();

        return $this->respondWithSuccess(__('Cities list'), [
            'cities' => CityResource::collection($cities),
        ]);
    }


    public function getTermsAndConditions()
    {
        $data['data'] = collect(setting('terms-and-conditions'))->toArray()[app()->getLocale()] ?? '';
        $data['status'] = 200;
        return $this->setStatusCode(200)->respondWithArray($data);
    }
}
