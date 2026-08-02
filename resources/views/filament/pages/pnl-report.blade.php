<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="pnl-from">
                        {{ __('pnl.period.from') }}
                    </label>
                    <x-filament::input
                        id="pnl-from"
                        type="date"
                        wire:model.live="fromDate"
                        class="w-full"
                    />
                </div>
                <div class="flex-1">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="pnl-to">
                        {{ __('pnl.period.to') }}
                    </label>
                    <x-filament::input
                        id="pnl-to"
                        type="date"
                        wire:model.live="toDate"
                        class="w-full"
                    />
                </div>
            </div>

            @if ($this->error !== null)
                <div class="mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $this->error }}
                </div>
            @endif
        </div>

        @if ($this->error === null)
            @php($data = $this->getReportData())

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('pnl.summary.revenue') }}</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900">Rp {{ number_format($data['revenue'], 0, ',', '.') }}</p>
                        </div>
                        <div class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('pnl.summary.orders_count') }}</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $data['orders_count'] }}</p>
                        </div>
                        <div class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('pnl.summary.items_sold') }}</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $data['items_sold'] }}</p>
                        </div>
                        <div class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('pnl.summary.inventory_value') }}</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900">Rp {{ number_format($data['inventory_value'], 0, ',', '.') }}</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500">{{ __('pnl.statement.expenses_title') }}</h3>

                        @php($expenses = $data['expenses'])
                        @if (array_sum($expenses) === 0)
                            <p class="mt-3 text-sm text-gray-500">{{ __('pnl.period.empty') }}</p>
                        @else
                            <dl class="mt-3 space-y-2 text-sm">
                                @foreach ($expenses as $category => $amount)
                                    @if ($amount > 0)
                                        <div class="flex items-center justify-between">
                                            <dt class="text-gray-600">{{ __('expenses.categories.'.$category) }}</dt>
                                            <dd class="font-semibold text-gray-900">Rp {{ number_format($amount, 0, ',', '.') }}</dd>
                                        </div>
                                    @endif
                                @endforeach
                                <div class="flex items-center justify-between border-t border-gray-200 pt-2">
                                    <dt class="font-medium text-gray-700">{{ __('pnl.statement.expenses_title') }}</dt>
                                    <dd class="font-bold text-gray-900">Rp {{ number_format($data['expenses_total'], 0, ',', '.') }}</dd>
                                </div>
                            </dl>
                        @endif
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500">{{ __('pnl.title') }}</h3>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">{{ __('pnl.statement.revenue') }}</dt>
                            <dd class="font-semibold text-gray-900">Rp {{ number_format($data['revenue'], 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">{{ __('pnl.statement.cogs') }}</dt>
                            <dd class="font-semibold text-gray-900">−Rp {{ number_format($data['cogs'], 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-gray-200 pt-2">
                            <dt class="font-medium text-gray-700">{{ __('pnl.statement.gross_margin') }}</dt>
                            <dd class="font-bold text-gray-900">Rp {{ number_format($data['gross_margin'], 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">{{ __('pnl.statement.expenses_title') }}</dt>
                            <dd class="font-semibold text-gray-900">−Rp {{ number_format($data['expenses_total'], 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-gray-200 pt-2">
                            <dt class="font-medium text-gray-700">{{ __('pnl.statement.net_margin') }}</dt>
                            <dd class="text-base font-bold {{ $data['net_margin'] >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                Rp {{ number_format($data['net_margin'], 0, ',', '.') }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-gray-200 pt-2">
                            <dt class="text-gray-600">{{ __('pnl.margins.gross') }}</dt>
                            <dd class="font-semibold text-gray-900">{{ $data['gross_margin_percent'] }}%</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">{{ __('pnl.margins.net') }}</dt>
                            <dd class="font-semibold text-gray-900">{{ $data['net_margin_percent'] }}%</dd>
                        </div>
                    </dl>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
