<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SiteUser extends Pivot
{
    protected $table = 'site_user';

    protected $fillable = [
        'site_id',
        'user_id',
    ];

    public $incrementing = true;
}
