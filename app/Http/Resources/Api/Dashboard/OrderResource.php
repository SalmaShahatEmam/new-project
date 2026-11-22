<?php

namespace App\Http\Resources\Api\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        $this->loadMissing(['user', 'items.resource', 'items.site']);
        $items = $this->relationLoaded('items') ? $this->items : collect();
        $totalBeforeTax = $items->sum(fn ($item) => $this->calculateItemTotals($item)['total_before_tax']);
        $totalAfterTax = $items->sum(fn ($item) => $this->calculateItemTotals($item)['total_after_tax']);

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'user_id' => $this->user_id,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'sections' => $this->sections,
            'priority' => $items->pluck('priority')
                ->filter()
                ->sortBy(fn ($priority) => $priority->rank())
                ->last()
                ?->value,
            'status' => $this->status,
            'items' => $items->map(function ($item) {
                    $totals = $this->calculateItemTotals($item);

                    return [
                        'id' => $item->id,
                        'boq_item' => $item->boq_item,
                        'resource_id' => $item->resource_id,
                        'resource' => $item->resource ? [
                            'id' => $item->resource->id,
                            'name' => $this->translateValue($item->resource->name),
                            'resource_code' => $item->resource->resource_code,
                            'unit' => $item->resource->unit ? [
                                'id' => $item->resource->unit->id,
                                'name' => $this->translateValue($item->resource->unit->name),
                            ] : null,
                        ] : null,
                        'site_id' => $item->site_id,
                        'site' => $item->site ? [
                            'id' => $item->site->id,
                            'name' => $item->site->name,
                        ] : null,
                        'price' => $item->price,
                        'unit_price_before_tax' => $totals['unit_price_before_tax'],
                        'tax' => $item->tax,
                        'unit_price_after_tax' => $totals['unit_price_after_tax'],
                        'quantity' => $item->quantity,
                        'total_before_tax' => $totals['total_before_tax'],
                        'total_after_tax' => $totals['total_after_tax'],
                        'reason' => $item->reason,
                        'status' => $item->status?->value,
                        'priority' => $item->priority?->value,
                        'delivery_date' => $item->delivery_date,
                        'notes' => $item->notes,
                        "created_at" => $item->created_at,
                        "updated_at" => $item->updated_at,
                    ];
                })->values()->all(),
            'total_tax' => $totalAfterTax - $totalBeforeTax,
            'total_before_tax' => $totalBeforeTax,
            'total_after_tax' => $totalAfterTax,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

   private function calculateItemTotals($item): array
    {
        $unitPriceBeforeTax = (float) $item->price;
        $tax = (float) $item->tax;
        $quantity = (float) $item->quantity;
        
        $unitPriceAfterTax = $unitPriceBeforeTax + ($unitPriceBeforeTax * $tax / 100);
        $totalBeforeTax = $unitPriceBeforeTax * $quantity;
        $totalAfterTax = $unitPriceAfterTax * $quantity;

        return [
            'unit_price_before_tax' => $unitPriceBeforeTax,
            'unit_price_after_tax' => $unitPriceAfterTax,
            'total_before_tax' => $totalBeforeTax,
            'total_after_tax' => $totalAfterTax,
        ];
    }

    private function translateValue(mixed $value): ?string
    {
        if (is_array($value)) {
            return $value[app()->getLocale()] ?? reset($value) ?: null;
        }

        return $value;
    }
}
