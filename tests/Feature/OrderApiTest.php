<?php

namespace Tests\Feature;

use App\Http\Middleware\APIKey;
use App\Models\Category;
use App\Models\Order;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
