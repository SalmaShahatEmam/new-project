<?php

namespace App\Repositories\Contracts;

use App\Models\Supplier;

interface SupplierContract extends BaseContract
{

public function toggleActive(Supplier $Supplier): Supplier;
}

