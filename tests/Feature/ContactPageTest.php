<?php

namespace Tests\Feature;

use Tests\TestCase;

class ContactPageTest extends TestCase
{
    public function test_contact_page_renders_successfully(): void
    {
        $response = $this->get('/contact');

        $response->assertOk();
    }

    public function test_contact_page_shows_shop_config_values(): void
    {
        $response = $this->get('/contact');

        $response->assertSee(config('shop.name'));
        $response->assertSee(config('shop.phone_display'));
        $response->assertSee(config('shop.email'));
        $response->assertSee('Jl. Contoh Raya No. 123');
    }

    public function test_contact_page_shows_opening_hours(): void
    {
        $response = $this->get('/contact');

        foreach (config('shop.hours') as $day => $hours) {
            $response->assertSee(__("site.days.$day"));
            $response->assertSee($hours);
        }
    }

    public function test_contact_page_has_whatsapp_cta(): void
    {
        $response = $this->get('/contact');

        $response->assertSee('https://wa.me/'.preg_replace('/\D/', '', config('shop.phone')));
        $response->assertSee('Hubungi via WhatsApp');
    }

    public function test_contact_page_shows_qris_badge(): void
    {
        $response = $this->get('/contact');

        $response->assertSee('Terima QRIS');
    }

    public function test_contact_page_has_keyless_maps_embed(): void
    {
        $response = $this->get('/contact');

        $response->assertSee('https://maps.google.com/maps?q='.urlencode(config('shop.address')).'&output=embed');
        $response->assertSee(__('contact.map_title'));
    }

    public function test_contact_page_has_directions_link(): void
    {
        $response = $this->get('/contact');

        $response->assertSee('https://www.google.com/maps/dir/?api=1&destination='.urlencode(config('shop.address')));
        $response->assertSee(__('contact.directions_button'));
    }
}
