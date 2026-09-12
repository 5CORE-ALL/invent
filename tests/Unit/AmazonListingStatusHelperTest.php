<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\AmazonListingStatusHelper;
use App\Services\MarketplaceManager\MarketplacePortalStatusTabs;
use PHPUnit\Framework\TestCase;

class AmazonListingStatusHelperTest extends TestCase
{
    public function test_out_of_stock_and_discoverable_are_live_not_inactive(): void
    {
        $this->assertSame('active', AmazonListingStatusHelper::normalizePortalStatus('OUT_OF_STOCK'));
        $this->assertSame('active', AmazonListingStatusHelper::normalizePortalStatus('out of stock'));
        $this->assertSame('active', AmazonListingStatusHelper::normalizePortalStatus('DISCOVERABLE'));
        $this->assertSame('active', AmazonListingStatusHelper::normalizePortalStatus('Active'));
        $this->assertSame('inactive', AmazonListingStatusHelper::normalizePortalStatus('INACTIVE'));
        $this->assertSame('inactive', AmazonListingStatusHelper::normalizePortalStatus('SUPPRESSED'));
        $this->assertSame('inactive', AmazonListingStatusHelper::normalizePortalStatus('Closed'));
    }

    public function test_api_sheet_mapping_keeps_sold_out_listings_active(): void
    {
        $this->assertSame('ACTIVE', AmazonListingStatusHelper::mapAmazonApiStatusToSheet('OUT_OF_STOCK'));
        $this->assertSame('ACTIVE', AmazonListingStatusHelper::mapAmazonApiStatusToSheet('DISCOVERABLE'));
        $this->assertSame('ACTIVE', AmazonListingStatusHelper::mapAmazonApiStatusToSheet('BUYABLE'));
        $this->assertSame('INACTIVE', AmazonListingStatusHelper::mapAmazonApiStatusToSheet('INACTIVE'));
        $this->assertSame('INACTIVE', AmazonListingStatusHelper::mapAmazonApiStatusToSheet('SUPPRESSED'));
        $this->assertSame('INCOMPLETE', AmazonListingStatusHelper::mapAmazonApiStatusToSheet('INCOMPLETE'));
    }

    public function test_portal_tabs_treat_out_of_stock_as_active(): void
    {
        $this->assertSame('active', MarketplacePortalStatusTabs::bucket('out_of_stock'));
        $this->assertSame('active', MarketplacePortalStatusTabs::bucket('discoverable'));
        $this->assertSame('inactive', MarketplacePortalStatusTabs::bucket('inactive'));
    }

    public function test_listings_raw_prefers_report_quantity_over_pushed_column(): void
    {
        $row = (object) [
            'quantity' => 66,
            'raw_data' => [
                'quantity' => '0',
                'status' => 'Active',
                'seller-sku' => '66SF',
            ],
        ];

        $meta = AmazonListingStatusHelper::metaFromListingsRawRow($row);

        $this->assertSame(0, $meta['quantity']);
        $this->assertSame('active', $meta['state']);
    }

    public function test_listings_raw_falls_back_to_column_when_report_qty_missing(): void
    {
        $row = (object) [
            'quantity' => 12,
            'raw_data' => json_encode(['status' => 'Inactive', 'seller-sku' => 'ABC']),
        ];

        $meta = AmazonListingStatusHelper::metaFromListingsRawRow($row);

        $this->assertSame(12, $meta['quantity']);
        $this->assertSame('inactive', $meta['state']);
    }

    public function test_report_row_without_status_is_still_live(): void
    {
        $row = (object) [
            'quantity' => 8,
            'raw_data' => ['seller-sku' => 'A-54', 'quantity' => '8'],
        ];

        $this->assertTrue(AmazonListingStatusHelper::reportRowIsLive($row));
    }

    public function test_report_row_inactive_status_is_not_live(): void
    {
        $row = (object) [
            'quantity' => 0,
            'raw_data' => ['status' => 'Inactive', 'seller-sku' => 'A-54'],
        ];

        $this->assertFalse(AmazonListingStatusHelper::reportRowIsLive($row));
    }

    public function test_closed_fba_does_not_override_active_fbm_same_sku(): void
    {
        $sets = AmazonListingStatusHelper::classifyReportSkus([
            ['sku' => '1/4M-3/8M Camera Screw 5Pcs', 'live' => false],
            ['sku' => '1/4M-3/8M Camera Screw 5Pcs', 'live' => true],
        ]);

        $this->assertSame([], $sets['inactive']);
        $this->assertTrue(isset($sets['active'][strtoupper('1/4M-3/8M Camera Screw 5Pcs')]));
    }

