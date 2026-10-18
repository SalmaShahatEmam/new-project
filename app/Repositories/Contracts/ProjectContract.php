<?php

namespace App\Repositories\Contracts;

use App\Models\Project;

interface ProjectContract extends BaseContract
{
    public function addResources(
        Project $project,
        ?string $sectionName,
        array $resources,
        int|string $userId,
        int|string $engineerId
    ): Project;
}
