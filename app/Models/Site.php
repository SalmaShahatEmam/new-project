<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\SoftDeletes;
class Site extends Model
{
    use HasFactory, ModelTrait, HasTranslations , SoftDeletes;

    protected $fillable = ['name', 'description', 'duration_in_months', 'contract_date', 'location_id', 'project_id', 'status'];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'contract_date' => 'datetime',
    ];

    protected $appends = [
        'site_report_labour',
        'site_report_material',
        'site_equipment',
        'site_problem_count',
        'site_report_count',
        'site_problem_count',
    ];

    #site_report_labour

    public function getSiteReportCountAttribute()
    {
        return $this->reports()->count();
    }


    public function getSiteReportLabourAttribute()
    {
        return $this->reports()->where('report_type', 'labor')->count();
    }

    public function getSiteReportMaterialAttribute()
    {
        return $this->reports()->where('report_type', 'materials')->count();
    }

    public function getSiteEquipmentAttribute()
    {
        return $this->reports()->where('report_type', 'equipment')->count();
    }

    public function getSiteProblemCountAttribute()
    {
        return $this->problems()->count();
    }

    protected $translatable = ['name', 'description'];

    protected $filters = ['location_id', 'project_id'];

    protected $definedRelations = ['users'];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(SiteUser::class)
            ->withTimestamps();
    }

    public function reports()
    {
        return $this->hasMany(SiteReport::class);
    }

    public function problems()
    {
        return $this->hasMany(SiteProblem::class);
    }

    public function scopeOfLocation_id($query, $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeOfProject_id($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }
}
