<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

        Notification::make()
            ->title(__('pos.order_created', ['order_number' => $order->order_number]))
            ->success()
            ->send();
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
