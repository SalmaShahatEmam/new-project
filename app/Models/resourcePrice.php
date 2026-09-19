<?php

namespace App\Models;

use App\Models\Resource;
use App\Models\Supplier;
use App\Traits\ModelTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class resourcePrice extends Model
{
    use HasFactory, ModelTrait ;

    protected $fillable = ['resource_id', 'location_id', 'price', 'updated_by' , 'currency', 'effective_date' , "tax_value" , "supplier_id"];

    protected $appends = ['price_after_tax' ,'leatestPrice'];

    protected $with = ["location", "updatedBy" , "supplier"];


     protected function priceAfterTax(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->tax_value != '0'
                ? $this->price + ($this->price * $this->tax_value / 100)
                : $this->price,
        );
    }  

    protected function leatestPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->resource->resourcePrices()
                ->orderBy('effective_date', 'desc')
                ->first(),
        );
    }

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

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function getFormattedEffectiveDateAttribute()
{
    return [
        'en' => Carbon::parse($this->effective_date)
            ->locale('en')
            ->translatedFormat('d M Y, h:i A'),

        'ar' => Carbon::parse($this->effective_date)
            ->locale('ar')
            ->translatedFormat('d MMMM Y، h:mm A'),
    ];
}
}
