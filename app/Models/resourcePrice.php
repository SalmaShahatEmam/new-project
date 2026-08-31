<?php

namespace App\Models;

use App\Models\Resource;
use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class resourcePrice extends Model
{
    use HasFactory, ModelTrait ;

    protected $fillable = [];

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }
    
}
