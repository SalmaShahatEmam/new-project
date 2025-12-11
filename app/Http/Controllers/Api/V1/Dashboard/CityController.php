<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Dashboard\CityRequest;
use App\Http\Resources\Api\V1\Dashboard\CityResource;
use App\Models\City ;
use App\Repositories\Contracts\CityContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class CityController extends BaseApiController
{
    /**
     * CityController constructor.
     * @param CityContract $repository
     */
    public function __construct(CityContract $repository)
    {
        parent::__construct($repository, CityResource::class, 'cities');
    }

    /**
     * @param CityRequest $request
     * @return JsonResponse
     */
    public function store(CityRequest $request): JsonResponse
    {
        $City = $this->repository->create($request->validated());
        return $this->respondWithSuccess(__('City added successfully'), [
            'City' => new CityResource($City),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param City $City
     * @return JsonResponse
     */
    public function show(City $City): JsonResponse
    {
        return $this->respondWithSuccess(__('City details'), [
            'City' => (new CityResource($City)),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param CityRequest $request
     * @param City $City
     * @return JsonResponse
     */
    public function update(CityRequest $request, City $City): JsonResponse
    {
        $City = $this->repository->update($City, $request->all());
        return $this->respondWithSuccess(__('City updated successfully'), [
            'City' => (new CityResource($City)),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param City $City
     *
     * @return JsonResponse
     */
    public function destroy(City $City): JsonResponse
    {
        $this->repository->remove($City);
        return $this->respondWithSuccess(trans('messages.responses.deleted'));
    }


    public function toggleCityStatus(City $city)
    {
        
        $city->is_active = !(bool)$city->is_active;
        $city->save();

        // TODO : مع مراعاة:لا يمكن تعطيل مدينة مرتبط بها مستخدم اواعلان
        return $this->respondWithSuccess(__('City status updated successfully'), [
            'city' => new CityResource($city),
        ]);
    }

}
