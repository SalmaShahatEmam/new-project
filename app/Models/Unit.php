<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Translatable\Translatable;

class Unit extends Model
{
    use HasFactory, ModelTrait , HasTranslations;


  protected $fillable = ["name" ];

    protected $filters = ["name" , "is_active"];

    protected $translatable = ['name'];

    protected $casts = [
        'name' => 'array',
        "is_active" => 'boolean',
    ];

}
