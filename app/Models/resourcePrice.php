<?php

namespace App\Models;

use App\Models\Resource;
use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class resourcePrice extends Model
{
    use HasFactory, ModelTrait ;

    protected $fillable = ['resource_id', 'location_id', 'price', 'updated_by' , 'currency', 'effective_date'];

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }
    
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
