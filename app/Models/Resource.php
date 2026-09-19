<?php

namespace App\Models;

use App\Models\Unit;
use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Resource extends Model
{
    use HasFactory, ModelTrait , HasTranslations;

    protected $fillable = ["name" , "description" , "unit_of_measure" , "category_id" , "resource_code" , 'unit_id'];

    protected $casts = [
           'name' => 'array',
           'description' => 'array',
           'unit_of_measure' => 'array',
    ];

    # Relations
    protected $with = ['category', 'unit' , "resourcePrices"];
    protected $filters = ["name" , "description" , "unit_of_measure" , "category_id" , "resource_code"];
    protected $translatable = ['name', 'description' , 'unit_of_measure'];

     public function scopeofIs_active(Builder $query , $activation)
    {
        return $query->where('is_active', $activation);
    }

    public function scopeofCategory_id(Builder $query , $category_id)
    {
        return $query->where('category_id', $category_id);
    }
    public function scopeofResource_code(Builder $query , $resource_code)
    {
        return $query->where('resource_code', 'LIKE', "%$resource_code%");
    }
    public function scopeofUnit_of_measure(Builder $query , $unit_of_measure)
    {
        return $query->where('unit_of_measure->en', 'LIKE', "%$unit_of_measure%")
            ->orWhere('unit_of_measure->ar', 'LIKE', "%$unit_of_measure%");
    }

    public function scopeofName(Builder $query , $name)
    {
        return $query->where('name->en', 'LIKE', "%$name%")
            ->orWhere('name->ar', 'LIKE', "%$name%");
    }

    public function scopeofDescription(Builder $query , $description)
    {
        return $query->where('description->en', 'LIKE', "%$description%")
            ->orWhere('description->ar', 'LIKE', "%$description%");
    
    }
    public function resourcePrices()
    {
        return $this->hasMany(resourcePrice::class);
    }

    public function category() 
    {
        return $this->belongsTo(Category::class);
    }

    public function unit() 
    {
        return $this->belongsTo(Unit::class);
    }



}
