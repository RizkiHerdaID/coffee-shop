@if ($menu->isNotEmpty())
@php
    $products = $menu->map(function (App\Models\MenuItem $item) {
        $product = [
            '@type' => 'Product',
            'name' => $item->name,
            'url' => url(route('menu')),
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => 'IDR',
                // schema.org requires the raw numeric price — no thousands
                // separators ("25.000" would make Google drop the markup).
                'price' => $item->price,
                'availability' => $item->available ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => url(route('menu')),
            ],
        ];

        if ($item->photo) {
            $product['image'] = Illuminate\Support\Facades\Storage::disk('public')->url($item->photo);
        }

        return $product;
    })->values()->all();

    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'itemListElement' => $products,
    ];
@endphp
<script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endif
