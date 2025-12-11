<?php

namespace App\Repositories\Concretes;

use App\Models\City;
use App\Repositories\Contracts\CityContract;

class CityConcrete extends BaseConcrete implements CityContract
{
    /**
     * CityConcrete constructor.
     * @param City $model
     */
    public function __construct(City $model)
    {
        parent::__construct($model);
    }
}
