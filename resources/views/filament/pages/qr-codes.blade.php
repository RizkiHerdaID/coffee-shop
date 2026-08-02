<x-filament-panels::page>
    <div class="mb-6 flex justify-end print:hidden">
        <x-filament::button icon="heroicon-m-printer" onclick="window.print()">
            {{ __('qr.print') }}
        </x-filament::button>
    </div>

    <p class="mb-6 text-sm text-gray-500 print:hidden">{{ __('qr.admin_intro') }}</p>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($qrCodes as $table => $qrCode)
        <div class="rounded-2xl border border-gray-300 bg-white p-6 text-center">
            <p class="mb-4 text-lg font-bold text-gray-900">{{ __('qr.table_name', ['number' => $table]) }}</p>
            <img src="{{ $qrCode }}" alt="{{ __('qr.table_name', ['number' => $table]) }}" class="mx-auto h-56 w-56">
        </div>
        @endforeach
    </div>

    <style>
        @media print {
            .fi-sidebar,
            .fi-topbar,
            .fi-skip-link {
                display: none !important;
            }

            .fi-main-ctn {
                display: block !important;
                padding: 0 !important;
            }

            .fi-main {
                padding: 0 !important;
            }
        }
    </style>
</x-filament-panels::page>
