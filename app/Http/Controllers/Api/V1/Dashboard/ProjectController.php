<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Dashboard\ProjectAssign;
use App\Http\Requests\Api\V1\Dashboard\ProjectReopenRequest;
use App\Http\Requests\Api\V1\Dashboard\ProjectRequest;
use App\Http\Requests\Api\V1\Dashboard\ProjectResourceRequest;
use App\Http\Resources\Api\V1\Dashboard\ProjectResource;
use App\Http\Resources\Api\V1\Dashoard\ProjectResourcesResource;
use App\Models\Project;
use App\Models\ProjectResources as Item;
use App\Models\User;
use App\Repositories\Contracts\ProjectContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends BaseApiController
{
    /**
     * ProjectController constructor.
     */
    public function __construct(ProjectContract $repository)
    {
        parent::__construct($repository, ProjectResource::class, 'projects');
        $this->relations = [
            'users',
            'resources.resource',
            'resources.location',
            'resources.projectEngineer',
            'resources.uploader',
        ];

    }

    public function store(ProjectRequest $request): JsonResponse
    {
        $Project = $this->repository->create($request->all());

        if ($request->has('Structural_id')) {
            $Project->users()->create([
                'engineer_id' => $request->Structural_id,
                'type' => 'Structural',
            ]);
        }
        if ($request->has('Architectural_id')) {
            $Project->users()->create([
                'engineer_id' => $request->Architectural_id,
                'type' => 'Architectural',
            ]);
        }
        if ($request->has('Electrical_id')) {
            $Project->users()->create([
                'engineer_id' => $request->Electrical_id,
                'type' => 'Electrical',
            ]);
        }
        if ($request->has('Mechanical_id')) {
            $Project->users()->create([
                'engineer_id' => $request->Mechanical_id,
                'type' => 'Mechanical',
            ]);
        }

        $Project->update([
            'status' => $Project->allEngineersAssigned() ? 'in_progress' : 'pending',
        ]);

        return $this->respondWithSuccess(__('Project added successfully'), [
            'Project' => new ProjectResource($Project),
        ]);
    }

    public function addNote(Request $request, $item): JsonResponse
    {
        $validated = $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        $item = Item::findOrFail($item);
        $item->update(['note' => $validated['note']]);

        return $this->respondWithSuccess(__('Note added successfully'), [
            'item' => new ProjectResourcesResource($item),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $Project): JsonResponse
    {

        $Project->load([
            'users',
            'resources.resource',
            'resources.location',
            'resources.projectEngineer',
            'resources.uploader',
        ]);

        return $this->respondWithSuccess(__('Project details'), [
            'Project' => (new ProjectResource($Project)),
        ]);
    }

    public function copy(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:1000',
        ]);

        $copiedProject = DB::transaction(function () use ($project, $validated) {
            $copy = $project->replicate();

            if (isset($validated['name'])) {
                $copy->name = $validated['name'];
            } else {
                $copyNumber = 1;
                do {
                    $copy->name = $project->name.' (Copy '.$copyNumber.')';
                    $copyNumber++;
                } while (Project::where('name', $copy->name)->exists());
            }

            $copy->description = $validated['description'] ?? $project->description;
            $copy->save();

            foreach ($project->users()->get() as $assignment) {
                $copiedAssignment = $assignment->replicate();
                $copiedAssignment->project_id = $copy->id;
                $copiedAssignment->save();
            }

            foreach ($project->resources()->get() as $resource) {
                $copiedResource = $resource->replicate();
                $copiedResource->project_id = $copy->id;
                $copiedResource->save();
            }

            return $copy->load(['users', 'resources']);
        });

        return $this->respondWithSuccess(__('Project copied successfully'), [
            'Project' => new ProjectResource($copiedProject),
            'resources' => ProjectResourcesResource::collection($copiedProject->resources()->where('user_id', auth()->id())->get()),

        ]);
    }

    /**
     * Update the specified resource in storage.
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
     */
    public function destroy(Project $Project): JsonResponse
    {
        $this->repository->remove($Project);

        return $this->respondWithSuccess(trans('messages.responses.deleted'));
    }

    public function addResource(ProjectResourceRequest $request, Project $Project): JsonResponse
    {
        $userId = auth()->id();

        $user = User::find($userId);


        if ($user->type != 'admin' && ! $Project->users()
            ->where('engineer_id', $userId)
            ->where('active', true)
            ->exists()) {
            return $this->setStatusCode(403)->respondWithError(__('Unauthorized for this user'));
        }

        $engineerId = $request->validated('engineer_id') ?? $userId;

        $Project = $this->repository->addResources(
            $Project,
            $request->validated('section_name'),
            $request->validated('resources'),
            $userId,
            $engineerId
        );

        return $this->respondWithSuccess(__('Project updated successfully'), [
            'project' => new ProjectResource($Project),
            /*  'resources' => ProjectResourcesResource::collection(
                $Project->resources()->where('user_id', $userId)->get()
            ), */
        ]);
    }

    public function destroyResource(Project $project, int $resourceId): JsonResponse
    {
        $userId = auth()->id();

        if (! $userId || ! $project->users()
            ->where('engineer_id', $userId)
            ->where('active', true)
            ->exists()) {
            return $this->setStatusCode(403)->respondWithError(__('Unauthorized'));
        }

        $resource = $project->resources()
            ->where('user_id', $userId)
            ->findOrFail($resourceId);

        $resource->delete();

        return $this->respondWithSuccess(__('Project resource deleted successfully'), [
            'resource_id' => $resourceId,
        ]);
    }

    public function addAssignee(ProjectAssign $request, Project $Project): JsonResponse
    {
        if ($request->has('Structural_id')) {
            $Project->users()->where('type', 'Structural')->update(['active' => false]);
            $Project->users()->create([
                'engineer_id' => $request->Structural_id,
                'type' => 'Structural',
            ]);
        }
        if ($request->has('Architectural_id')) {
            $Project->users()->where('type', 'Architectural')->update(['active' => false]);
            $Project->users()->create([
                'engineer_id' => $request->Architectural_id,
                'type' => 'Architectural',
            ]);
        }
        if ($request->has('Electrical_id')) {
            $Project->users()->where('type', 'Electrical')->update(['active' => false]);
            $Project->users()->create([
                'engineer_id' => $request->Electrical_id,
                'type' => 'Electrical',
            ]);
        }
        if ($request->has('Mechanical_id')) {
            $Project->users()->where('type', 'Mechanical')->update(['active' => false]);
            $Project->users()->create([
                'engineer_id' => $request->Mechanical_id,
                'type' => 'Mechanical',
            ]);
        }

        $Project->update([
            'status' => $Project->allEngineersAssigned() ? 'in_progress' : 'pending',
        ]);
        $Project->load('users');

        return $this->respondWithSuccess(__('Assignees added successfully'), [
            'Project' => new ProjectResource($Project),
        ]);
    }

    public function updateStatus(Project $Project): JsonResponse
    {
        $this->validate(request(), [
            'status' => 'required|in:approved,rejected',
        ]);

        $Project->update(['status' => request('status'),
            'reject_message' => request('reject_message'),
        ]);

        return $this->respondWithSuccess(__('Project status updated successfully'), [
            'Project' => new ProjectResource($Project),
        ]);
    }

    public function reopen(ProjectReopenRequest $request, Project $Project): JsonResponse
    {
        if (! in_array($Project->status, ['approved', 'rejected'], true)) {
            return $this->setStatusCode(422)->respondWithError(
                __('Only approved or rejected projects can be reopened')
            );
        }

        $Project->update([
            'status' => 'in_progress',
            'reopen_reason' => $request->validated('reopen_reason'),
        ]);

        return $this->respondWithSuccess(__('Project reopened for estimation successfully'), [
            'Project' => new ProjectResource($Project),
        ]);
    }
}
