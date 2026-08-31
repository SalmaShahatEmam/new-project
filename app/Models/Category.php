<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasFactory, ModelTrait , HasTranslations;

  
 protected $casts = [
        'name' => 'array',
    ];    protected $fillable = ["name" , "parent_id" ];

    # Relations
}
