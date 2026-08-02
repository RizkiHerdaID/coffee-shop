<?php

namespace Database\Seeders;

use App\Models\Promo;
use Illuminate\Database\Seeder;

class PromoSeeder extends Seeder
{
    /**
     * Seed an always-live welcome promo so the public banner renders out of
     * the box. Idempotent via updateOrCreate on the title.
     */
    public function run(): void
    {
        Promo::query()->updateOrCreate(
            ['title' => 'Promo Kopi Pagi'],
            [
                'subtitle' => 'Gratis tambahan satu shots di atas harga Rp 25.000, sebelum pukul 10 pagi',
                'badge' => 'Promo',
                'cta_text' => 'Lihat Menu',
                'cta_url' => '/menu',
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(30),
                'active' => true,
                'sort_order' => 1,
            ],
        );
    }
}
