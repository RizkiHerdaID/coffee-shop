<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($this->activeShift !== null)
                <div class="rounded-2xl border border-amber-300 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-gray-900">{{ __('pos.shift.active') }}</h2>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">
                            <span class="h-2 w-2 rounded-full bg-green-500"></span>
                            {{ __('pos.shift.report') }}
                        </span>
                    </div>

                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div>
                            <dt class="text-xs font-medium text-gray-500">{{ __('pos.shift.opened_at') }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $this->activeShift->opened_at->format('d/m/Y H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">{{ __('pos.shift.opened_by') }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $this->activeShift->admin?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">{{ __('pos.shift.orders_count') }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $this->activeShift->paidOrdersCount() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">{{ __('pos.shift.sales_total') }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">Rp {{ number_format($this->activeShift->salesTotal(), 0, ',', '.') }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 border-t border-gray-200 pt-4">
                        <label for="shift-closing-cash" class="mb-1 block text-sm font-medium text-gray-700">
                            {{ __('pos.shift.closing_cash') }}
                        </label>
                        <input
                            id="shift-closing-cash"
                            type="text"
                            inputmode="numeric"
                            wire:model="closingCash"
                            x-data="{ money: (v) => { const digits = String(v).replace(/[^\d]/g, ''); return digits === '' ? '' : Number(digits).toLocaleString('id-ID'); } }"
                            x-mask:dynamic="money($input)"
                            placeholder="{{ __('pos.shift.closing_cash_placeholder') }}"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-amber-500 focus:ring-amber-500"
                        >
                        @error('closingCash')
                            <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                        @enderror

                        @if ($confirmingClose)
                            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                                <p class="text-sm font-medium text-amber-900">{{ __('pos.shift.close_confirm') }}</p>
                                <p class="mt-1 text-xs text-amber-700">
                                    {{ __('pos.shift.sales_total') }}: Rp {{ number_format($this->activeShift->salesTotal(), 0, ',', '.') }}
                                </p>
                                <div class="mt-3 flex gap-2">
                                    <x-filament::button color="danger" wire:click="closeShift">
                                        {{ __('pos.shift.close') }}
                                    </x-filament::button>
                                    <x-filament::button color="gray" wire:click="cancelClose">
                                        {{ __('pos.receipt.close') }}
                                    </x-filament::button>
                                </div>
                            </div>
                        @else
                            <x-filament::button class="mt-4 w-full" color="danger" wire:click="askClose">
                                {{ __('pos.shift.close') }}
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-gray-900">{{ __('pos.shift.open') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ __('pos.shift.no_active') }}</p>

                    <div class="mt-4">
                        <label for="shift-opening-cash" class="mb-1 block text-sm font-medium text-gray-700">
                            {{ __('pos.shift.opening_cash') }}
                        </label>
                        <input
                            id="shift-opening-cash"
                            type="text"
                            inputmode="numeric"
                            wire:model="openingCash"
                            x-data="{ money: (v) => { const digits = String(v).replace(/[^\d]/g, ''); return digits === '' ? '' : Number(digits).toLocaleString('id-ID'); } }"
                            x-mask:dynamic="money($input)"
                            placeholder="{{ __('pos.shift.opening_cash_placeholder') }}"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-amber-500 focus:ring-amber-500"
                        >
                        @error('openingCash')
                            <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                        @enderror

                        <x-filament::button class="mt-4 w-full" color="success" icon="heroicon-m-play" wire:click="openShift">
                            {{ __('pos.shift.open') }}
                        </x-filament::button>
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-gray-900">{{ __('pos.shift.history') }}</h2>

                @forelse ($this->recentShifts as $row)
                    <div class="mt-4 border-t border-gray-200 pt-4 first:mt-0 first:border-t-0 first:pt-0">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900">
                                    #{{ str_pad((string) $row['shift']->id, 4, '0', STR_PAD_LEFT) }}
                                </p>
                                <p class="text-xs text-gray-500">{{ $row['shift']->opened_at->format('d/m/Y H:i') }} — {{ $row['shift']->closed_at->format('H:i') }}</p>
                                <p class="mt-1 text-xs text-gray-600">
                                    {{ $row['orders_count'] }} {{ __('pos.shift.orders_count') }} · Rp {{ number_format($row['sales_total'], 0, ',', '.') }}
                                </p>
                                @if ($row['discrepancy'] !== 0)
                                    <p class="mt-0.5 text-xs {{ $row['discrepancy'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ __('pos.zreport.discrepancy') }}: {{ $row['discrepancy'] > 0 ? '+' : '' }}Rp {{ number_format($row['discrepancy'], 0, ',', '.') }}
                                    </p>
                                @endif
                            </div>
                            <x-filament::button
                                color="gray"
                                size="sm"
                                icon="heroicon-m-document-text"
                                tag="a"
                                :href="\App\Filament\Pages\ShiftReport::getUrl(['record' => $row['shift']->id])"
                            >
                                {{ __('pos.shift.view_report') }}
                            </x-filament::button>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-500">{{ __('pos.shift.empty') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
