<?php

namespace App\Repositories\Contracts;

use App\Models\Unit;

interface UnitContract extends BaseContract
{

        public function toggleActive(Unit $Unit);
}

