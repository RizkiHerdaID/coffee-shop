<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shift;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    /**
     * Free-text modifiers per cart line, keyed by menu item id.
     *
     * @var array<int, string>
     */
    public array $cartNotes = [];

    /**
     * Free-text note for the whole order (printed on the kitchen ticket).
     */
    public ?string $notes = null;

    public string $category = '';

    public ?string $customerPhone = null;

    /**
     * Cart-level discount type: '' (none), 'fixed' (IDR off the gross
     * total) or 'percent' (off the gross total).
     */
    public string $discountType = '';

    /**
     * Discount input value, Indonesian thousands separators for fixed
     * ("10.000") or plain digits for percent ("10").
     */
    public string $discountAmount = '';

    /**
     * Order awaiting/processing payment. createOrder() auto-selects the new
     * order; selectOrder() re-selects an existing pending order.
     */
    public ?int $selectedOrderId = null;

    public string $paymentMethod = PaymentMethod::Cash->value;

    /**
     * Amount charged, Indonesian thousands separators allowed (e.g. "100.000");
     * also accepts plain digits. For cash this is the tendered amount (change
     * allowed); for QRIS/e-wallet it may be a PARTIAL amount, blank settles
     * the remaining exactly.
     */
    public string $paymentAmount = '';

    public string $paymentReference = '';

    /**
     * Change due from the last cash payment (IDR integer; 0 when none).
     */
    public int $changeDue = 0;

    /**
     * Ingredient names skipped by consumeRecipeStock() because of
     * insufficient stock, collected per order for the lenient warning.
     *
     * @var array<int, string>
     */
    protected array $skippedStockIngredients = [];

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
            unset($this->cart[$menuItemId], $this->cartNotes[$menuItemId]);
        }
    }

    public function removeItem(int $menuItemId): void
    {
        unset($this->cart[$menuItemId], $this->cartNotes[$menuItemId]);
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->cartNotes = [];
        $this->discountType = '';
        $this->discountAmount = '';
    }

    /**
     * One-tap repeat: load the most recent order's items into the cart.
     *
     * Replaces the current cart (items and per-line notes) with the last
     * order's lines; order items whose menu item is missing or unavailable
     * are skipped. No-op with an info notification when no order exists yet.
     */
    public function repeatOrder(): void
    {
        $lastOrder = Order::query()
            ->with('items')
            ->latest('id')
            ->first();

        if ($lastOrder === null) {
            Notification::make()
                ->title(__('dashboard.repeat_no_previous'))
                ->info()
                ->send();

            return;
        }

        $availableIds = MenuItem::query()
            ->whereIn('id', $lastOrder->items->pluck('menu_item_id')->filter())
            ->where('available', true)
            ->pluck('id')
            ->flip();

        $this->clearCart();

        foreach ($lastOrder->items as $line) {
            if ($line->menu_item_id === null || ! isset($availableIds[$line->menu_item_id])) {
                continue;
            }

            $this->cart[$line->menu_item_id] = $line->qty;

            if (filled($line->notes)) {
                $this->cartNotes[$line->menu_item_id] = $line->notes;
            }
        }
    }

    /**
     * Whether any order exists yet (drives the repeat-last-order button).
     */
    public function getHasPreviousOrdersProperty(): bool
    {
        return Order::query()->exists();
    }

    public function createOrder(): void
    {
        $lines = $this->cartLines;

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['cart' => __('pos.cart_empty')]);
        }

        $this->validate([
            'customerPhone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
            'cartNotes' => ['nullable', 'array'],
            'cartNotes.*' => ['nullable', 'string', 'max:500'],
            'discountType' => ['nullable', Rule::in(['', 'fixed', 'percent'])],
        ]);

        [$discountType, $discountAmount] = $this->normalizeDiscount($lines->sum('subtotal'));

        $order = DB::transaction(function () use ($lines, $discountType, $discountAmount): Order {
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'status' => OrderStatus::Pending,
                'total' => $lines->sum('subtotal'),
                'discount_type' => $discountType,
                'discount_amount' => $discountAmount,
                'customer_phone' => filled($this->customerPhone) ? $this->customerPhone : null,
                'notes' => filled($this->notes) ? trim($this->notes) : null,
                'shift_id' => Shift::active()?->id,
                'created_by' => auth('admin')->id(),
            ]);

            $menuItems = config('pos.deduct_stock')
                ? MenuItem::query()
                    ->with('ingredients')
                    ->whereIn('id', $lines->pluck('item')->pluck('id')->unique())
                    ->get()
                    ->keyBy('id')
                : new Collection;

            foreach ($lines as $line) {
                $orderItem = $order->items()->create([
                    'menu_item_id' => $line['item']->id,
                    'name' => $line['item']->name,
                    'price' => $line['item']->price,
                    'qty' => $line['qty'],
                    'subtotal' => $line['subtotal'],
                    'notes' => filled($line['notes']) ? trim($line['notes']) : null,
                ]);

                $this->consumeRecipeStock($order, $orderItem, $line['qty'], $menuItems);
            }

            return $order;
        });

        $this->cart = [];
        $this->cartNotes = [];
        $this->notes = null;
        $this->customerPhone = null;
        $this->discountType = '';
        $this->discountAmount = '';
        $this->selectOrder($order->id);

        if ($this->skippedStockIngredients !== []) {
            Log::warning("Stock deduction skipped for order {$order->order_number}: insufficient stock for ".implode(', ', $this->skippedStockIngredients));

            Notification::make()
                ->title(__('pos.stock.warning_title'))
                ->body(__('pos.stock.skipped', ['ingredients' => implode(', ', $this->skippedStockIngredients)]))
                ->warning()
                ->send();

            $this->skippedStockIngredients = [];
        }

        Notification::make()
            ->title(__('pos.order_created', ['order_number' => $order->order_number]))
            ->success()
            ->send();
    }

    /**
     * Validate the cart discount and return [discount_type, discount_amount]
     * for persistence (both null when no discount is set). Throws a
     * ValidationException keyed on 'discountAmount' (or 'discountType' for an
     * unknown type) when the input is invalid.
     *
     * @return array{0: string|null, 1: int|null}
     */
    protected function normalizeDiscount(int $gross): array
    {
        if ($this->discountType === '') {
            return [null, null];
        }

        if ($this->discountType === 'fixed') {
            if (! preg_match('/^(\d{1,3}(\.\d{3})*|\d+)$/', $this->discountAmount)) {
                throw ValidationException::withMessages(['discountAmount' => __('dashboard.discount_invalid')]);
            }

            $amount = (int) str_replace('.', '', $this->discountAmount);

            if ($amount < 1 || $amount > $gross) {
                throw ValidationException::withMessages(['discountAmount' => __('dashboard.discount_exceeds_total')]);
            }

            return ['fixed', $amount];
        }

        if ($this->discountType === 'percent') {
            if (! preg_match('/^\d+$/', $this->discountAmount)) {
                throw ValidationException::withMessages(['discountAmount' => __('dashboard.discount_invalid')]);
            }

            $percent = (int) $this->discountAmount;

            if ($percent < 1 || $percent > 100) {
                throw ValidationException::withMessages(['discountAmount' => __('dashboard.discount_percent_range')]);
            }

            return ['percent', $percent];
        }

        throw ValidationException::withMessages(['discountType' => __('dashboard.discount_invalid')]);
    }

    /**
     * Deduct recipe ingredients as 'out' stock movements linked to the
     * order item. Lenient mode: insufficient stock skips the ingredient
     * (stockOut returns false) instead of blocking the sale; skipped
     * ingredient names are collected for a single warning notification.
     *
     * @param  Collection<int, MenuItem>  $menuItems
     */
    protected function consumeRecipeStock(Order $order, OrderItem $orderItem, int $lineQty, Collection $menuItems): void
    {
        $item = $menuItems[$orderItem->menu_item_id] ?? null;

        if ($item === null || $item->ingredients->isEmpty()) {
            return;
        }

        foreach ($item->ingredients->sortBy('id') as $ingredient) {
            $needed = (int) $ingredient->pivot->quantity * $lineQty;

            if ($needed < 1) {
                continue;
            }

            $deducted = $ingredient->stockOut(
                $needed,
                note: "{$order->order_number} {$orderItem->name}",
                orderItemId: $orderItem->id,
            );

            if (! $deducted) {
                $this->skippedStockIngredients[] = $ingredient->name;
            }
        }
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
            // QRIS / e-wallet: take a partial amount when entered; blank
            // settles the remaining exactly.
            if (blank($this->paymentAmount)) {
                $applied = $order->remaining;
            } else {
                $applied = (int) str_replace('.', '', (string) $this->paymentAmount);

                if ($applied < 1) {
                    throw ValidationException::withMessages(['paymentAmount' => __('pos.payment.amount_min')]);
                }

                if ($applied > $order->remaining) {
                    throw ValidationException::withMessages(['paymentAmount' => __('dashboard.amount_exceeds_remaining')]);
                }
            }

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

    /**
     * Fill the payment amount with the order's remaining balance so a single
     * capture settles the rest. No-op without a selected pending order.
     */
    public function payRest(): void
    {
        $order = $this->selectedOrder;

        if ($order === null || $order->remaining < 1) {
            return;
        }

        $this->paymentAmount = number_format($order->remaining, 0, ',', '.');
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
                    'notes' => (string) ($this->cartNotes[$menuItemId] ?? ''),
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

    /**
     * Effective cart discount in IDR based on the current input; 0 while no
     * discount is set or the input is not yet a valid amount.
     */
    public function getCartDiscountValueProperty(): int
    {
        $gross = $this->cartTotal;

        if ($this->discountType === 'fixed') {
            $amount = (int) str_replace('.', '', (string) $this->discountAmount);

            return $amount < 1 ? 0 : min($amount, $gross);
        }

        if ($this->discountType === 'percent') {
            $percent = (int) $this->discountAmount;

            return $percent < 1 || $percent > 100 ? 0 : (int) round($gross * $percent / 100);
        }

        return 0;
    }

    /**
     * Gross cart total minus the effective discount.
     */
    public function getCartNetTotalProperty(): int
    {
        return max($this->cartTotal - $this->cartDiscountValue, 0);
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
