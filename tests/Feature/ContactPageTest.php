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
}
