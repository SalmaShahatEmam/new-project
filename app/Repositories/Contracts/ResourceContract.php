<?php

namespace App\Repositories\Contracts;

use App\Models\Resource;

interface ResourceContract extends BaseContract
{
    public function storePrices(Resource $Resource, array $data);

}

