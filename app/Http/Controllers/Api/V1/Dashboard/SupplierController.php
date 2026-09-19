<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Dashboard\SupplierRequest;
use App\Http\Resources\Api\V1\Dashboard\SupplierResource;
use App\Models\Supplier ;
use App\Repositories\Contracts\SupplierContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class SupplierController extends BaseApiController
{
    /**
     * SupplierController constructor.
     * @param SupplierContract $repository
     */
    public function __construct(SupplierContract $repository)
    {
        parent::__construct($repository, SupplierResource::class, 'suppliers');
    }

    /**
     * @param SupplierRequest $request
     * @return JsonResponse
     */
    public function store(SupplierRequest $request): JsonResponse
    {
        $Supplier = $this->repository->create($request->validated());
        return $this->respondWithSuccess(__('Supplier added successfully'), [
            'Supplier' => new SupplierResource($Supplier),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param Supplier $Supplier
     * @return JsonResponse
     */
    public function show(Supplier $Supplier): JsonResponse
    {
        return $this->respondWithSuccess(__('Supplier details'), [
            'Supplier' => (new SupplierResource($Supplier)),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param SupplierRequest $request
     * @param Supplier $Supplier
     * @return JsonResponse
     */
    public function update(SupplierRequest $request, Supplier $Supplier): JsonResponse
    {
        $Supplier = $this->repository->update($Supplier, $request->all());
        return $this->respondWithSuccess(__('Supplier updated successfully'), [
            'Supplier' => (new SupplierResource($Supplier)),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Supplier $Supplier
     *
     * @return JsonResponse
     */
    public function destroy(Supplier $Supplier): JsonResponse
    {
        $this->repository->remove($Supplier);
        return $this->respondWithSuccess(trans('messages.responses.deleted'));
    }

    public function toggleActive(Supplier $Supplier): JsonResponse
    {
        $Supplier = $this->repository->toggleActive($Supplier);
        return $this->respondWithSuccess(__('Supplier status updated successfully'), [
            'Supplier' => (new SupplierResource($Supplier)),
        ]);
    }
}
