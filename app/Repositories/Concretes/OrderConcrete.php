<?php

namespace App\Repositories\Concretes;

use App\Models\Order;
use App\Repositories\Contracts\OrderContract;

class OrderConcrete extends BaseConcrete implements OrderContract
{
    /**
     * OrderConcrete constructor.
     * @param Order $model
     */
    public function __construct(Order $model)
    {
        parent::__construct($model);
    }
}
