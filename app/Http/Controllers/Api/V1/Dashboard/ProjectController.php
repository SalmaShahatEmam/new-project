<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Dashboard\ProjectRequest;
use App\Http\Requests\Api\V1\Dashboard\ProjectResourceRequest;
use App\Http\Resources\Api\V1\Dashboard\ProjectResource;
use App\Http\Resources\Api\V1\Dashoard\ProjectResourcesResource;
use App\Models\Project ;
use App\Models\Resource;
use App\Repositories\Contracts\ProjectContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class ProjectController extends BaseApiController
{
    /**
     * ProjectController constructor.
     * @param ProjectContract $repository
     */
    public function __construct(ProjectContract $repository)
    {
        parent::__construct($repository, ProjectResource::class, 'projects');
    }

    /**
     * @param ProjectRequest $request
     * @return JsonResponse
     */
    public function store(ProjectRequest $request): JsonResponse
    {
        $Project = $this->repository->create($request->all());

        if($request->has('Structural_id') ){
            $Project->users()->create([
                'engineer_id' => $request->Structural_id,
                'type' => 'Structural'
            ]);
        }
        if($request->has('Architectural_id') ){
            $Project->users()->create([
                'engineer_id' => $request->Architectural_id,
                'type' => 'Architectural'
            ]);
        }
        if($request->has('Electrical_id') ){
            $Project->users()->create([
                'engineer_id' => $request->Electrical_id,
                'type' => 'Electrical'
            ]);
        }
        if($request->has('Mechanical_id') ){
            $Project->users()->create([
                'engineer_id' => $request->Mechanical_id,
                'type' => 'Mechanical'
            ]);
        }
        
        return $this->respondWithSuccess(__('Project added successfully'), [
            'Project' => new ProjectResource($Project),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param Project $Project
     * @return JsonResponse
     */
    public function show(Project $Project): JsonResponse
    {
        return $this->respondWithSuccess(__('Project details'), [
            'Project' => (new ProjectResource($Project)),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param ProjectRequest $request
     * @param Project $Project
     * @return JsonResponse
     */
    public function update(ProjectRequest $request, Project $Project): JsonResponse
    {
        $Project = $this->repository->update($Project, $request->all());
        return $this->respondWithSuccess(__('Project updated successfully'), [
            'Project' => (new ProjectResource($Project)),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Project $Project
     *
     * @return JsonResponse
     */
    public function destroy(Project $Project): JsonResponse
    {
        $this->repository->remove($Project);
        return $this->respondWithSuccess(trans('messages.responses.deleted'));
    }


    public function addResource(ProjectResourceRequest $request, Project $Project): JsonResponse
    {

    /**
        $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('resource_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('project_engineer_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('quantity')->default(1);
            $table->string('unit')->nullable();
            $table->string('price');
            $table->string('supply_location')->nullable();
            $table->timestamps();
     */
        $resource = Resource::find($request->resource_id);

        $Project->resources()->create([
            'resource_id' => $resource->id,
            'quantity' => $request->quantity,
            'supply_location' => $request->location_id,
            "user_id" => auth()->id(),
            "unit" => $resource->unit,
            "price" => $resource?->price ?? null,
        ]);

        return $this->respondWithSuccess(__('Project updated successfully'), [
            'resources' => ProjectResourcesResource::collection($Project->resources()->where("user_id", auth()->id())->get()),
        ]);
    }
}
