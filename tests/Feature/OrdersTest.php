<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Models\Admin;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class OrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    public function test_create_form_stores_total_as_raw_integer_from_indonesian_formatted_input(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreateOrder::class)
            ->fillForm([
                'order_number' => 'TOTAL-001',
                'customer_phone' => '081234567890',
                'status' => OrderStatus::Pending,
                'total' => '25.000',
                'created_by' => $admin->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('orders', [
            'order_number' => 'TOTAL-001',
            'total' => 25000,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(CreateOrder::class)
            ->fillForm([
                'order_number' => 'TOTAL-002',
                'status' => OrderStatus::Pending,
                'total' => '1.500.000',
                'created_by' => $admin->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('orders', [
            'order_number' => 'TOTAL-002',
            'total' => 1500000,
        ]);
    }

    public function test_edit_form_displays_total_with_indonesian_separators(): void
    {
        $admin = Admin::factory()->create();
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'TOTAL-003',
            'status' => OrderStatus::Pending,
            'total' => 1500000,
            'created_by' => $admin->id,
        ]));

        Livewire::actingAs($admin, 'admin')
            ->test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormSet(['total' => '1.500.000']);
    }
}
