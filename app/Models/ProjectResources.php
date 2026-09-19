<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectResources extends Model
{
    protected $fillable = [
        'project_id',
        'resource_id',
        'quantity',
        'supply_location',
        'user_id',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
