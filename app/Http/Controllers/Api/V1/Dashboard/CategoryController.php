<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Dashboard\CategoryRequest;
use App\Http\Resources\Api\V1\Dashboard\CategoryResource;
use App\Models\Category ;
use App\Repositories\Contracts\CategoryContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class CategoryController extends BaseApiController
{
    /**
     * CategoryController constructor.
     * @param CategoryContract $repository
     */
    public function __construct(CategoryContract $repository)
    {
        parent::__construct($repository, CategoryResource::class, 'categories');
    }

    /**
     * @param CategoryRequest $request
     * @return JsonResponse
     */
    public function store(CategoryRequest $request): JsonResponse
    {
        $Category = $this->repository->create($request->validated());
        return $this->respondWithSuccess(__('Category added successfully'), [
            'Category' => new CategoryResource($Category),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param Category $Category
     * @return JsonResponse
     */
    public function show(Category $Category): JsonResponse
    {
        return $this->respondWithSuccess(__('Category details'), [
            'Category' => (new CategoryResource($Category)),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param CategoryRequest $request
     * @param Category $Category
     * @return JsonResponse
     */
    public function update(CategoryRequest $request, Category $Category): JsonResponse
    {
        $Category = $this->repository->update($Category, $request->all());
        return $this->respondWithSuccess(__('Category updated successfully'), [
            'Category' => (new CategoryResource($Category)),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Category $Category
     *
     * @return JsonResponse
     */
    public function destroy(Category $Category): JsonResponse
    {
        $this->repository->remove($Category);
        return $this->respondWithSuccess(trans('messages.responses.deleted'));
    }


}
