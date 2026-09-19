<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProjectEngineers;
use PhpParser\Builder\Function_;

class Project extends Model
{
    use HasFactory, ModelTrait;

    protected $fillable = ["name", "description",  "status", "user_id", "end_date"];

    protected $filters = ["name", "description",  "status", "user_id", "end_date" , "id"];

    public function scopeOfId($query, $id)
    {
        return $query->where('id', $id);
    }
    public function scopeOfName($query, $name)
    {
        return $query->where('name', 'LIKE', "%$name%");
    }

    public function scopeOfDescription($query, $description)
    {
        return $query->where('description', 'LIKE', "%$description%");
    }

    public function scopeOfStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOfUserId($query, $user_id)
    {
        return $query->where('user_id', $user_id);
    }

    public function scopeOfEndDate($query, $end_date)
    {
        return $query->where('end_date', $end_date);
    }



    # Relations

    public function users()
    {
        return $this->hasMany(ProjectEngineers::class , "project_id" ,"id");
    }

    public function resources()
    {
        return $this->hasMany(ProjectResources::class , "project_id" ,"id");
    }
}
