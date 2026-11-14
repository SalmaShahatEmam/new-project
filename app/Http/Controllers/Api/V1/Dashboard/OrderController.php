<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Enum\OrderItemStatusEnum;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\Dashboard\OrderRequest;
use App\Http\Resources\Api\Dashboard\OrderResource;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Resource;
use App\Repositories\Contracts\OrderContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class OrderController extends BaseApiController
{
    /**
     * OrderController constructor.
     * @param OrderContract $repository
     */
    public function __construct(OrderContract $repository)
    {
        parent::__construct($repository, OrderResource::class, 'orders');
    }

    public function orderStatics()
    {
        $totalOrders = Order::query()->count();
        $totalOrdersItemsPriceBeforeTax = OrderItems::query()->sum('total_before_tax');
        $totalOrdersItemsPriceAfterTax = OrderItems::query()->sum('total_after_tax');
        $totalOrdersItemsTax = $totalOrdersItemsPriceAfterTax - $totalOrdersItemsPriceBeforeTax;

        return $this->respondWithSuccess(__('Order Statistics'), [
            'total_orders_count' => $totalOrders,
            'total_orders_items_price_before_tax' => $totalOrdersItemsPriceBeforeTax,
            'total_orders_items_price_after_tax' => $totalOrdersItemsPriceAfterTax,
            'total_orders_items_tax' => $totalOrdersItemsTax,
        ]);
    }

    /**
     * @param OrderRequest $request
     * @return JsonResponse
     */
    public function store(OrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = DB::transaction(function () use ($validated) {
            $order = Order::query()->create([
                'order_number' => 'ORD-TEMP-'.Str::uuid(),
                'user_id' => auth()->id(),
                'sections' => $validated['sections'] ?? null,
                'status' => 'new',
            ]);

            $order->update([
                'order_number' => $this->generateOrderNumber($order->id),
            ]);

            foreach ($validated['items'] ?? [] as $itemData) {
                $resource = Resource::query()->findOrFail($itemData['resource_id']);

                $latestResourcePrice = $resource->resourcePrices()
                    ->orderByDesc('effective_date')
                    ->orderByDesc('id')
                    ->first();

                if (! $latestResourcePrice) {
                    throw new \RuntimeException(__('No price history found for resource #:resource_id', ['resource_id' => $resource->id]));
                }

                $order->items()->create([
                    'boq_item' => $itemData['BOQ_item'] ?? $itemData['boq_item'] ?? null,
                    'order_id' => $order->id,
                    'resource_id' => $resource->id,
                    'site_id' => $itemData['site_id'] ?? null,
                    'price' => $latestResourcePrice->price,
                    'tax' => $latestResourcePrice->tax_value ?? 0,
                    'quantity' => $itemData['quantity'],
                    'reason' => $itemData['reason'] ?? null,
                    'status' => $itemData['status'] ?? OrderItemStatusEnum::UNAPPROVED->value,
                    'priority' => $itemData['priority'] ?? null,
                    'delivery_date' => $itemData['delivery_date'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $order->syncStatusFromItems();

            return $order->load(['user', 'items.resource', 'items.site']);
        });

        return $this->respondWithSuccess(__('Order added successfully'), [
            'Order' => new OrderResource($order),
      ]);
    }

    /**
     * Display the specified resource.
     *
     * @param Order $order
     * @return JsonResponse
     */
    public function show(Order $order): JsonResponse
    {
        return $this->respondWithSuccess(__('Order details'), [
            'Order' => new OrderResource($order->load(['user', 'items.resource', 'items.site'])),
        ]);
    }

    public function getMyOrders()
    {
        $user = auth()->user();
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->with(['items.resource.unit', 'items.site'])
            ->get();

        return $this->respondWithSuccess(__('My Orders'), [
            'Orders' => OrderResource::collection($orders),
        ]);
    }
    /**
     * Update the specified resource in storage.
     *
     * @param OrderRequest $request
     * @param Order $Order
     * @return JsonResponse
     */
    public function update(OrderRequest $request, Order $Order): JsonResponse
    {
        $Order = $this->repository->update($Order, $request->all());
        return $this->respondWithSuccess(__('Order updated successfully'), [
            'Order' => (new OrderResource($Order)),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Order $Order
     *
     * @return JsonResponse
     */
    public function destroy(Order $Order): JsonResponse
    {
        $this->repository->remove($Order);
        return $this->respondWithSuccess(trans('messages.responses.deleted'));
    }

    private function generateOrderNumber(int $orderId): string
    {
        $year = now()->format('Y');
        $sequence = $orderId;

        do {
            $orderNumber = sprintf('ORD-%s-%03d', $year, $sequence++);
        } while (Order::query()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }


    public function updateOrderItemStatus(Request $request , $orderItemId): JsonResponse
    {
        $validated = $request->validate([
                'status' => ['required', Rule::enum(OrderItemStatusEnum::class)],
                'reason' => 'nullable|string',
        ]);

        $orderItem = DB::transaction(function () use ($validated, $orderItemId) {
            $orderItem = OrderItems::query()->findOrFail($orderItemId);
            $updates = [
                'status' => $validated['status'],
                'status_change_date' => now(),
            ];

            if ($validated['status'] === OrderItemStatusEnum::REJECTED->value) {
                $updates['rejection_reason'] = $validated['reason'] ?? null;
            } elseif ($validated['status'] === OrderItemStatusEnum::RETURNED_FOR_REVISION->value) {
                $updates['return_reason'] = $validated['reason'] ?? null;
            }

            $orderItem->update($updates);
            $orderItem->order->syncStatusFromItems();

            return $orderItem;
        });

        return $this->respondWithSuccess(__('Order item status updated successfully'), [
            'OrderItem' => $orderItem,
        ]);
    }   


    public function deleteOrderItem(int $orderItemId): JsonResponse
    {
        $orderItem = OrderItems::query()->findOrFail($orderItemId);

        if ($orderItem->status === OrderItemStatusEnum::APPROVED->value) {
            return $this->respondWithError(__('Cannot delete an approved order item'), 400);
        }

        $orderItem->delete();
        $orderItem->order->syncStatusFromItems();

        return $this->respondWithSuccess(__('Order item deleted successfully'));
    }


    public function updateOrderItem(Request $request, int $orderItemId): JsonResponse
    {
        $validated = $request->validate([
            'boq_item' => 'nullable|string',
            'quantity' => 'required|numeric|min:1',
            'reason' => 'nullable|string',
            'priority' => 'nullable|string',
            'delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $orderItem = OrderItems::query()->findOrFail($orderItemId);

        if ($orderItem->status === OrderItemStatusEnum::APPROVED->value) {
            return $this->respondWithError(__('Cannot update an approved order item'), 400);
        }

        $orderItem->update($validated);
        $orderItem->update([
            'status_change_date' => now(),
            'status' => OrderItemStatusEnum::UNAPPROVED->value,
        ]);

        $orderItem->order->syncStatusFromItems();

        return $this->respondWithSuccess(__('Order item updated successfully'), [
            'OrderItem' => $orderItem,
        ]);
    }
}
