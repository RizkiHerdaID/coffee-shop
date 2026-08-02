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
                'price' => number_format($item->price, 0, ',', '.'),
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
<script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}</script>
@endif
