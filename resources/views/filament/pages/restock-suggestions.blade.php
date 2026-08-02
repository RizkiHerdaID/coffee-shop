<x-filament-panels::page>
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('purchase-orders.restock.heading') }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('purchase-orders.restock.description') }}</p>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
