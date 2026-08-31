<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Resource extends Model
{
    use HasFactory, ModelTrait , HasTranslations;

    protected $fillable = ["name" , "description" , "unit_of_measure" , "category_id" , "resource_code"];

    protected $casts = [
           'name' => 'array',
           'description' => 'array',
           'unit_of_measure' => 'array',
    ];

    protected $guarded = [];

    # Relations

    public function resourcePrices()
    {
        return $this->hasMany(resourcePrice::class);
    }
}
