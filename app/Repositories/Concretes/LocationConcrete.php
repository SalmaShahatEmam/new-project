<?php

namespace App\Repositories\Concretes;

use App\Models\Location;
use App\Repositories\Contracts\LocationContract;

class LocationConcrete extends BaseConcrete implements LocationContract
{
    /**
     * LocationConcrete constructor.
     * @param Location $model
     */
    public function __construct(Location $model)
    {
        parent::__construct($model);
    }
}
