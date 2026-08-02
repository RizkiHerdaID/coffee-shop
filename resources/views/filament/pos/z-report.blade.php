{{-- Standalone printable Z-report — MUST NOT extend layouts/app.blade.php. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('pos.zreport.title') }} — #{{ str_pad((string) $shift->id, 4, '0', STR_PAD_LEFT) }}</title>
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

        .report { padding: 1.25rem 0.5rem; }

        .report .center { text-align: center; }

        .report .shop-name {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .report .title {
            margin-top: 0.5rem;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .report .muted { color: #6b7280; font-size: 0.8rem; }

        .report hr {
            border: 0;
            border-top: 1px dashed #9ca3af;
            margin: 0.6rem 0;
        }

        .report .meta { font-size: 0.85rem; margin-bottom: 0.25rem; }
        .report .meta span { float: right; }

        .report .section {
            margin-top: 0.75rem;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.05em;
        }

        .report .line { display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.85rem; }
        .report .line .amount { white-space: nowrap; }

        .report .grand-total { font-size: 1rem; font-weight: 700; }

        .report .strong { font-weight: 700; }

        .report .ok { color: #15803d; }

        .report .short { color: #b91c1c; }

        .report .surplus { color: #b45309; }

        .report .thanks {
            margin-top: 0.75rem;
            text-align: center;
            font-weight: 600;
        }

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
        <button type="button" class="btn-print" onclick="window.print()">{{ __('pos.zreport.print') }}</button>
        <button type="button" class="btn-back" onclick="window.close()">{{ __('pos.zreport.back') }}</button>
    </div>

    <div class="sheet">
        <div class="report">
            <p class="center shop-name">{{ config('shop.name') }}</p>
            <p class="center muted">{{ str_replace("\n", ' ', config('shop.address')) }}</p>
            <p class="center muted">{{ config('shop.phone_display') }}</p>
            <p class="center title">{{ __('pos.zreport.shift') }}</p>

            <hr>

            <p class="meta">{{ __('pos.zreport.number') }}<span>#{{ str_pad((string) $shift->id, 4, '0', STR_PAD_LEFT) }}</span></p>
            <p class="meta">{{ __('pos.zreport.opened') }}<span>{{ $shift->opened_at->format('d/m/Y H:i') }}</span></p>
            <p class="meta">{{ __('pos.zreport.closed') }}<span>{{ $shift->closed_at?->format('d/m/Y H:i') ?? '—' }}</span></p>
            <p class="meta">{{ __('pos.zreport.closed_by') }}<span>{{ $shift->admin?->name ?? '—' }}</span></p>

            <hr>

            <p class="meta strong">{{ __('pos.zreport.orders') }}<span>{{ $shift->paidOrdersCount() }}</span></p>

            <div class="line grand-total">
                <span>{{ __('pos.zreport.sales') }}</span>
                <span>Rp {{ number_format($shift->salesTotal(), 0, ',', '.') }}</span>
            </div>

            <p class="section">{{ __('pos.zreport.payments') }}</p>

            @php($methods = $shift->paymentsByMethod())
            <div class="line">
                <span>{{ __('pos.zreport.cash') }}</span>
                <span>Rp {{ number_format($methods['cash'], 0, ',', '.') }}</span>
            </div>
            <div class="line">
                <span>{{ __('pos.zreport.qris') }}</span>
                <span>Rp {{ number_format($methods['qris'], 0, ',', '.') }}</span>
            </div>
            <div class="line">
                <span>{{ __('pos.zreport.ewallet') }}</span>
                <span>Rp {{ number_format($methods['ewallet'], 0, ',', '.') }}</span>
            </div>

            <p class="section">{{ __('pos.zreport.cash_count') }}</p>

            <div class="line">
                <span>{{ __('pos.zreport.opening_cash') }}</span>
                <span>Rp {{ number_format($shift->opening_cash, 0, ',', '.') }}</span>
            </div>
            <div class="line">
                <span>{{ __('pos.zreport.cash_payments') }}</span>
                <span>Rp {{ number_format($methods['cash'], 0, ',', '.') }}</span>
            </div>
            <div class="line">
                <span>{{ __('pos.zreport.cash_refunds') }}</span>
                <span>Rp {{ number_format($shift->cashRefunds(), 0, ',', '.') }}</span>
            </div>
            <div class="line">
                <span>{{ __('dashboard.cash_movements.deposits_total') }}</span>
                <span>Rp {{ number_format($shift->deposits(), 0, ',', '.') }}</span>
            </div>
            <div class="line">
                <span>{{ __('dashboard.cash_movements.petty_out_total') }}</span>
                <span>Rp {{ number_format($shift->pettyOut(), 0, ',', '.') }}</span>
            </div>

            <hr>

            <div class="line strong">
                <span>{{ __('pos.zreport.expected') }}</span>
                <span>Rp {{ number_format($shift->expectedCash(), 0, ',', '.') }}</span>
            </div>
            <div class="line">
                <span>{{ __('pos.zreport.counted') }}</span>
                <span>Rp {{ number_format($shift->closing_cash ?? 0, 0, ',', '.') }}</span>
            </div>
            <div class="line strong {{ $shift->discrepancy() === 0 ? 'ok' : ($shift->discrepancy() > 0 ? 'surplus' : 'short') }}">
                <span>{{ __('pos.zreport.discrepancy') }}</span>
                <span>
                    @if ($shift->discrepancy() === 0)
                        {{ __('pos.zreport.discrepancy_ok') }}
                    @else
                        {{ $shift->discrepancy() > 0 ? '+' : '−' }}Rp {{ number_format(abs($shift->discrepancy()), 0, ',', '.') }}
                    @endif
                </span>
            </div>

            <p class="muted">{{ __('pos.zreport.discrepancy_note') }}</p>
        </div>
    </div>

    @if (request()->boolean('autoprint'))
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
