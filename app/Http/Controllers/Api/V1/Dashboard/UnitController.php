<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Dashboard\UnitRequest;
use App\Http\Resources\Api\V1\Dashboard\UnitResource;
use App\Models\Unit ;
use App\Repositories\Contracts\UnitContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class UnitController extends BaseApiController
{
    /**
     * UnitController constructor.
     * @param UnitContract $repository
     */
    public function __construct(UnitContract $repository)
    {
        parent::__construct($repository, UnitResource::class, 'units');
    }

    /**
     * @param UnitRequest $request
     * @return JsonResponse
     */
    public function store(UnitRequest $request): JsonResponse
    {
        $Unit = $this->repository->create($request->validated());
        return $this->respondWithSuccess(__('Unit added successfully'), [
            'Unit' => new UnitResource($Unit->refresh()),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param Unit $Unit
     * @return JsonResponse
     */
    public function show(Unit $Unit): JsonResponse
    {
        return $this->respondWithSuccess(__('Unit details'), [
            'Unit' => (new UnitResource($Unit)),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UnitRequest $request
     * @param Unit $Unit
     * @return JsonResponse
     */
    public function update(UnitRequest $request, Unit $Unit): JsonResponse
    {
        $Unit = $this->repository->update($Unit, $request->all());
        return $this->respondWithSuccess(__('Unit updated successfully'), [
            'Unit' => (new UnitResource($Unit)),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Unit $Unit
     *
     * @return JsonResponse
     */
    public function destroy(Unit $Unit): JsonResponse
    {
        $this->repository->remove($Unit);
        return $this->respondWithSuccess(trans('messages.responses.deleted'));
    }


    public function toggleActive(Unit $Unit): JsonResponse
    {
        $Unit = $this->repository->toggleActive($Unit);
        return $this->respondWithSuccess(__('Unit updated successfully'), [
            'Unit' => (new UnitResource($Unit)),
        ]);
    }

}
