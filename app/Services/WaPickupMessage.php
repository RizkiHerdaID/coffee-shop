<?php

namespace App\Services;

final class WaPickupMessage
{
    /**
     * Build the localized WhatsApp pickup-order message.
     *
     * @param  array<int, array{name: string, quantity: int, price: int}>  $items
     */
    public static function build(array $items, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $lines = [];
        $grandTotal = 0;

        foreach ($items as $item) {
            $lineTotal = (int) $item['price'] * (int) $item['quantity'];
            $grandTotal += $lineTotal;

            $lines[] = __('menu.pickup.item_line', [
                'name' => (string) $item['name'],
                'qty' => (int) $item['quantity'],
                'total' => self::formatPrice($lineTotal),
            ], $locale);
        }

        return implode("\n", [
            __('site.wa_message', [], $locale),
            __('menu.pickup.message_title', ['shop' => config('shop.name')], $locale),
            '',
            implode("\n", $lines),
            '',
            __('menu.pickup.message_total', ['total' => self::formatPrice($grandTotal)], $locale),
            __('menu.pickup.message_pickup', [], $locale),
        ]);
    }

    public static function formatPrice(int $value): string
    {
        return 'Rp '.number_format($value, 0, ',', '.');
    }
}
