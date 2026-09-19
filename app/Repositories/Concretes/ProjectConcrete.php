<?php

namespace App\Repositories\Concretes;

use App\Models\Project;
use App\Repositories\Contracts\ProjectContract;

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
}
