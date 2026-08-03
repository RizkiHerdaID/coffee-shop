<?php

namespace Tests\Feature;

use App\Models\Promo;
use Database\Seeders\PromoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_promo_seeder_creates_the_welcome_promo_on_first_run(): void
    {
        $this->seed(PromoSeeder::class);

        $this->assertDatabaseCount('promos', 1);
        $this->assertDatabaseHas('promos', [
            'title' => 'Promo Kopi Pagi',
            'active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_reseeding_does_not_duplicate_the_promo(): void
    {
        $this->seed(PromoSeeder::class);
        $this->seed(PromoSeeder::class);
        $this->seed(PromoSeeder::class);

        $this->assertDatabaseCount('promos', 1);
    }

    public function test_reseeding_leaves_an_extended_ends_at_untouched(): void
    {
        $this->seed(PromoSeeder::class);

        $extended = now()->addDays(60);
        Promo::where('title', 'Promo Kopi Pagi')->update(['ends_at' => $extended]);

        $this->seed(PromoSeeder::class);

        $promo = Promo::where('title', 'Promo Kopi Pagi')->firstOrFail();
        $this->assertSame($extended->getTimestamp(), $promo->ends_at->getTimestamp());
        $this->assertDatabaseCount('promos', 1);
    }
}
