<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $stats['period'] === 'weekly' ? __('summary.subject.weekly', ['start' => $stats['start']->translatedFormat('d F Y'), 'end' => $stats['end']->translatedFormat('d F Y')]) : __('summary.subject.daily', ['date' => $stats['end']->translatedFormat('d F Y')]) }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; background-color: #f3f4f6; margin: 0; padding: 24px; }
        .container { max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; padding: 24px; }
        h2 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th { text-align: left; padding: 8px; border-bottom: 2px solid #d1d5db; font-size: 12px; text-transform: uppercase; color: #6b7280; }
        td { padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        td.price, td.qty { text-align: right; white-space: nowrap; }
        .stats td { border-bottom: none; padding: 6px 8px; }
        .stats td.price { font-weight: bold; }
        .empty { color: #6b7280; }
        .footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <h2>{{ __('summary.greeting') }}</h2>

        <p>{{ __('summary.intro', [
            'start' => $stats['start']->translatedFormat('d F Y'),
            'end' => $stats['end']->translatedFormat('d F Y'),
        ]) }}</p>

        <table class="stats">
            <tr>
                <td>{{ __('summary.stats.revenue') }}</td>
                <td class="price">{{ 'Rp '.number_format($stats['revenue'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>{{ __('summary.stats.orders_count') }}</td>
                <td class="price">{{ number_format($stats['orders_count'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>{{ __('summary.stats.avg_order') }}</td>
                <td class="price">{{ 'Rp '.number_format($stats['avg_order'], 0, ',', '.') }}</td>
            </tr>
        </table>

        <h3>{{ __('summary.stats.top_items') }}</h3>

        @if (empty($stats['top_items']))
            <p class="empty">{{ __('summary.stats.empty') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('summary.table.item') }}</th>
                        <th class="price">{{ __('summary.table.qty') }}</th>
                        <th class="price">{{ __('summary.table.revenue') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stats['top_items'] as $item)
                        <tr>
                            <td>{{ $item['name'] }}</td>
                            <td class="qty">{{ number_format($item['qty'], 0, ',', '.') }}</td>
                            <td class="price">{{ 'Rp '.number_format($item['revenue'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p class="footer">{!! nl2br(e(__('summary.footer', [
            'shop' => config('shop.name'),
            'address' => config('shop.address'),
        ]))) !!}</p>
    </div>
</body>
</html>
