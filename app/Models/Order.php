<?php

namespace App\Models;

use App\Enum\OrderItemStatusEnum;
use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, ModelTrait;

   
    protected $fillable = ['order_number', 'user_id', 'sections', 'status'];
    Protected $filters = ['order_number','date','from_date' , 'to_date','resource_id','user_id', 'sections', 'status' , 'site_id'];
    # Relations

    public function items()
    {
        return $this->hasMany(OrderItems::class, 'order_id');
    }

    public function syncStatusFromItems(): void
    {
        $itemCount = $this->items()->count();

        if ($this->items()
            ->where('status', OrderItemStatusEnum::RETURNED_FOR_REVISION->value)->exists()) {
            $status = OrderItemStatusEnum::RETURNED_FOR_REVISION->value;
        } elseif ($this->items()->where('status', OrderItemStatusEnum::UNAPPROVED->value)->exists()) {
            $status = OrderItemStatusEnum::UNAPPROVED->value;
        } elseif ($itemCount > 0 && $this->items()
        ->where('status', OrderItemStatusEnum::REJECTED->value)->count() === $itemCount) {
            $status = OrderItemStatusEnum::REJECTED->value;
        } elseif ($itemCount > 0 && $this->items()
            ->where('status', OrderItemStatusEnum::APPROVED->value)->count() === $itemCount) {
            $status = OrderItemStatusEnum::APPROVED->value;
        } elseif ($itemCount > 0) {
            $status = OrderItemStatusEnum::UNAPPROVED->value;
        } else {
            $status = 'new';
        }

        $this->update(['status' => $status]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function scopeOfUser_id($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOfSections($query, $sections)
    {
        return $query->where('sections', $sections);
    }

    public function scopeOfOrder_number($query, $orderNumber)
    {
        return $query->where('order_number', 'like', "%$orderNumber%");
    }

    public function scopeOfSite_id($query, $siteId)
    {
        return $query->whereRelation('items', 'site_id', $siteId);
    }

    public function scopeOfResource_id($query, $resourceId)
    {
        return $query->whereRelation('items', 'resource_id', $resourceId);
    }

    public function scopeOfFrom_date($query, $fromDate)
    {
        return $query->whereDate('created_at', '>=', $fromDate);
    }

    public function scopeOfTo_date($query, $toDate)
    {
        return $query->whereDate('created_at', '<=', $toDate);
    }

    public function scopeOfDate($query, $date)
    {
        $now = now();

        switch ($date) {
            case 'today':
                return $query->whereDate('created_at', $now->toDateString());
            case 'week':
                return $query->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
            case 'month':
                return $query->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);
            default:
                return $query;
        }
    }
}
