<?php

namespace Tests\Feature;

use Tests\TestCase;

class BarcodeImagePublicPageTest extends TestCase
{
    public function test_barcode_image_page_is_public_and_read_only(): void
    {
        $response = $this->get(route('barcode.image'));

        $response->assertOk();
        $response->assertSee('Barcode Image', false);
        $response->assertSee('Search Parent', false);
        $response->assertSee('Search SKU', false);
        $response->assertSee('Search Barcode / UPC', false);
        $response->assertSee('View only', false);
        $response->assertDontSee('Autogenerate from UPC', false);
        $response->assertDontSee(route('masters.barcode.save', [], false), false);
    }
}
