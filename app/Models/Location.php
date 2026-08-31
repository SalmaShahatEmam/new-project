<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Location extends Model
{
    use HasFactory, ModelTrait , HasTranslations;

    protected $fillable = ["name" , "code"];
    protected $filters = ["name" ];
    protected $casts = [
           'name' => 'array',
    ];
    # Relations

      public function scopeofName(Builder $query , $name)
    {
        return $query->where('name->en', 'LIKE', "%$name%")
            ->orWhere('name->ar', 'LIKE', "%$name%");
    }
}
