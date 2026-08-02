{{-- Standalone printable receipt — MUST NOT extend layouts/app.blade.php (page-speed branch owns it). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('pos.receipt.title') }} — {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f3f4f6;
            font-family: ui-monospace, 'Cascadia Mono', 'Consolas', monospace;
            color: #111827;
        }

        .toolbar {
            position: sticky;
            top: 0;
            display: flex;
            gap: 0.5rem;
            align-items: center;
            padding: 0.75rem 1rem;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }

        .toolbar button {
            border: 0;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-print { background: #d97706; color: #fff; }
        .btn-print:hover { background: #b45309; }
        .btn-back { background: #fff; color: #374151; border: 1px solid #d1d5db !important; }

        .sheet {
            width: 80mm;
            margin: 1.5rem auto;
            background: #fff;
            box-shadow: 0 1px 3px rgb(0 0 0 / 0.1);
        }

        .receipt { padding: 1.25rem 0.5rem; }

        .receipt .center { text-align: center; }

        .receipt .shop-name {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .receipt .muted { color: #6b7280; font-size: 0.8rem; }

        .receipt hr {
            border: 0;
            border-top: 1px dashed #9ca3af;
            margin: 0.6rem 0;
        }

        .receipt .meta { font-size: 0.85rem; margin-bottom: 0.25rem; }
        .receipt .meta span { float: right; }

        .receipt .items { font-size: 0.85rem; }

        .receipt .line { display: flex; justify-content: space-between; gap: 0.5rem; }
        .receipt .line .name { overflow-wrap: anywhere; }
        .receipt .line .amount { white-space: nowrap; }

        .receipt .totals { font-size: 0.9rem; }

        .receipt .grand-total {
            font-size: 1rem;
            font-weight: 700;
        }

        .receipt .thanks {
            margin-top: 0.75rem;
            text-align: center;
            font-weight: 600;
        }

        .receipt .payment-methods { font-size: 0.85rem; margin-top: 0.5rem; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet { margin: 0; width: 100%; box-shadow: none; }
            @page { margin: 6mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" class="btn-print" onclick="window.print()">{{ __('pos.receipt.print') }}</button>
        <button type="button" class="btn-back" onclick="window.close()">{{ __('pos.receipt.close') }}</button>
    </div>

    <div class="sheet">
        <div class="receipt">
            <p class="center shop-name">{{ config('shop.name') }}</p>
            <p class="center muted">{{ str_replace("\n", ' ', config('shop.address')) }}</p>
            <p class="center muted">{{ config('shop.phone_display') }}</p>

            <hr>

            <p class="meta">{{ __('pos.receipt.order') }}<span>{{ $order->order_number }}</span></p>
            <p class="meta">{{ __('pos.receipt.date') }}<span>{{ $order->created_at->format('d/m/Y H:i') }}</span></p>
            <p class="meta">{{ __('pos.receipt.status') }}<span>{{ $order->status->getLabel() }}</span></p>

            <hr>

            <div class="items">
                @foreach ($order->items as $item)
                    <div class="line">
                        <span class="name">{{ $item->name }} × {{ $item->qty }}</span>
                        <span class="amount">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
                    </div>
                    <div class="line muted">
                        <span>Rp {{ number_format($item->price, 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>

            <hr>

            <div class="totals">
                <div class="line grand-total">
                    <span>{{ __('pos.receipt.total') }}</span>
                    <span>Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                </div>
            </div>

            <div class="payment-methods">
                @foreach ($order->payments as $payment)
                    <div class="line">
                        <span>{{ $payment->method?->getLabel() ?? __('pos.payment.method.cash') }}</span>
                        <span>Rp {{ number_format($payment->amount, 0, ',', '.') }}</span>
                    </div>
                    @if (filled($payment->reference))
                        <div class="line muted">
                            <span>{{ __('pos.payment.reference') }}: {{ $payment->reference }}</span>
                        </div>
                    @endif
                @endforeach
            </div>

            <hr>

            <p class="thanks">{{ __('pos.receipt.thank_you') }}</p>
            <p class="center muted">{{ __('pos.receipt.visit_again') }}</p>
        </div>
    </div>

    @if (request()->boolean('autoprint'))
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
