<?php

namespace App\Models;

use App\Enum\OrderItemPriorityEnum;
use App\Enum\OrderItemStatusEnum;
use Illuminate\Database\Eloquent\Model;

class OrderItems extends Model
{
    protected $table = 'order_items';

    protected $casts = [
        'status' => OrderItemStatusEnum::class,
        'priority' => OrderItemPriorityEnum::class,
    ];

    protected $appends = [
        'total_after_tax',
    ];

    protected $fillable = [
                'boq_item',
                'order_id',
                'resource_id',
                'site_id',
                'price',
                'tax',
                'quantity',
                'reason',
                'status',
                'priority',
                'delivery_date',
                'notes',
                'status_change_date',
                'rejection_reason'
                ,'return_reason'
            ];

    public function getTotalAfterTaxAttribute(): float
    {
        $price = (float) $this->price;
        $taxRate = (float) $this->tax;
        $quantity = (float) $this->quantity;

        return round($price * $quantity * (1 + ($taxRate / 100)), 2);
    }

        public function order()
        {
            return $this->belongsTo(Order::class, 'order_id');
        }

        public function resource()
        {
            return $this->belongsTo(Resource::class, 'resource_id');
        }

        public function site()
        {
            return $this->belongsTo(Site::class, 'site_id');
        }


}
