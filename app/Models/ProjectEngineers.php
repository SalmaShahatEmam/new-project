<?php

namespace App\Models;

use App\Models\Project;
use App\Models\User;
use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectEngineers extends Model
{
    use HasFactory, ModelTrait;

    protected $fillable = [
        'project_id',
        'engineer_id',
        'type',
    ];

    public function project()
{
    return $this->belongsTo(Project::class, 'project_id');
}

public function engineer()
{
    return $this->belongsTo(User::class, 'engineer_id');
}
}
#Structural
#Architectural
#Electrical
#Mechanical