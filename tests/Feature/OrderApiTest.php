<?php

namespace Tests\Feature;

use App\Http\Middleware\APIKey;
use App\Models\Category;
use App\Models\Order;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(APIKey::class);
        $this->withHeader('Accept-Language', 'en');
    }

    public function test_order_index_and_show_include_order_item_resource_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $category = Category::query()->create([
            'name' => ['en' => 'Electrical', 'ar' => 'كهرباء'],
        ]);
        $resource = Resource::query()->create([
            'resource_code' => 'CABLE-001',
            'name' => ['en' => 'Electrical cable', 'ar' => 'كابل كهربائي'],
            'category_id' => $category->id,
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-2026-001',
            'user_id' => $user->id,
            'sections' => 'Electrical',
            'status' => 'new',
        ]);
        $order->items()->create([
            'resource_id' => $resource->id,
            'price' => 12.50,
            'tax' => 15,
            'quantity' => 3,
            'reason' => 'Required for site work',
            'priority' => 'urgent',
        ]);
        $order->items()->create([
            'resource_id' => $resource->id,
            'price' => 10,
            'tax' => 0,
            'quantity' => 2,
            'reason' => 'Additional item',
            'priority' => 'very_urgent',
        ]);

        $this->getJson('/dashboard-api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.Orders.data.0.id', $order->id)
            ->assertJsonPath('data.Orders.data.0.priority', 'very_urgent')
            ->assertJsonPath('data.Orders.data.0.items.0.resource.name', 'Electrical cable')
            ->assertJsonPath('data.Orders.data.0.items.0.resource.resource_code', 'CABLE-001')
            ->assertJsonPath('data.Orders.data.0.items.0.total_after_tax', 43.13)
            ->assertJsonPath('data.Orders.data.0.total_after_tax', 63.13);

        $this->getJson('/dashboard-api/v1/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.Order.id', $order->id)
            ->assertJsonPath('data.Order.priority', 'very_urgent')
            ->assertJsonPath('data.Order.user.id', $user->id)
            ->assertJsonPath('data.Order.items.0.quantity', 3)
            ->assertJsonPath('data.Order.items.0.resource.name', 'Electrical cable')
            ->assertJsonPath('data.Order.items.0.total_after_tax', 43.13)
            ->assertJsonPath('data.Order.total_after_tax', 63.13);
    }

    public function test_order_status_tracks_its_items_when_an_item_status_changes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $category = Category::query()->create([
            'name' => ['en' => 'Electrical', 'ar' => 'كهرباء'],
        ]);
        $resource = Resource::query()->create([
            'resource_code' => 'CABLE-002',
            'name' => ['en' => 'Electrical cable', 'ar' => 'كابل كهربائي'],
            'category_id' => $category->id,
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-2026-002',
            'user_id' => $user->id,
            'status' => 'new',
        ]);
        $items = collect(['unapproved', 'unapproved'])->map(fn ($status) => $order->items()->create([
            'resource_id' => $resource->id,
            'price' => 12.50,
            'tax' => 15,
            'quantity' => 1,
            'status' => $status,
        ]));

        $order->syncStatusFromItems();
        $this->assertSame('unapproved', $order->fresh()->status);

        $this->postJson('/dashboard-api/v1/orders/'.$items[0]->id.'/order-item-status', [
            'status' => 'returned_for_revision',
        ])->assertOk();
        $this->assertSame('returned_for_revision', $order->fresh()->status);

        $this->postJson('/dashboard-api/v1/orders/'.$items[1]->id.'/order-item-status', [
            'status' => 'approved',
        ])->assertOk();
        $this->assertSame('returned_for_revision', $order->fresh()->status);

        $this->postJson('/dashboard-api/v1/orders/'.$items[0]->id.'/order-item-status', [
            'status' => 'approved',
        ])->assertOk();
        $this->assertSame('approved', $order->fresh()->status);

        $this->postJson('/dashboard-api/v1/orders/'.$items[1]->id.'/order-item-status', [
            'status' => 'rejected',
        ])->assertOk();
        $this->assertSame('unapproved', $order->fresh()->status);

        $this->postJson('/dashboard-api/v1/orders/'.$items[0]->id.'/order-item-status', [
            'status' => 'rejected',
        ])->assertOk();
        $this->assertSame('rejected', $order->fresh()->status);
    }

    public function test_order_update_reconciles_items_without_trusting_submitted_statuses(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $category = Category::query()->create([
            'name' => ['en' => 'Electrical', 'ar' => 'كهرباء'],
        ]);
        $resource = Resource::query()->create([
            'resource_code' => 'CABLE-003',
            'name' => ['en' => 'Electrical cable', 'ar' => 'كابل كهربائي'],
            'category_id' => $category->id,
        ]);
        $locationId = DB::table('locations')->insertGetId([
            'name' => json_encode(['en' => 'Riyadh', 'ar' => 'الرياض']),
            'code' => 'RUH-ORDER-TEST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('resource_prices')->insert([
            'resource_id' => $resource->id,
            'location_id' => $locationId,
            'price' => 20,
            'currency' => 'SAR',
            'tax_value' => 15,
            'effective_date' => now()->toDateString(),
            'updated_by' => $user->id,
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = Order::query()->create([
            'order_number' => 'ORD-2026-003',
            'user_id' => $user->id,
            'sections' => 'labour',
            'status' => 'unapproved',
        ]);
        $itemToUpdate = $order->items()->create([
            'resource_id' => $resource->id,
            'price' => 10,
            'tax' => 0,
            'quantity' => 1,
            'boq_item' => 'BOQ-OLD',
            'status' => 'rejected',
            'priority' => 'normal',
            'delivery_date' => now()->addDays(3)->toDateString(),
        ]);
        $itemToDelete = $order->items()->create([
            'resource_id' => $resource->id,
            'price' => 10,
            'tax' => 0,
            'quantity' => 1,
            'status' => 'unapproved',
            'priority' => 'normal',
            'delivery_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->putJson('/dashboard-api/v1/orders/'.$order->id, [
            'sections' => 'materials',
            'items' => [
                [
                    'id' => $itemToUpdate->id,
                    'boq_item' => 'BOQ-UPDATED',
                    'resource_id' => $resource->id,
                    'quantity' => 2,
                    'priority' => 'urgent',
                    'delivery_date' => now()->addDays(5)->toDateString(),
                    'status' => 'approved',
                ],
                [
                    'boq_item' => 'BOQ-NEW',
                    'resource_id' => $resource->id,
                    'quantity' => 3,
                    'priority' => 'normal',
                    'delivery_date' => now()->addDays(5)->toDateString(),
                ],
            ],
        ])->assertOk();

        $this->assertSame('materials', $order->fresh()->sections);
        $this->assertSame('unapproved', $order->fresh()->status);
        $this->assertDatabaseHas('order_items', [
            'id' => $itemToUpdate->id,
            'boq_item' => 'BOQ-UPDATED',
            'quantity' => 2,
            'status' => 'unapproved',
        ]);
        $this->assertDatabaseMissing('order_items', ['id' => $itemToDelete->id]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'boq_item' => 'BOQ-NEW',
            'quantity' => 3,
            'price' => 20,
            'tax' => 15,
            'status' => 'unapproved',
        ]);
    }

    public function test_order_update_ignores_approved_item_changes_and_updates_other_items(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $category = Category::query()->create([
            'name' => ['en' => 'Electrical', 'ar' => 'كهرباء'],
        ]);
        $resource = Resource::query()->create([
            'resource_code' => 'CABLE-004',
            'name' => ['en' => 'Electrical cable', 'ar' => 'كابل كهربائي'],
            'category_id' => $category->id,
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-2026-004',
            'user_id' => $user->id,
            'status' => 'approved',
        ]);
        $approvedItem = $order->items()->create([
            'resource_id' => $resource->id,
            'price' => 10,
            'tax' => 0,
            'quantity' => 1,
            'status' => 'approved',
            'priority' => 'normal',
            'delivery_date' => now()->addDays(3)->toDateString(),
        ]);
        $editableItem = $order->items()->create([
            'resource_id' => $resource->id,
            'price' => 10,
            'tax' => 0,
            'quantity' => 1,
            'status' => 'unapproved',
            'priority' => 'normal',
            'delivery_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->putJson('/dashboard-api/v1/orders/'.$order->id, [
            'items' => [[
                'id' => $approvedItem->id,
                'resource_id' => $resource->id,
                'quantity' => 2,
                'priority' => 'normal',
                'delivery_date' => now()->addDays(3)->toDateString(),
            ], [
                'id' => $editableItem->id,
                'resource_id' => $resource->id,
                'quantity' => 5,
                'priority' => 'normal',
                'delivery_date' => now()->addDays(3)->toDateString(),
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('order_items', [
            'id' => $approvedItem->id,
            'quantity' => 1,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('order_items', [
            'id' => $editableItem->id,
            'quantity' => 5,
            'status' => 'unapproved',
        ]);

        $this->withHeader('Accept-Language', 'ar')
            ->putJson('/dashboard-api/v1/orders/'.$approvedItem->id.'/order-items', [
            'quantity' => 2,
        ])->assertBadRequest()
            ->assertJsonPath('message', 'لا يمكن تعديل المادة المعتمدة "كهرباء".');
        $this->deleteJson('/dashboard-api/v1/order-items/'.$approvedItem->id)
            ->assertBadRequest();

        $this->putJson('/dashboard-api/v1/orders/'.$order->id, [
            'items' => [],
        ])->assertOk();

        $this->assertDatabaseMissing('order_items', ['id' => $editableItem->id]);
        $this->assertDatabaseHas('order_items', [
            'id' => $approvedItem->id,
            'status' => 'approved',
        ]);
    }
}
