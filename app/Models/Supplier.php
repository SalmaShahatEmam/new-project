<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Supplier extends Model
{
     use HasFactory, ModelTrait,  HasTranslations;

  #   protected $table = "suppliers";

    protected $fillable = ["name" , "contact" , "map_link" , "status"];

    protected $filters = ["name" , "contact" , "map_link" , "status"];

    protected $translatable = ['name'];

    protected $casts = [
        'name' => 'array',
    ];
  
   public function scopeofStatus(Builder $query , $status)
    {
        return $query->where('status', $status);
    }

    public function scopeofContact(Builder $query , $contact)
    {
        return $query->where('contact', 'LIKE', "%$contact%");
    }
    public function scopeofName(Builder $query , $name)
    {
        return $query->where('name->en', 'LIKE', "%$name%")
            ->orWhere('name->ar', 'LIKE', "%$name%");
    }
}
