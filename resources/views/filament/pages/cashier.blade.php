<x-filament-panels::page>
    @if (\App\Models\Shift::active() === null)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">
            {{ __('pos.shift.no_active_notice') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="$set('category', '')"
                    class="rounded-full border px-4 py-1.5 text-sm font-semibold transition {{ $category === '' ? 'border-amber-500 bg-amber-500/10 text-amber-600' : 'border-gray-300 text-gray-600 hover:border-amber-500 hover:text-amber-600' }}"
                >
                    {{ __('menu.categories.all') }}
                </button>
                @foreach ($this->categories as $category)
                    <button
                        type="button"
                        wire:click="$set('category', '{{ $category }}')"
                        class="rounded-full border px-4 py-1.5 text-sm font-semibold transition {{ $this->category === $category ? 'border-amber-500 bg-amber-500/10 text-amber-600' : 'border-gray-300 text-gray-600 hover:border-amber-500 hover:text-amber-600' }}"
                    >
                        {{ __("menu.categories.$category") }}
                    </button>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($this->menuItems as $item)
                    <button
                        type="button"
                        wire:click="addToCart({{ $item->id }})"
                        class="flex flex-col items-start gap-1 rounded-2xl border border-gray-300 bg-white p-4 text-left shadow-sm transition hover:border-amber-500 hover:shadow"
                    >
                        <span class="text-base font-bold text-gray-900">{{ $item->name }}</span>
                        <span class="text-sm font-semibold text-amber-600">Rp {{ number_format($item->price, 0, ',', '.') }}</span>
                        @if (filled($item->note))
                            <span class="text-xs text-gray-500">{{ $item->note }}</span>
                        @endif
                    </button>
                @empty
                    <p class="col-span-full text-sm text-gray-500">{{ __('menu.empty') }}</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            <div class="h-fit rounded-2xl border border-gray-300 bg-white p-5 shadow-sm lg:sticky lg:top-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-gray-900">{{ __('pos.cart_section') }}</h2>
                    @unless ($this->cartLines->isEmpty())
                        <x-filament::button color="gray" size="sm" icon="heroicon-m-trash" wire:click="clearCart">
                            {{ __('pos.clear_cart') }}
                        </x-filament::button>
                    @endunless
                </div>

                <div class="space-y-3">
                    @forelse ($this->cartLines as $line)
                        <div class="flex items-start justify-between gap-2 rounded-xl border border-gray-200 p-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $line['item']->name }}</p>
                                <p class="text-xs text-gray-500">Rp {{ number_format($line['item']->price, 0, ',', '.') }} × {{ $line['qty'] }}</p>
                                <p class="text-sm font-bold text-amber-600">Rp {{ number_format($line['subtotal'], 0, ',', '.') }}</p>
                                <label for="line-notes-{{ $line['item']->id }}" class="mt-2 block text-xs font-medium text-gray-600">
                                    {{ __('dashboard.line_notes') }}
                                </label>
                                <textarea
                                    id="line-notes-{{ $line['item']->id }}"
                                    rows="2"
                                    wire:model.blur="cartNotes.{{ $line['item']->id }}"
                                    placeholder="{{ __('dashboard.line_notes_placeholder') }}"
                                    class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 placeholder:text-gray-400 focus:border-amber-500 focus:ring-amber-500"
                                ></textarea>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <x-filament::icon-button icon="heroicon-m-minus" size="sm" wire:click="decrementItem({{ $line['item']->id }})" :tooltip="__('pos.qty')" />
                                <span class="w-6 text-center text-sm font-semibold text-gray-900">{{ $line['qty'] }}</span>
                                <x-filament::icon-button icon="heroicon-m-plus" size="sm" wire:click="incrementItem({{ $line['item']->id }})" :tooltip="__('pos.qty')" />
                                <x-filament::icon-button icon="heroicon-m-x-mark" size="sm" color="danger" wire:click="removeItem({{ $line['item']->id }})" :tooltip="__('pos.remove')" />
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-500">{{ __('pos.empty_cart') }}</p>
                    @endforelse
                </div>

                <div class="mt-4 border-t border-gray-200 pt-4">
                    <label for="cashier-customer-phone" class="mb-1 block text-sm font-medium text-gray-700">
                        {{ __('pos.customer_phone') }}
                    </label>
                    <input
                        id="cashier-customer-phone"
                        type="tel"
                        wire:model="customerPhone"
                        placeholder="{{ __('pos.customer_phone_placeholder') }}"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-amber-500 focus:ring-amber-500"
                    >

                    <label for="cashier-order-notes" class="mt-4 block text-sm font-medium text-gray-700">
                        {{ __('dashboard.order_notes') }}
                    </label>
                    <textarea
                        id="cashier-order-notes"
                        rows="2"
                        wire:model.blur="notes"
                        placeholder="{{ __('dashboard.order_notes_placeholder') }}"
                        class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-amber-500 focus:ring-amber-500"
                    ></textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                    @enderror

                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-600">{{ __('pos.total') }}</span>
                        <span class="text-xl font-bold text-gray-900">Rp {{ number_format($this->cartTotal, 0, ',', '.') }}</span>
                    </div>

                    @error('cart')
                        <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
                    @enderror

                    <x-filament::button class="mt-4 w-full" :disabled="$this->cartLines->isEmpty()" wire:click="createOrder">
                        {{ __('pos.create_order') }}
                    </x-filament::button>
                </div>
            </div>

            @if ($this->selectedOrder !== null)
                <div wire:key="payment-panel-{{ $this->selectedOrder->id }}" class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm">
                    @if ($this->selectedOrder->status === \App\Enums\OrderStatus::Pending)
                        <h2 class="text-lg font-bold text-gray-900">{{ __('pos.payment.title') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ __('pos.payment.order', ['order_number' => $this->selectedOrder->order_number]) }}
                        </p>

                        <div class="mt-4 space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">{{ __('pos.total') }}</span>
                                <span class="font-semibold text-gray-900">Rp {{ number_format($this->selectedOrder->total, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">{{ __('pos.paid') }}</span>
                                <span class="font-semibold text-green-600">Rp {{ number_format($this->selectedOrder->paid_total, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-200 pt-2">
                                <span class="font-medium text-gray-700">{{ __('pos.payment.remaining') }}</span>
                                <span class="text-lg font-bold text-gray-900">Rp {{ number_format($this->selectedOrder->remaining, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div class="mt-4">
                            <p class="mb-2 text-sm font-medium text-gray-700">{{ __('pos.payment.method.title') }}</p>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach ([\App\Enums\PaymentMethod::Cash, \App\Enums\PaymentMethod::Qris, \App\Enums\PaymentMethod::Ewallet] as $method)
                                    <button
                                        type="button"
                                        wire:click="$set('paymentMethod', '{{ $method->value }}')"
                                        class="rounded-lg border px-2 py-2 text-sm font-semibold transition {{ $this->paymentMethod === $method->value ? 'border-amber-500 bg-amber-500/10 text-amber-600' : 'border-gray-300 text-gray-600 hover:border-amber-500 hover:text-amber-600' }}"
                                    >
                                        {{ $method->getLabel() }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        @if ($this->paymentMethod === \App\Enums\PaymentMethod::Qris->value)
                            <div class="mt-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 text-center">
                                @if (config('pos.qris.image'))
                                    <img
                                        src="{{ asset(config('pos.qris.image')) }}"
                                        alt="{{ __('pos.payment.qris_alt') }}"
                                        class="mx-auto h-44 w-44 object-contain"
                                    >
                                @else
                                    <div class="mx-auto flex h-44 w-44 items-center justify-center rounded-lg bg-white">
                                        <span class="px-4 text-center text-xs text-gray-400">{{ __('pos.payment.qris_placeholder') }}</span>
                                    </div>
                                @endif
                                <p class="mt-2 text-sm font-medium text-gray-700">{{ __('pos.payment.qris_scan') }}</p>
                            </div>
                        @endif

                        @if ($this->paymentMethod === \App\Enums\PaymentMethod::Cash->value)
                            <div class="mt-4">
                                <label for="cashier-payment-amount" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('pos.payment.tendered') }}
                                </label>
                                <input
                                    id="cashier-payment-amount"
                                    type="text"
                                    inputmode="numeric"
                                    wire:model="paymentAmount"
                                    x-data="{ money: (v) => { const digits = String(v).replace(/[^\d]/g, ''); return digits === '' ? '' : Number(digits).toLocaleString('id-ID'); } }"
                                    x-mask:dynamic="money($input)"
                                    placeholder="{{ __('pos.payment.tendered_placeholder') }}"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-amber-500 focus:ring-amber-500"
                                >
                                @error('paymentAmount')
                                    <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        @if ($this->paymentMethod !== \App\Enums\PaymentMethod::Cash->value)
                            <div class="mt-4">
                                <label for="cashier-payment-reference" class="mb-1 block text-sm font-medium text-gray-700">
                                    {{ __('pos.payment.reference') }}
                                </label>
                                <input
                                    id="cashier-payment-reference"
                                    type="text"
                                    wire:model="paymentReference"
                                    placeholder="{{ __('pos.payment.reference_placeholder') }}"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-amber-500 focus:ring-amber-500"
                                >
                            </div>
                        @endif

                        @error('payment')
                            <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
                        @enderror

                        @if ($this->changeDue > 0)
                            <div class="mt-4 flex items-center justify-between rounded-xl border border-green-200 bg-green-50 px-4 py-3">
                                <span class="text-sm font-semibold text-green-800">{{ __('pos.payment.change') }}</span>
                                <span class="text-lg font-bold text-green-700">Rp {{ number_format($this->changeDue, 0, ',', '.') }}</span>
                            </div>
                        @endif

                        <x-filament::button class="mt-4 w-full" color="success" wire:click="capturePayment">
                            {{ __('pos.payment.pay') }}
                        </x-filament::button>
                    @else
                        <h2 class="text-lg font-bold text-gray-900">{{ __('pos.payment.receipt_section') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ __('pos.payment.order', ['order_number' => $this->selectedOrder->order_number]) }}
                        </p>

                        <div class="mt-4 space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">{{ __('pos.total') }}</span>
                                <span class="font-semibold text-gray-900">Rp {{ number_format($this->selectedOrder->total, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">{{ __('pos.paid') }}</span>
                                <span class="font-semibold text-green-600">Rp {{ number_format($this->selectedOrder->paid_total, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">{{ __('pos.payment.method.title') }}</span>
                                <span class="font-semibold text-gray-900">
                                    {{ $this->selectedOrder->payments->map(fn ($payment) => $payment->method?->getLabel() ?? __('pos.payment.method.cash'))->implode(', ') }}
                                </span>
                            </div>
                        </div>

                        @if ($this->changeDue > 0)
                            <div class="mt-4 flex items-center justify-between rounded-xl border border-green-200 bg-green-50 px-4 py-3">
                                <span class="text-sm font-semibold text-green-800">{{ __('pos.payment.change') }}</span>
                                <span class="text-lg font-bold text-green-700">Rp {{ number_format($this->changeDue, 0, ',', '.') }}</span>
                            </div>
                        @endif

                        <div class="mt-4 space-y-2">
                            <x-filament::button class="w-full" icon="heroicon-m-printer" tag="a" :href="route('pos.receipt', $this->selectedOrder)" target="_blank">
                                {{ __('pos.payment.print_receipt') }}
                            </x-filament::button>

                            @if ($this->selectedOrder->status === \App\Enums\OrderStatus::Paid)
                                <x-filament::button class="w-full" color="info" icon="heroicon-m-check-badge" wire:click="markServed({{ $this->selectedOrderId }})">
                                    {{ __('pos.actions.mark_served') }}
                                </x-filament::button>
                            @endif

                            <x-filament::button class="w-full" color="gray" icon="heroicon-m-arrow-path" wire:click="startNewOrder">
                                {{ __('pos.payment.new_order') }}
                            </x-filament::button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
