<?php

namespace Tests\Unit;

use App\Http\Controllers\MarketPlace\AlibabaAnalyticsController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AlibabaListingDisplaySkuTest extends TestCase
{
    /** @param  array<string, array<string, bool>>  $sheet */
    private function display(string $stored, string $productId, string $hint, array $sheet): string
    {
        $ref = new ReflectionClass(AlibabaAnalyticsController::class);
        $controller = $ref->newInstanceWithoutConstructor();
        $m = $ref->getMethod('listingDisplaySku');

        return $m->invoke($controller, $stored, $productId, $hint, $sheet);
    }

    public function test_pack_size_from_the_title_is_added_when_no_other_listing_owns_that_sku(): void
    {
        $sheet = ['WF 12 140 PP 4OHM' => ['100' => true]];

        $this->assertSame('WF 12 140 PP 4OHM 2PCS', $this->display('WF 12 140 PP 4OHM', '100', 'Speaker 2 pcs set WF 12 140 PP 4OHM', $sheet));
    }

    public function test_suffixed_sku_that_belongs_to_another_listing_is_not_taken(): void
    {
        // "GS EL" (product …51501770) must not become "GS EL 6PCS": that is the stored SKU of product …43390518.
        $sheet = [
            'GS EL' => ['10000051501770' => true],
            'GS EL 6PCS' => ['10000043390518' => true],
        ];

        $this->assertSame('GS EL', $this->display('GS EL', '10000051501770', 'Guitar Strings 6 pcs set GS EL', $sheet));
        // The listing that really is "GS EL 6PCS" is untouched.
        $this->assertSame('GS EL 6PCS', $this->display('GS EL 6PCS', '10000043390518', 'Guitar Strings 6 pcs', $sheet));
    }

    public function test_suffixed_sku_owned_by_the_same_product_is_still_used(): void
    {
        $sheet = [
            'GS EL' => ['10000051501770' => true],
            'GS EL 6PCS' => ['10000051501770' => true],
        ];

        $this->assertSame('GS EL 6PCS', $this->display('GS EL', '10000051501770', 'Guitar Strings 6 pcs', $sheet));
    }

    public function test_sku_without_a_pack_hint_is_unchanged(): void
    {
        $this->assertSame('GS EL', $this->display('GS EL', '1', 'Electric Guitar Strings Extra Light Gauge 6 String Set', ['GS EL' => ['1' => true]]));
    }
}
