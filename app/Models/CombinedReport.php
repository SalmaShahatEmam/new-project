<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CombinedReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'from_date',
        'to_date',
        'name',
    ];

    protected $casts = [
        'from_date' => 'date:Y-m-d',
        'to_date' => 'date:Y-m-d',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
