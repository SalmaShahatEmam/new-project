<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Dashboard\ResourcePriceRequest;
use App\Http\Requests\Api\V1\Dashboard\ResourceRequest;
use App\Http\Resources\Api\V1\Dashboard\ResourcePriceResource;
use App\Http\Resources\Api\V1\Dashboard\ResourceResource;
use App\Models\Resource ;
use App\Repositories\Contracts\ResourceContract;
use Illuminate\Http\JsonResponse;

class ResourceController extends BaseApiController
{
    /**
     * ResourceController constructor.
     * @param ResourceContract $repository
     */
    public function __construct(ResourceContract $repository)
    {
        parent::__construct($repository, ResourceResource::class, 'resources');
    }

    /**
     * @param ResourceRequest $request
     * @return JsonResponse
     */
    public function store(ResourceRequest $request): JsonResponse
    {
        $Resource = $this->repository->create($request->validated());
        return $this->respondWithSuccess(__('Resource added successfully'), [
            'Resource' => new ResourceResource($Resource),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param Resource $Resource
     * @return JsonResponse
     */
    public function show(Resource $Resource): JsonResponse
    {
        return $this->respondWithSuccess(__('Resource details'), [
            'Resource' => (new ResourceResource($Resource)),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param ResourceRequest $request
     * @param Resource $Resource
     * @return JsonResponse
     */
    public function update(ResourceRequest $request, Resource $Resource): JsonResponse
    {
        $Resource = $this->repository->update($Resource, $request->all());
        return $this->respondWithSuccess(__('Resource updated successfully'), [
            'Resource' => (new ResourceResource($Resource)),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Resource $Resource
     *
     * @return JsonResponse
     */
    public function destroy(Resource $Resource): JsonResponse
    {
        $this->repository->remove($Resource);
        return $this->respondWithSuccess(trans('messages.responses.deleted'));
    }


    public function getPrices(Resource $Resource): JsonResponse
    {
        $prices = $Resource->resourcePrices()->get();
        return $this->respondWithSuccess(__('Resource prices'), [
            "resource_data" => new ResourceResource($Resource),
            'prices_history' => ResourcePriceResource::collection($prices),
        ]);
    }


    public function storePrices(ResourcePriceRequest $request, Resource $Resource): JsonResponse
    {
        $this->repository->storePrices($Resource, $request->all());
        return $this->respondWithSuccess(__('Resource prices updated successfully'), [
            "resource_data" => new ResourceResource($Resource),
        ]);
    }

}
