<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\MenuItem;
use App\Models\Order;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Cashier extends Page
{
    protected string $view = 'filament.pages.cashier';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = 1;

    /**
     * @var array<int, int> menu item id => quantity
     */
    public array $cart = [];

    public string $category = '';

    public ?string $customerPhone = null;

    /**
     * Order awaiting/processing payment. createOrder() auto-selects the new
     * order; selectOrder() re-selects an existing pending order.
     */
    public ?int $selectedOrderId = null;

    public string $paymentMethod = PaymentMethod::Cash->value;

    /**
     * Tendered cash, Indonesian thousands separators allowed (e.g. "100.000");
     * also accepts plain digits. QRIS/e-wallet ignore this and settle the
     * remaining amount exactly.
     */
    public string $paymentAmount = '';

    public string $paymentReference = '';

    /**
     * Change due from the last cash payment (IDR integer; 0 when none).
     */
    public int $changeDue = 0;

    public static function getNavigationLabel(): string
    {
        return __('pos.nav_label');
    }

    public function getTitle(): string
    {
        return __('pos.page_title');
    }

    public function addToCart(int $menuItemId): void
    {
        $item = MenuItem::query()->find($menuItemId);

        if ($item === null || ! $item->available) {
            return;
        }

        $this->cart[$menuItemId] = ($this->cart[$menuItemId] ?? 0) + 1;
    }

    public function incrementItem(int $menuItemId): void
    {
        if (isset($this->cart[$menuItemId])) {
            $this->cart[$menuItemId]++;
        }
    }

    public function decrementItem(int $menuItemId): void
    {
        if (! isset($this->cart[$menuItemId])) {
            return;
        }

        $this->cart[$menuItemId]--;

        if ($this->cart[$menuItemId] <= 0) {
            unset($this->cart[$menuItemId]);
        }
    }

    public function removeItem(int $menuItemId): void
    {
        unset($this->cart[$menuItemId]);
    }

    public function clearCart(): void
    {
        $this->cart = [];
    }

    public function createOrder(): void
    {
        $lines = $this->cartLines;

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['cart' => __('pos.cart_empty')]);
        }

        $this->validate([
            'customerPhone' => ['nullable', 'string', 'max:20'],
        ]);

        $order = DB::transaction(function () use ($lines): Order {
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'status' => OrderStatus::Pending,
                'total' => $lines->sum('subtotal'),
                'customer_phone' => filled($this->customerPhone) ? $this->customerPhone : null,
                'created_by' => auth('admin')->id(),
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'menu_item_id' => $line['item']->id,
                    'name' => $line['item']->name,
                    'price' => $line['item']->price,
                    'qty' => $line['qty'],
                    'subtotal' => $line['subtotal'],
                ]);
            }

            return $order;
        });

        $this->cart = [];
        $this->customerPhone = null;
        $this->selectOrder($order->id);

        Notification::make()
            ->title(__('pos.order_created', ['order_number' => $order->order_number]))
            ->success()
            ->send();
    }

    /**
     * Re-select an existing order for payment and reset the payment form.
     */
    public function selectOrder(int $orderId): void
    {
        if (Order::query()->whereKey($orderId)->doesntExist()) {
            return;
        }

        $this->selectedOrderId = $orderId;
        $this->paymentMethod = PaymentMethod::Cash->value;
        $this->paymentReference = '';
        $this->changeDue = 0;

        $order = $this->selectedOrder;
        $this->paymentAmount = $order !== null && $order->remaining > 0
            ? number_format($order->remaining, 0, ',', '.')
            : '';
    }

    public function capturePayment(): void
    {
        $order = $this->selectedOrder;

        if ($order === null) {
            throw ValidationException::withMessages(['selectedOrderId' => __('pos.payment.no_order')]);
        }

        if ($order->status !== OrderStatus::Pending) {
            throw ValidationException::withMessages(['selectedOrderId' => __('pos.payment.already_paid')]);
        }

        $this->validate([
            'paymentMethod' => ['required', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'paymentAmount' => ['nullable', 'regex:/^(\d{1,3}(\.\d{3})*|\d+)$/'],
            'paymentReference' => ['nullable', 'string', 'max:120'],
        ]);

        $method = PaymentMethod::tryFrom($this->paymentMethod) ?? PaymentMethod::Cash;

        if ($method === PaymentMethod::Cash) {
            $tendered = (int) str_replace('.', '', (string) $this->paymentAmount);

            if (blank($this->paymentAmount)) {
                throw ValidationException::withMessages(['paymentAmount' => __('pos.payment.amount_required')]);
            }

            if ($tendered < 1) {
                throw ValidationException::withMessages(['paymentAmount' => __('pos.payment.amount_min')]);
            }

            $applied = $tendered;
            $change = max($tendered - $order->remaining, 0);
            $reference = null;
        } else {
            // QRIS / e-wallet settle exactly the remaining amount.
            $applied = $order->remaining;
            $change = 0;
            $reference = filled($this->paymentReference) ? $this->paymentReference : null;
        }

        DB::transaction(function () use ($order, $method, $applied, $reference): void {
            $order->payments()->create([
                'method' => $method,
                'amount' => $applied,
                'reference' => $reference,
                'paid_at' => now(),
                'admin_id' => auth('admin')->id(),
            ]);

            $order->markPaidIfCovered();
        });

        $order->refresh();
        $this->changeDue = $change;
        $this->paymentReference = '';
        $this->paymentAmount = $order->remaining > 0 ? number_format($order->remaining, 0, ',', '.') : '';

        if ($order->status === OrderStatus::Paid) {
            Notification::make()
                ->title(__('pos.payment.paid', ['order_number' => $order->order_number]))
                ->body($change > 0
                    ? __('pos.payment.change_due', ['amount' => 'Rp '.number_format($change, 0, ',', '.')])
                    : null)
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('pos.payment.partial', [
                    'order_number' => $order->order_number,
                    'remaining' => 'Rp '.number_format($order->remaining, 0, ',', '.'),
                ]))
                ->info()
                ->send();
        }
    }

    public function markServed(int $orderId): void
    {
        $order = Order::query()->find($orderId);

        if ($order === null) {
            throw ValidationException::withMessages(['selectedOrderId' => __('pos.payment.no_order')]);
        }

        if ($order->status !== OrderStatus::Paid) {
            throw ValidationException::withMessages(['selectedOrderId' => __('pos.payment.not_paid_yet')]);
        }

        $order->update(['status' => OrderStatus::Served]);

        Notification::make()
            ->title(__('pos.actions.marked_served', ['order_number' => $order->order_number]))
            ->success()
            ->send();
    }

    public function startNewOrder(): void
    {
        $this->selectedOrderId = null;
        $this->paymentMethod = PaymentMethod::Cash->value;
        $this->paymentAmount = '';
        $this->paymentReference = '';
        $this->changeDue = 0;
    }

    public function getMenuItemsProperty(): EloquentCollection
    {
        return MenuItem::query()
            ->where('available', true)
            ->when($this->category !== '', fn ($query) => $query->where('category', $this->category))
            ->orderBy('sort_order')
            ->get();
    }

    public function getCategoriesProperty(): Collection
    {
        return MenuItem::query()
            ->where('available', true)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->values()
            ->sort();
    }

    public function getCartLinesProperty(): Collection
    {
        if ($this->cart === []) {
            return new Collection;
        }

        $items = MenuItem::query()
            ->whereIn('id', array_keys($this->cart))
            ->where('available', true)
            ->get()
            ->keyBy('id');

        return collect($this->cart)
            ->map(fn (int $qty, int $menuItemId): ?array => isset($items[$menuItemId])
                ? [
                    'item' => $items[$menuItemId],
                    'qty' => $qty,
                    'subtotal' => $qty * $items[$menuItemId]->price,
                ]
                : null)
            ->filter()
            ->values();
    }

    public function getCartTotalProperty(): int
    {
        return $this->cartLines->sum('subtotal');
    }

    public function getSelectedOrderProperty(): ?Order
    {
        if ($this->selectedOrderId === null) {
            return null;
        }

        return Order::query()
            ->with(['items', 'payments'])
            ->find($this->selectedOrderId);
    }

    protected function generateOrderNumber(): string
    {
        $prefix = 'ORD-'.now()->format('Ymd').'-';

        $last = Order::query()
            ->where('order_number', 'like', $prefix.'%')
            ->orderByDesc('order_number')
            ->value('order_number');

        $sequence = $last === null ? 1 : ((int) substr($last, -4)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
