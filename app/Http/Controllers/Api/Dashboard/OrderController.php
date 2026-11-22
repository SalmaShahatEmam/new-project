<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\Dashboard\OrderRequest;
use App\Http\Resources\Api\Dashboard\OrderResource;
use App\Models\Order;
use App\Models\Resource;
use App\Repositories\Contracts\OrderContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /**
     * @param OrderRequest $request
     * @return JsonResponse
     */
    public function store(OrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = DB::transaction(function () use ($validated) {
            $order = Order::query()->create([
                'order_number' => $validated['order_number'],
                'user_id' => auth()->id(),
                'sections' => $validated['sections'] ?? null,
                'status' => 'new',
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
                    'status' => $itemData['status'] ?? null,
                    'priority' => $itemData['priority'] ?? null,
                    'delivery_date' => $itemData['delivery_date'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

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


}
