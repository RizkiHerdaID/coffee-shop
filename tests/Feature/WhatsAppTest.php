<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\SendOrderConfirmation;
use App\Models\Admin;
use App\Models\Order;
use App\Services\FonnteWhatsApp;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        Http::fake([
            config('whatsapp.fonnte.url') => Http::response(['status' => true], 200),
        ]);
        $order = $this->makeOrder(['order_number' => 'ORD-001']);

        SendOrderConfirmation::dispatchSync($order);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === config('whatsapp.fonnte.url')
                && $request->hasHeader('Authorization', 'test-token')
                && $request['target'] === '6281234567890'
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
        Http::fake([
            config('whatsapp.fonnte.url') => Http::response(['status' => true], 200),
        ]);
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
        Http::fake([
            config('whatsapp.fonnte.url') => Http::response(['status' => true], 200),
        ]);
        $order = $this->makeOrder();
        $order->items()->delete();

        SendOrderConfirmation::dispatchSync($order);

        Http::assertSent(function (Request $request): bool {
            return ! str_contains($request['message'], 'Item:')
                && str_contains($request['message'], config('shop.name'))
                && ! str_contains($request['message'], ':shop');
        });
    }

    // ---------------------------------------------------------------------
    // Service-level HTTP behavior (Vikunja 110): success requires a JSON
    // body with a truthy status; non-JSON bodies, HTTP errors and network
    // exceptions are retried with backoff and then fail with a warning.
    // Delays are injected as [0] / maxAttempts 1 to keep the suite fast.
    // ---------------------------------------------------------------------

    public function test_service_treats_non_json_success_response_as_failure(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        Log::spy();
        Http::fake([
            config('whatsapp.fonnte.url') => Http::response('OK', 200),
        ]);

        $result = $this->makeService(maxAttempts: 1)->send('081234567890', 'pesan');

        $this->assertFalse($result);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_service_treats_fonnte_logical_failure_as_failure(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'request invalid on disconnected device'], 200),
        ]);

        $result = $this->makeService(maxAttempts: 1)->send('081234567890', 'pesan');

        $this->assertFalse($result);
    }

    public function test_service_retries_transient_json_failure_then_succeeds(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        $attempts = 0;
        Http::fake([
            config('whatsapp.fonnte.url') => function () use (&$attempts) {
                $attempts++;

                return $attempts === 1
                    ? Http::response(['status' => false, 'reason' => 'temporary error'], 200)
                    : Http::response(['status' => true], 200);
            },
        ]);

        $result = $this->makeService(retryDelays: [0, 0])->send('081234567890', 'pesan');

        $this->assertTrue($result);
        $this->assertSame(2, $attempts);
    }

    public function test_service_retries_network_error_then_succeeds(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        $attempts = 0;
        Http::fake([
            config('whatsapp.fonnte.url') => function () use (&$attempts) {
                $attempts++;

                if ($attempts === 1) {
                    throw new ConnectionException('Connection timed out');
                }

                return Http::response(['status' => true], 200);
            },
        ]);

        $result = $this->makeService(retryDelays: [0, 0])->send('081234567890', 'pesan');

        $this->assertTrue($result);
        $this->assertSame(2, $attempts);
    }

    public function test_service_normalizes_phone_before_sending(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        Http::fake([
            config('whatsapp.fonnte.url') => Http::response(['status' => true], 200),
        ]);

        $result = $this->makeService(maxAttempts: 1)->send('0812-3456-7890', 'pesan');

        $this->assertTrue($result);
        Http::assertSent(fn (Request $request): bool => $request['target'] === '6281234567890');
    }

    public function test_service_returns_false_for_an_empty_phone_without_http(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        Log::spy();
        Http::fake();

        $result = $this->makeService(maxAttempts: 1)->send('', 'pesan');

        $this->assertFalse($result);
        Log::shouldHaveReceived('warning')->once();
        Http::assertNothingSent();
    }

    protected function makeService(int $maxAttempts = 3, array $retryDelays = [1, 3]): FonnteWhatsApp
    {
        return new FonnteWhatsApp(
            token: 'test-token',
            baseUrl: config('whatsapp.fonnte.url'),
            maxAttempts: $maxAttempts,
            retryDelays: $retryDelays,
        );
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
