<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\SendOrderConfirmation;
use App\Models\Admin;
use App\Models\Order;
use App\Services\FonnteWhatsApp;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MenuSeeder::class);

        Http::preventStrayRequests();
    }

    public function test_job_is_dispatched_once_per_created_order(): void
    {
        Queue::fake();
        config(['whatsapp.enabled' => true]);
        $admin = Admin::factory()->create();

        $first = Order::create([
            'order_number' => 'ORD-001',
            'customer_phone' => '081234567890',
            'status' => OrderStatus::Pending,
            'total' => 25000,
            'created_by' => $admin->id,
        ]);
        $second = Order::create([
            'order_number' => 'ORD-002',
            'customer_phone' => '081298765432',
            'status' => OrderStatus::Pending,
            'total' => 15000,
            'created_by' => $admin->id,
        ]);

        Queue::assertPushed(SendOrderConfirmation::class, 2);
        Queue::assertPushed(SendOrderConfirmation::class, fn (SendOrderConfirmation $job) => $job->order->is($first));
        Queue::assertPushed(SendOrderConfirmation::class, fn (SendOrderConfirmation $job) => $job->order->is($second));
    }

    public function test_no_http_request_is_made_when_disabled(): void
    {
        config(['whatsapp.enabled' => false]);
        $admin = Admin::factory()->create();

        Order::create([
            'order_number' => 'ORD-001',
            'customer_phone' => '081234567890',
            'status' => OrderStatus::Pending,
            'total' => 25000,
            'created_by' => $admin->id,
        ]);

        Http::assertNothingSent();
    }

    public function test_job_skips_gracefully_when_token_is_missing(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => null]);
        $order = $this->makeOrder();

        SendOrderConfirmation::dispatchSync($order);

        Http::assertNothingSent();
    }

    public function test_sends_confirmation_payload_when_enabled(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        Http::fake();
        $order = $this->makeOrder(['order_number' => 'ORD-001']);

        SendOrderConfirmation::dispatchSync($order);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === config('whatsapp.fonnte.url')
                && $request->hasHeader('Authorization', 'test-token')
                && $request['target'] === '081234567890'
                && str_contains($request['message'], 'ORD-001')
                && str_contains($request['message'], config('shop.name'))
                && ! str_contains($request['message'], ':shop')
                && str_contains($request['message'], 'Espresso')
                && str_contains($request['message'], 'Cappuccino')
                && str_contains($request['message'], 'Rp 25.000')
                && str_contains($request['message'], config('shop.phone'));
        });
        Http::assertSentCount(1);
    }

    public function test_message_includes_only_first_three_items(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        Http::fake();
        $order = $this->makeOrder();
        $order->items()->create(['menu_item_id' => 3, 'name' => 'Flat White', 'price' => 20000, 'qty' => 1, 'subtotal' => 20000]);
        $order->items()->create(['menu_item_id' => 4, 'name' => 'Cold Brew', 'price' => 25000, 'qty' => 1, 'subtotal' => 25000]);

        SendOrderConfirmation::dispatchSync($order);

        Http::assertSent(function (Request $request): bool {
            return str_contains($request['message'], 'Espresso')
                && str_contains($request['message'], 'Cappuccino')
                && str_contains($request['message'], 'Flat White')
                && ! str_contains($request['message'], 'Cold Brew');
        });
    }

    public function test_message_omits_items_when_order_has_none(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        Http::fake();
        $order = $this->makeOrder();
        $order->items()->delete();

        SendOrderConfirmation::dispatchSync($order);

        Http::assertSent(function (Request $request): bool {
            return ! str_contains($request['message'], 'Item:')
                && str_contains($request['message'], config('shop.name'))
                && ! str_contains($request['message'], ':shop');
        });
    }

    public function test_service_treats_fonnte_logical_failure_as_failure(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'request invalid on disconnected device'], 200),
        ]);
        $order = $this->makeOrder();

        $result = app(FonnteWhatsApp::class)->send($order->customer_phone, 'pesan');

        $this->assertFalse($result);
    }

    protected function makeOrder(array $attributes = []): Order
    {
        $admin = Admin::factory()->create();

        $order = Order::withoutEvents(fn () => Order::create([
            ...[
                'order_number' => 'ORD-'.fake()->unique()->numberBetween(100, 999),
                'customer_phone' => '081234567890',
                'status' => OrderStatus::Pending,
                'total' => 25000,
                'created_by' => $admin->id,
            ],
            ...$attributes,
        ]));

        $order->items()->createMany([
            ['menu_item_id' => 1, 'name' => 'Espresso', 'price' => 15000, 'qty' => 1, 'subtotal' => 15000],
            ['menu_item_id' => 2, 'name' => 'Cappuccino', 'price' => 10000, 'qty' => 1, 'subtotal' => 10000],
        ]);

        return $order;
    }
}
