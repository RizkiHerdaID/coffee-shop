<?php

namespace Database\Seeders;

use App\Models\Promo;
use Illuminate\Database\Seeder;

class PromoSeeder extends Seeder
{
    /**
     * Seed an always-live welcome promo so the public banner renders out of
     * the box. Idempotent via firstOrCreate on the unique title. Re-seeding
     * must never touch the dates on an existing promo (an owner may have
     * extended ends_at); only the static copy fields are refreshed.
     */
    public function run(): void
    {
        $promo = Promo::query()->firstOrCreate(
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

        if ($promo->wasRecentlyCreated) {
            return;
        }

        $promo->update([
            'subtitle' => 'Gratis tambahan satu shots di atas harga Rp 25.000, sebelum pukul 10 pagi',
            'badge' => 'Promo',
            'cta_text' => 'Lihat Menu',
            'cta_url' => '/menu',
            'active' => true,
            'sort_order' => 1,
        ]);
    }
}
