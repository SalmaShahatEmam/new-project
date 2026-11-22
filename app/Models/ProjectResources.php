<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectResources extends Model
{
    use softDeletes;

    protected $fillable = [
        'project_id',
        'engineer_id',
        'resource_id',
        'quantity',
        'unit',
        'price',
        'supply_location',
        'user_id',
        'section_name',
        'note',
        "tax",
        "price_after_tax",
    ];


    protected $appends = [
        'resource_name' , 'r_unit'  , 'resource_code'
    ];


 /*    public function getResourceNameAttribute()
    {
        return $this->resource()->first()->name ?? null;
    } */

    public function getRUnitAttribute()
    {
        return $this->resource()->first()->unit->name ?? null;
    }


    public function getResourceCodeAttribute(): ?string
    {
        if ($this->relationLoaded('resource')) {
            return $this->getRelation('resource')?->resource_code;
        }

        return $this->resource()->value('resource_code');
    }


    public function getResourceNameAttribute(): ?string
    {
    if ($this->relationLoaded('resource')) {
        return $this->getRelation('resource')?->name;
    }

    return $this->resource()->value('name');
    } 

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function projectEngineer()
    {
        return $this->belongsTo(User::class, 'engineer_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'supply_location');
    }
}
