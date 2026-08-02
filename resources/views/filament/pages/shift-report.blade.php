<x-filament-panels::page>
    @if ($this->shift !== null)
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-gray-900">{{ __('pos.zreport.title') }} — #{{ str_pad((string) $this->shift->id, 4, '0', STR_PAD_LEFT) }}</h2>
                        <x-filament::button
                            icon="heroicon-m-printer"
                            tag="a"
                            :href="route('pos.zreport', $this->shift)"
                            target="_blank"
                        >
                            {{ __('pos.zreport.print') }}
                        </x-filament::button>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div>
                            <dt class="text-xs font-medium text-gray-500">{{ __('pos.zreport.opened') }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $this->shift->opened_at->format('d/m/Y H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">{{ __('pos.zreport.closed') }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $this->shift->closed_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">{{ __('pos.zreport.closed_by') }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $this->shift->admin?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">{{ __('pos.zreport.orders') }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $this->shift->paidOrdersCount() }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 border-t border-gray-200 pt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('pos.zreport.sales') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">Rp {{ number_format($this->shift->salesTotal(), 0, ',', '.') }}</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500">{{ __('pos.zreport.payments') }}</h3>

                    @php($methods = $this->shift->paymentsByMethod())
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">{{ __('pos.payment.method.cash') }}</dt>
                            <dd class="font-semibold text-gray-900">Rp {{ number_format($methods['cash'], 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">{{ __('pos.payment.method.qris') }}</dt>
                            <dd class="font-semibold text-gray-900">Rp {{ number_format($methods['qris'], 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">{{ __('pos.payment.method.ewallet') }}</dt>
                            <dd class="font-semibold text-gray-900">Rp {{ number_format($methods['ewallet'], 0, ',', '.') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500">{{ __('pos.zreport.cash_count') }}</h3>

                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">{{ __('pos.zreport.opening_cash') }}</dt>
                            <dd class="font-semibold text-gray-900">Rp {{ number_format($this->shift->opening_cash, 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">{{ __('pos.zreport.cash_payments') }}</dt>
                            <dd class="font-semibold text-gray-900">Rp {{ number_format($methods['cash'], 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">{{ __('pos.zreport.cash_refunds') }}</dt>
                            <dd class="font-semibold text-gray-900">Rp {{ number_format($this->shift->cashRefunds(), 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">{{ __('dashboard.cash_movements.deposits_total') }}</dt>
                            <dd class="font-semibold text-green-700">Rp {{ number_format($this->shift->deposits(), 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">{{ __('dashboard.cash_movements.petty_out_total') }}</dt>
                            <dd class="font-semibold text-red-600">Rp {{ number_format($this->shift->pettyOut(), 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-gray-200 pt-2">
                            <dt class="font-medium text-gray-700">{{ __('pos.zreport.expected') }}</dt>
                            <dd class="font-bold text-gray-900">Rp {{ number_format($this->shift->expectedCash(), 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">{{ __('pos.zreport.counted') }}</dt>
                            <dd class="font-semibold text-gray-900">Rp {{ number_format($this->shift->closing_cash ?? 0, 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-gray-200 pt-2">
                            <dt class="font-medium text-gray-700">{{ __('pos.zreport.discrepancy') }}</dt>
                            <dd class="font-bold {{ $this->shift->discrepancy() === 0 ? 'text-green-600' : ($this->shift->discrepancy() > 0 ? 'text-amber-600' : 'text-red-600') }}">
                                @if ($this->shift->discrepancy() === 0)
                                    {{ __('pos.zreport.discrepancy_ok') }}
                                @else
                                    {{ $this->shift->discrepancy() > 0 ? '+' : '−' }}Rp {{ number_format(abs($this->shift->discrepancy()), 0, ',', '.') }}
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <p class="mt-3 text-xs text-gray-500">{{ __('pos.zreport.discrepancy_note') }}</p>
                </div>

                <div class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500">{{ __('dashboard.cash_movements.title') }}</h3>

                    @forelse ($this->shift->cashMovements as $movement)
                        <div class="mt-3 rounded-lg border border-gray-200 px-3 py-2">
                            <p class="text-sm font-semibold {{ $movement->isDeposit() ? 'text-green-700' : 'text-red-600' }}">
                                {{ $movement->isDeposit() ? __('dashboard.cash_movements.deposit_short') : __('dashboard.cash_movements.petty_out_short') }}
                                — Rp {{ number_format($movement->amount, 0, ',', '.') }}
                            </p>
                            @if ($movement->note)
                                <p class="mt-0.5 text-xs text-gray-500">{{ $movement->note }}</p>
                            @endif
                            <p class="mt-0.5 text-xs text-gray-400">
                                {{ $movement->created_at->format('d/m/Y H:i') }} · {{ $movement->admin?->name ?? '—' }}
                            </p>
                        </div>
                    @empty
                        <p class="mt-3 text-sm text-gray-500">{{ __('dashboard.cash_movements.empty') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
