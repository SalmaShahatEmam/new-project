<?php

namespace App\Repositories\Concretes;

use App\Models\Supplier;
use App\Repositories\Contracts\SupplierContract;

class SupplierConcrete extends BaseConcrete implements SupplierContract
{
    /**
     * SupplierConcrete constructor.
     * @param Supplier $model
     */
    public function __construct(Supplier $model)
    {
        parent::__construct($model);
    }


  public function toggleActive(Supplier $Supplier): Supplier
{
    $Supplier->status = $Supplier->status === 'active'
        ? 'inactive'
        : 'active';

    $Supplier->save();

    return $Supplier;
}
}
