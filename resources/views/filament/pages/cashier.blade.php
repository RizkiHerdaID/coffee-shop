<x-filament-panels::page>
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
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $line['item']->name }}</p>
                            <p class="text-xs text-gray-500">Rp {{ number_format($line['item']->price, 0, ',', '.') }} × {{ $line['qty'] }}</p>
                            <p class="text-sm font-bold text-amber-600">Rp {{ number_format($line['subtotal'], 0, ',', '.') }}</p>
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
    </div>
</x-filament-panels::page>
