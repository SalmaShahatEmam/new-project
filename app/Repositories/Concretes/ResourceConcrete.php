<?php

namespace App\Repositories\Concretes;

use App\Models\Resource;
use App\Repositories\Contracts\ResourceContract;

class ResourceConcrete extends BaseConcrete implements ResourceContract
{
    /**
     * ResourceConcrete constructor.
     * @param Resource $model
     */
    public function __construct(Resource $model)
    {
        parent::__construct($model);
    }


    public function storePrices(Resource $Resource, array $data)
    {
        return $Resource->resourcePrices()->create($data);
    }
}
