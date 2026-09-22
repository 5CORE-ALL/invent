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
}