    public function test_closed_only_sku_stays_inactive(): void
    {
        $sets = AmazonListingStatusHelper::classifyReportSkus([
            ['sku' => 'ONLY-CLOSED', 'live' => false],
        ]);

        $this->assertSame(['ONLY-CLOSED'], $sets['inactive']);
    }

    public function test_closed_fba_leftover_alone_is_not_inactive_listing(): void
    {
        $sets = AmazonListingStatusHelper::classifyReportSkus([
            ['sku' => '1/4M-3/8M Camera Screw 5Pcs', 'live' => false, 'ignore' => true, 'fba' => true],
        ]);

        $this->assertSame([], $sets['inactive']);
    }

    public function test_sku_with_fba_row_is_never_inactive_listing(): void
    {
        $sets = AmazonListingStatusHelper::classifyReportSkus([
            ['sku' => '1/4M-3/8M Camera Screw 5Pcs', 'live' => false, 'fba' => true, 'ignore' => true],
            ['sku' => '1/4M-3/8M Camera Screw 5Pcs', 'live' => false, 'fba' => false],
        ]);

        $this->assertSame([], $sets['inactive']);
    }

    public function test_fulfillment_channel_with_spaces_is_fba(): void
    {
        $row = (object) [
            'seller_sku' => '1/4M-3/8M Camera Screw 5Pcs',
            'quantity' => 0,
            'raw_data' => [
                'status' => 'Inactive',
                'Fulfillment Channel' => 'AMAZON',
            ],
        ];

        $this->assertTrue(AmazonListingStatusHelper::reportRowIsFba($row));
        $this->assertTrue(AmazonListingStatusHelper::reportRowIsClosedFba($row));
    }

    public function test_closed_fba_row_is_detected_from_report(): void
    {
        $row = (object) [
            'quantity' => 0,
            'raw_data' => [
                'status' => 'Inactive',
                'fulfillment-channel' => 'AMAZON',
                'seller-sku' => '1/4M-3/8M Camera Screw 5Pcs',
            ],
        ];

        $this->assertTrue(AmazonListingStatusHelper::reportRowIsClosedFba($row));
        $this->assertFalse(AmazonListingStatusHelper::reportRowIsLive($row));
    }

    public function test_fbm_row_with_qty_is_live(): void
    {
        $row = (object) [
            'quantity' => 145,
            'raw_data' => [
                'status' => 'Active',
                'fulfillment-channel' => 'DEFAULT',
                'quantity' => '145',
                'seller-sku' => '1/4M-3/8M Camera Screw 5Pcs',
            ],
        ];

        $this->assertFalse(AmazonListingStatusHelper::reportRowIsClosedFba($row));
        $this->assertTrue(AmazonListingStatusHelper::reportRowIsLive($row));
    }

    public function test_seller_central_buyable_with_qty_is_live(): void
    {
        $state = AmazonListingStatusHelper::sellerCentralListingState([
            'summaries' => [['status' => ['BUYABLE', 'DISCOVERABLE']]],
            'fulfillmentAvailability' => [['quantity' => 145]],
        ], 200);

        $this->assertSame('live', $state);
    }

    public function test_seller_central_inactive_zero_qty_stays_inactive(): void
    {
        $state = AmazonListingStatusHelper::sellerCentralListingState([
            'summaries' => [['status' => ['INACTIVE']]],
            'fulfillmentAvailability' => [['quantity' => 0]],
        ], 200);

        $this->assertSame('inactive', $state);
    }

    public function test_seller_central_missing_or_unknown_is_not_inactive_listing(): void
    {
        $this->assertSame('missing', AmazonListingStatusHelper::sellerCentralListingState(null, 404));
        $this->assertSame('unknown', AmazonListingStatusHelper::sellerCentralListingState(null, 500));

        $keep = AmazonListingStatusHelper::keepSellerCentralInactiveSkus(
            ['1/4M-3/8M Camera Screw 5Pcs', 'A-54', 'CLOSED-ONLY'],
            [
                '1/4M-3/8M Camera Screw 5Pcs' => 'live',
                'A-54' => 'unknown',
                'CLOSED-ONLY' => 'inactive',
            ]
        );

        $this->assertSame(['CLOSED-ONLY'], $keep);
    }
}
