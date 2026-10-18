<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteProblem extends Model
{
    protected $fillable = [
        'site_id',
        'user_id',
        'founded_by',
        'assigned_to',
        'explanation',
        'causes',
        'notes',
        'status',
        'priority',
        'suggested_solutions',
        'attachments',
    ];

    protected $casts = [
        'suggested_solutions' => 'array',
        'attachments' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function founder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'founded_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
