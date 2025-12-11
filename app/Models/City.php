<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class City extends Model
{
    use HasFactory, ModelTrait,  HasTranslations;

    protected $fillable = ["name"];

    protected $filters = ["name" , "is_active"];

    protected $translatable = ['name'];

    protected $casts = [
        'name' => 'array',
    ];
  
    public function scopeofIs_active(Builder $query , $activation)
    {
        return $query->where('is_active', $activation);
    }
    public function scopeofName(Builder $query , $name)
    {
        return $query->where('name->en', 'LIKE', "%$name%")
            ->orWhere('name->ar', 'LIKE', "%$name%");
    }
    # Relations
}
