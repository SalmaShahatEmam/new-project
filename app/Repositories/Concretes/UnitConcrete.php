<?php

namespace App\Repositories\Concretes;

use App\Models\Unit;
use App\Repositories\Contracts\UnitContract;

class UnitConcrete extends BaseConcrete implements UnitContract
{
    /**
     * UnitConcrete constructor.
     * @param Unit $model
     */
    public function __construct(Unit $model)
    {
        parent::__construct($model);
    }

    public function toggleActive(Unit $Unit): Unit
    {
        $Unit->is_active = !$Unit->is_active;
        $Unit->save();
        return $Unit;
    }
}
