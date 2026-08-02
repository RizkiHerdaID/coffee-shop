<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_visible_testimonials(): void
    {
        Testimonial::factory()->create([
            'name' => 'Budi Santoso',
            'text' => 'Kopi robusta-nya bikin nagih, pelayanannya cepat dan ramah.',
            'visible' => true,
        ]);
        Testimonial::factory()->create([
            'name' => 'Siti Aminah',
            'text' => 'Suasana cafenya nyaman buat kerja sambil minum latte.',
            'visible' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Budi Santoso');
        $response->assertSee('Kopi robusta-nya bikin nagih, pelayanannya cepat dan ramah.');
        $response->assertSee('Siti Aminah');
        $response->assertSee('Suasana cafenya nyaman buat kerja sambil minum latte.');
    }

    public function test_home_page_hides_hidden_testimonials(): void
    {
        Testimonial::factory()->create([
            'name' => 'Terlihat',
            'text' => 'Testimoni yang tampil di halaman utama.',
            'visible' => true,
        ]);
        Testimonial::factory()->create([
            'name' => 'Tersembunyi',
            'text' => 'Testimoni ini harus disembunyikan dari pengunjung.',
            'visible' => false,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Terlihat');
        $response->assertDontSee('Tersembunyi');
        $response->assertDontSee('Testimoni ini harus disembunyikan dari pengunjung.');
    }

    public function test_no_aggregate_rating_when_fewer_than_three_visible_testimonials(): void
    {
        Testimonial::factory()->count(2)->create(['visible' => true]);

        $response = $this->get('/');

        $response->assertOk();
        $this->assertNull($this->aggregateRating($response->getContent()));
    }

    public function test_no_aggregate_rating_without_any_visible_testimonials(): void
    {
        Testimonial::factory()->count(3)->create(['visible' => false]);

        $response = $this->get('/');

        $response->assertOk();
        $this->assertNull($this->aggregateRating($response->getContent()));
    }

    public function test_aggregate_rating_present_with_three_or_more_visible_testimonials(): void
    {
        Testimonial::factory()->create(['name' => 'Rini', 'rating' => 5, 'visible' => true]);
        Testimonial::factory()->create(['name' => 'Joko', 'rating' => 4, 'visible' => true]);
        Testimonial::factory()->create(['name' => 'Dewi', 'rating' => 4, 'visible' => true]);

        $response = $this->get('/');

        $response->assertOk();
        $rating = $this->aggregateRating($response->getContent());

        $this->assertNotNull($rating);
        $this->assertSame('AggregateRating', $rating['@type'] ?? null);
        $this->assertSame(3, $rating['reviewCount'] ?? null);
        $this->assertEquals(4.333, round((float) ($rating['ratingValue'] ?? 0), 3));
    }

    public function test_aggregate_rating_average_and_count_use_visible_testimonials_only(): void
    {
        Testimonial::factory()->create(['name' => 'A', 'rating' => 5, 'visible' => true]);
        Testimonial::factory()->create(['name' => 'B', 'rating' => 4, 'visible' => true]);
        Testimonial::factory()->create(['name' => 'C', 'rating' => 4, 'visible' => true]);
        Testimonial::factory()->create(['name' => 'D', 'rating' => 5, 'visible' => true]);
        Testimonial::factory()->create(['name' => 'E', 'rating' => 1, 'visible' => false]);
        Testimonial::factory()->create(['name' => 'F', 'rating' => 1, 'visible' => false]);

        $response = $this->get('/');

        $response->assertOk();
        $rating = $this->aggregateRating($response->getContent());

        $this->assertNotNull($rating);
        $this->assertSame(4, $rating['reviewCount'] ?? null);
        $this->assertEquals(4.5, round((float) ($rating['ratingValue'] ?? 0), 3));
    }

    /**
     * Extract the AggregateRating node from the Cafe JSON-LD block, or null
     * when the home page does not render one.
     *
     * @return array<string, mixed>|null
     */
    private function aggregateRating(string $html): ?array
    {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

        foreach ($matches[1] as $raw) {
            $block = json_decode(trim($raw), true);

            if (($block['@type'] ?? null) === 'Cafe' && isset($block['aggregateRating'])) {
                return $block['aggregateRating'];
            }
        }

        return null;
    }
}
