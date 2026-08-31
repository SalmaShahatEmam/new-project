<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Dashboard\LocationRequest;
use App\Http\Resources\Api\V1\Dashboard\LocationResource;
use App\Models\Location ;
use App\Repositories\Contracts\LocationContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class LocationController extends BaseApiController
{
    /**
     * LocationController constructor.
     * @param LocationContract $repository
     */
    public function __construct(LocationContract $repository)
    {
        parent::__construct($repository, LocationResource::class, 'locations');
    }

    /**
     * @param LocationRequest $request
     * @return JsonResponse
     */
    public function store(LocationRequest $request): JsonResponse
    {
        $Location = $this->repository->create($request->validated());
        return $this->respondWithSuccess(__('Location added successfully'), [
            'Location' => new LocationResource($Location),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param Location $Location
     * @return JsonResponse
     */
    public function show(Location $Location): JsonResponse
    {
        return $this->respondWithSuccess(__('Location details'), [
            'Location' => (new LocationResource($Location)),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param LocationRequest $request
     * @param Location $Location
     * @return JsonResponse
     */
    public function update(LocationRequest $request, Location $Location): JsonResponse
    {
        $Location = $this->repository->update($Location, $request->all());
        return $this->respondWithSuccess(__('Location updated successfully'), [
            'Location' => (new LocationResource($Location)),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Location $Location
     *
     * @return JsonResponse
     */
    public function destroy(Location $Location): JsonResponse
    {
        $this->repository->remove($Location);
        return $this->respondWithSuccess(trans('messages.responses.deleted'));
    }


}
