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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SendOrderConfirmation job robustness (Vikunja 112, job part): the job
 * re-reads the order from the database inside handle(), so a serialized
 * (possibly stale) model or an empty item set can never crash it, and a
 * deleted order is skipped silently. The customer phone is normalized
 * before the message is sent.
 */
class SendOrderConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MenuSeeder::class);

        Http::preventStrayRequests();
    }

    protected function makeOrder(array $attributes = []): Order
    {
        $admin = Admin::factory()->create();

        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'ORD-'.fake()->unique()->numberBetween(100, 999),
            'customer_phone' => '081234567890',
            'status' => OrderStatus::Pending,
            'total' => 25000,
            'created_by' => $admin->id,
            ...$attributes,
        ]));

        $order->items()->createMany([
            ['menu_item_id' => 1, 'name' => 'Espresso', 'price' => 15000, 'qty' => 1, 'subtotal' => 15000],
            ['menu_item_id' => 2, 'name' => 'Cappuccino', 'price' => 10000, 'qty' => 1, 'subtotal' => 10000],
        ]);

        return $order;
    }

    protected function fakeFonnte(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);

        Http::fake([
            config('whatsapp.fonnte.url') => Http::response(['status' => true], 200),
        ]);
    }

    public function test_job_sends_confirmation_including_items(): void
    {
        $this->fakeFonnte();
        $order = $this->makeOrder(['order_number' => 'ORD-001']);

        SendOrderConfirmation::dispatchSync($order, app()->getLocale());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === config('whatsapp.fonnte.url')
                && $request['target'] === '6281234567890'
                && str_contains($request['message'], 'ORD-001')
                && str_contains($request['message'], 'Espresso')
                && str_contains($request['message'], 'Cappuccino')
                && str_contains($request['message'], 'Rp 25.000');
        });
        Http::assertSentCount(1);
    }

    public function test_job_renders_confirmation_without_items_when_items_are_empty(): void
    {
        // Sync-queue edge: the job may run before the items are attached,
        // so an empty item set must render the no-items template.
        $this->fakeFonnte();
        $order = $this->makeOrder();
        $order->items()->delete();

        SendOrderConfirmation::dispatchSync($order, app()->getLocale());

        Http::assertSent(function (Request $request): bool {
            return ! str_contains($request['message'], 'Item:')
                && str_contains($request['message'], config('shop.name'))
                && ! str_contains($request['message'], ':shop');
        });
        Http::assertSentCount(1);
    }

    public function test_job_skips_silently_when_order_no_longer_exists(): void
    {
        $this->fakeFonnte();
        $order = $this->makeOrder();

        DB::table('order_items')->where('order_id', $order->id)->delete();
        DB::table('orders')->where('id', $order->id)->delete();

        (new SendOrderConfirmation($order, app()->getLocale()))->handle(app(FonnteWhatsApp::class));

        Http::assertNothingSent();
    }

    public function test_job_normalizes_customer_phone_before_send(): void
    {
        $this->fakeFonnte();
        $order = $this->makeOrder(['customer_phone' => '+62 812-3456-7890']);

        SendOrderConfirmation::dispatchSync($order, app()->getLocale());

        Http::assertSent(fn (Request $request): bool => $request['target'] === '6281234567890');
        Http::assertSentCount(1);
    }

    public function test_job_renders_message_in_locale_captured_at_dispatch(): void
    {
        // The locale is captured when the job is constructed (dispatch
        // time); the queue worker's default locale must not leak in.
        $this->fakeFonnte();
        app()->setLocale('en');
        $order = $this->makeOrder(['order_number' => 'ORD-LOC']);
        $job = new SendOrderConfirmation($order, 'en');

        app()->setLocale('id');
        $job->handle(app(FonnteWhatsApp::class));

        Http::assertSent(function (Request $request): bool {
            return str_contains($request['message'], 'Hello! We received your order ORD-LOC')
                && ! str_contains($request['message'], 'Halo!');
        });
    }
}
