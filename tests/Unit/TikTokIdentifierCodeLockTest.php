<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\TikTok2InventorySyncService;
use App\Services\TikTokShopService;
use PHPUnit\Framework\TestCase;

class TikTokIdentifierCodeLockTest extends TestCase
{
    public function test_partial_edit_keeps_the_submitted_gtin(): void
    {
        $this->assertSame(
            ['code' => '012345678905', 'type' => 'GTIN'],
            TikTokShopService::identifierCodeFromSkuNode([
                'identifier_code' => [
                    'code' => '012345678905',
                    'type' => 'GTIN',
                ],
            ])
        );
    }

    public function test_upc_string_nodes_are_kept(): void
    {
        $this->assertSame(
            ['code' => '012345678905', 'type' => 'UPC'],
            TikTokShopService::identifierCodeFromSkuNode([
                'upc' => '012345678905',
                'identifier_code_type' => 'UPC',
            ])
        );
    }

    public function test_external_list_gtin_is_kept_with_type(): void
    {
        $this->assertSame(
            ['code' => '012345678905', 'type' => 'GTIN'],
            TikTokShopService::identifierCodeFromSkuNode([
                'external_list' => [
                    ['type' => 'GTIN', 'value' => '012345678905'],
                ],
            ])
        );
    }

    public function test_code_without_type_defaults_to_gtin_or_upc(): void
    {
        $this->assertSame(
            ['code' => '012345678905', 'type' => 'UPC'],
            TikTokShopService::identifierCodeFromSkuNode([
                'identifier_code' => ['code' => '012345678905'],
            ])
        );
        $this->assertSame(
            ['code' => '0123456789051', 'type' => 'GTIN'],
            TikTokShopService::identifierCodeFromSkuNode([
                'gtin' => '0123456789051',
            ])
        );
    }

    public function test_tiktok2_aliases_cover_hyphen_space_and_compact(): void
    {
        $aliases = TikTok2InventorySyncService::skuAliasesForPush('C7 MI 7-6B');

        $this->assertContains('C7 MI 7-6B', $aliases);
        $this->assertContains('C7-MI-7-6B', $aliases);
        $this->assertContains('C7MI76B', $aliases);
    }

    public function test_identifier_lock_is_not_a_product_status_error(): void
    {
        $message = 'Operation Not Allowed. Cannot change identifier code: it has already been submitted. Please keep the submitted identifier code.';

        $this->assertTrue(TikTokShopService::isIdentifierCodeLockedError($message));
        $this->assertFalse(TikTokShopService::isIdentifierCodeLockedError('12052901 Operation not allowed for this product status'));
    }

    public function test_zero_package_dimensions_are_replaced_with_positive_defaults(): void
    {
        $fields = TikTokShopService::positivePackageFields([
            'package_dimensions' => [
                'length' => '0',
                'width' => 0,
                'height' => '',
                'unit' => 'INCH',
            ],
            'package_weight' => ['value' => '0', 'unit' => 'POUND'],
        ]);

        $this->assertSame('10.00', $fields['package_dimensions']['length']);
        $this->assertSame('8.00', $fields['package_dimensions']['width']);
        $this->assertSame('6.00', $fields['package_dimensions']['height']);
        $this->assertSame('1.00', $fields['package_weight']['value']);
        $this->assertTrue(TikTokShopService::isPackageDimensionsError(
            "Invalid Parameter. Parameter `package_dimensions` is invalid because all package dimensions must be positive numeric values."
        ));
    }

    public function test_existing_positive_package_size_is_kept(): void
    {
        $fields = TikTokShopService::positivePackageFields([
            'package_dimensions' => [
                'length' => '12.5',
                'width' => '4',
                'height' => '3.25',
                'unit' => 'INCH',
            ],
            'package_weight' => ['value' => '2.2', 'unit' => 'POUND'],
        ]);

        $this->assertSame('12.50', $fields['package_dimensions']['length']);
        $this->assertSame('4.00', $fields['package_dimensions']['width']);
        $this->assertSame('3.25', $fields['package_dimensions']['height']);
        $this->assertSame('2.20', $fields['package_weight']['value']);
    }
}
