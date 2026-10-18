<?php

namespace App\Repositories\Concretes;

use App\Models\Project;
use App\Models\Resource;
use App\Models\User;
use App\Repositories\Contracts\ProjectContract;
use Illuminate\Support\Facades\DB;

class ProjectConcrete extends BaseConcrete implements ProjectContract
{
    /**
     * ProjectConcrete constructor.
     * @param Project $model
     */
    public function __construct(Project $model)
    {
        parent::__construct($model);
    }

    public function addResources(
        Project $project,
        ?string $sectionName,
        array $resources,
        int|string $userId,
        int|string $engineerId
    ): Project {

        return DB::transaction(function () use ($project, $sectionName, $resources, $userId, $engineerId): Project {

        $user = User::find($userId);
            $project = Project::query()
                ->lockForUpdate()
                ->findOrFail($project->getKey());

            $resourcesToCreate = [];

            foreach ($resources as $resourceData) {
                $resource = Resource::find($resourceData['resource_id']);

                if (! $resource) {
                    abort(404, 'Resource not found.');
                }

                $resourcePrice = $resource->resourcePrices()
                    ->where('location_id', $resourceData['location_id'])
                    ->orderByDesc('created_at')
                    ->whereNull('deleted_at')
                    ->first();

                if (! $resourcePrice) {
                    abort(422, "No price found for this resource \"{$resource->name}\" and location.");
                }

                $price = (float) $resourcePrice->price;
                $tax = (float) $resourcePrice->tax_value;

                $resourcesToCreate[] = [
                    'resource_id' => $resource->id,
                    'quantity' => (float) $resourceData['quantity'],
                    'supply_location' => $resourceData['location_id'],
                    'section_name' => $sectionName,
                    'user_id' => $userId,
                    'engineer_id' => $engineerId,
                    'unit' => $resource->unit?->name,
                    'price' => $price,
                    'note' => $resourceData['note'] ?? null,
                    'tax' => $tax,
                    'price_after_tax' => $price + ($price * $tax / 100),
                ];
            }

            if ($user->type != 'admin') {
                $project->resources()
                    ->where('section_name', $sectionName)
                    ->where('engineer_id', $engineerId)
                    ->forceDelete();
            }else{
                $project->resources()
                    ->where('section_name', $sectionName)
                    ->forceDelete();
            }

            if ($resourcesToCreate) {
                $project->resources()->createMany($resourcesToCreate);
            }

            $project->update([
                'status' => $project->allEngineersUploadedResources() ? 'submitted' : 'in_progress',
            ]);

            return $project;
        });
    }
}
