<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Dashboard\ResourcePriceRequest;
use App\Http\Requests\Api\V1\Dashboard\ResourceRequest;
use App\Http\Resources\Api\V1\Dashboard\ResourcePriceResource;
use App\Http\Resources\Api\V1\Dashboard\ResourceResource;
use App\Models\Resource ;
use App\Models\resourcePrice;
use App\Repositories\Contracts\ResourceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
        do {
            $resourceCode = 'R-' . random_int(1000, 9999);
        } while (Resource::where('resource_code', $resourceCode)->exists());

        $data = $request->validated();
        $data['resource_code'] = $resourceCode;

        $Resource = $this->repository->create($data);
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
        DB::transaction(function () use ($Resource): void {
            $Resource->resourcePrices()->delete();
            $this->repository->remove($Resource);
        });

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
        $price = $this->repository->storePrices($Resource, $request->all());
        $price->load('updatedBy', 'location' , 'supplier')->refresh();
        return $this->respondWithSuccess(__('Resource prices added successfully'), [
            "resource_data" => new ResourceResource($Resource),
            'prices_history' => ResourcePriceResource::make($price),
        ]);
    }

    /**
     * List all resource prices created by the authenticated user.
     */
    public function myPrices(): JsonResponse
    {
        $prices = resourcePrice::query()
            ->with(['resource.category', 'location', 'supplier', 'updatedBy'])
            ->where('updated_by', auth()->id())
            ->latest()
            ->get();

        $price_count = $prices->count();
        return $this->respondWithSuccess(__('My prices'), [
            "price_count" => $price_count,
            'prices' => ResourcePriceResource::collection($prices),
        ]);
    }

    public function toggleActive(Resource $Resource): JsonResponse
    {
        $Resource->active = !$Resource->active;
        $Resource->save();
        return $this->respondWithSuccess(__('Resource status updated successfully'), [
            "resource_data" => new ResourceResource($Resource),
        ]);
    }

}
