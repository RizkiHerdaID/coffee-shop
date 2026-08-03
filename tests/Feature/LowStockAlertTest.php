<?php

namespace Tests\Feature;

use App\Models\StockItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LowStockAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function makeItem(array $attributes = []): StockItem
    {
        return StockItem::create([
            'name' => 'Susu',
            'unit' => 'liter',
            'quantity' => 2,
            'min_threshold' => 5,
            ...$attributes,
        ]);
    }

    protected function fakeFonnte(): void
    {
        config(['whatsapp.fonnte.token' => 'test-token', 'whatsapp.low_stock.phone' => '081234567890']);
        Http::fake([
            config('whatsapp.fonnte.url') => Http::response(['status' => true], 200),
        ]);
    }

    public function test_sends_alert_for_low_stock_item_and_marks_it_notified(): void
    {
        $this->fakeFonnte();
        $low = $this->makeItem(['name' => 'Susu']);
        $healthy = $this->makeItem(['name' => 'Biji Kopi', 'quantity' => 100]);

        $this->artisan('stock:alert-low')->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            return $request->url() === config('whatsapp.fonnte.url')
                && $request['target'] === '6281234567890'
                && str_contains($request['message'], 'Susu')
                && str_contains($request['message'], '2 liter')
                && str_contains($request['message'], '5');
        });
        Http::assertSentCount(1);

        $this->assertNotNull($low->fresh()->low_stock_notified_at);
        $this->assertNull($healthy->fresh()->low_stock_notified_at);
    }

    public function test_second_run_does_not_resend_for_already_notified_item(): void
    {
        $this->fakeFonnte();
        $this->makeItem(['name' => 'Susu']);

        $this->artisan('stock:alert-low')->assertSuccessful();
        $this->artisan('stock:alert-low')->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_still_low_item_is_not_realerted_within_24_hours(): void
    {
        $this->fakeFonnte();
        $item = $this->makeItem(['name' => 'Susu']);
        $item->forceFill(['low_stock_notified_at' => now()->subHours(23)])->save();
        $notifiedAt = (string) $item->fresh()->low_stock_notified_at;

        $this->artisan('stock:alert-low')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame($notifiedAt, (string) $item->fresh()->low_stock_notified_at);
    }

    public function test_still_low_item_is_realerted_after_24_hours(): void
    {
        $this->fakeFonnte();
        $item = $this->makeItem(['name' => 'Susu']);
        $item->forceFill(['low_stock_notified_at' => now()->subDay()->subHour()])->save();
        $notifiedAt = (string) $item->fresh()->low_stock_notified_at;

        $this->artisan('stock:alert-low')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertNotSame($notifiedAt, (string) $item->fresh()->low_stock_notified_at);
    }

    public function test_restocked_item_is_not_realerted_when_it_dips_again_within_24_hours(): void
    {
        $this->fakeFonnte();
        $item = $this->makeItem(['name' => 'Susu']);
        $item->forceFill(['low_stock_notified_at' => now()->subHours(23)])->save();

        // Recovers above the threshold: no reset of the notified timestamp.
        $item->update(['quantity' => 10]);
        $this->artisan('stock:alert-low')->assertSuccessful();
        Http::assertNothingSent();

        // Dips low again, still inside the 24h pace window: no re-alert.
        $item->update(['quantity' => 2]);
        $this->artisan('stock:alert-low')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertNotNull($item->fresh()->low_stock_notified_at);
    }

    public function test_restocked_item_is_realerted_when_it_dips_again_after_24_hours(): void
    {
        $this->fakeFonnte();
        $item = $this->makeItem(['name' => 'Susu']);
        $item->forceFill(['low_stock_notified_at' => now()->subDay()->subHour()])->save();

        $item->update(['quantity' => 10]);
        $this->artisan('stock:alert-low')->assertSuccessful();
        Http::assertNothingSent();

        $item->update(['quantity' => 2]);
        $this->artisan('stock:alert-low')->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_blank_phone_logs_warning_and_succeeds_without_sending(): void
    {
        config(['whatsapp.fonnte.token' => 'test-token', 'whatsapp.low_stock.phone' => null]);
        Log::spy();
        $item = $this->makeItem(['name' => 'Susu']);

        $this->artisan('stock:alert-low')->assertSuccessful();

        Log::shouldHaveReceived('warning')->once();
        Http::assertNothingSent();
        $this->assertNull($item->fresh()->low_stock_notified_at);
    }

    public function test_missing_token_logs_warning_and_leaves_item_unnotified(): void
    {
        config(['whatsapp.fonnte.token' => null, 'whatsapp.low_stock.phone' => '081234567890']);
        Log::spy();
        $item = $this->makeItem(['name' => 'Susu']);

        $this->artisan('stock:alert-low')->assertSuccessful();

        Log::shouldHaveReceived('warning')->once();
        Http::assertNothingSent();
        $this->assertNull($item->fresh()->low_stock_notified_at);
    }
}
