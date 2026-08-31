<?php

namespace App\Repositories\Concretes;

use App\Models\Category;
use App\Repositories\Contracts\CategoryContract;

class CategoryConcrete extends BaseConcrete implements CategoryContract
{
    /**
     * CategoryConcrete constructor.
     * @param Category $model
     */
    public function __construct(Category $model)
    {
        parent::__construct($model);
    }
}
