<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderDetails extends Model
{
    protected $table = "provider_details";

    protected $hidden = [
        'created_at' ,
        "updated_at" ,
    ];

    protected $guarded = [];
}
