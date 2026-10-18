<?php

namespace App\Repositories\Concretes;

use App\Models\Site;
use App\Repositories\Contracts\SiteContract;

class SiteConcrete extends BaseConcrete implements SiteContract
{
    /**
     * SiteConcrete constructor.
     * @param Site $model
     */
    public function __construct(Site $model)
    {
        parent::__construct($model);
    }

}
