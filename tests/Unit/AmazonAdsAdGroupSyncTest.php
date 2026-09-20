<?php

namespace Tests\Unit;

use App\Models\AmazonAdsAdGroup;
use App\Support\AmazonAdsAdGroupSync;
use PHPUnit\Framework\TestCase;

class AmazonAdsAdGroupSyncTest extends TestCase
{
    public function test_rows_from_amazon_map_sp_ad_group_fields(): void
    {
        $rows = AmazonAdsAdGroupSync::rowsFromAmazon('prof-1', 'SP', [
            [
                'adGroupId' => '266666208021',
                'campaignId' => '166162002',
                'name' => 'PARENT GS EL POWER KW AG',
                'state' => 'paused',
                'defaultBid' => 0.75,
            ],
        ], [
            '166162002' => 'PARENT GS EL POWER KW',
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('prof-1', $rows[0]['profile_id']);
        $this->assertSame(AmazonAdsAdGroup::AD_TYPE_SP, $rows[0]['ad_type']);
        $this->assertSame('266666208021', $rows[0]['ad_group_id']);
        $this->assertSame('166162002', $rows[0]['campaign_id']);
        $this->assertSame('PARENT GS EL POWER KW', $rows[0]['campaignName']);
        $this->assertSame('PARENT GS EL POWER KW AG', $rows[0]['adGroupName']);
        $this->assertSame('PAUSED', $rows[0]['state']);
        $this->assertSame(0.75, $rows[0]['defaultBid']);
    }

    public function test_rows_from_amazon_skip_missing_ad_group_id(): void
    {
        $rows = AmazonAdsAdGroupSync::rowsFromAmazon('p', AmazonAdsAdGroup::AD_TYPE_SB, [
            ['name' => 'no id', 'campaignId' => '1'],
            ['adGroupId' => '99', 'name' => 'kept'],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('99', $rows[0]['ad_group_id']);
        $this->assertSame(AmazonAdsAdGroup::AD_TYPE_SB, $rows[0]['ad_type']);
    }

    public function test_default_bid_reads_nested_amount(): void
    {
        $this->assertSame(1.25, AmazonAdsAdGroupSync::defaultBid([
            'bid' => ['amount' => '1.25'],
        ]));
        $this->assertNull(AmazonAdsAdGroupSync::defaultBid(['name' => 'none']));
    }

    public function test_names_from_campaigns(): void
    {
        $names = AmazonAdsAdGroupSync::namesFromCampaigns([
            ['campaignId' => '100', 'name' => 'Alpha'],
            ['campaign_id' => '200', 'campaignName' => 'Beta'],
            ['campaignId' => '', 'name' => 'skip'],
        ]);

        $this->assertSame(['100' => 'Alpha', '200' => 'Beta'], $names);
    }

    public function test_default_bid_reads_flat_number(): void
    {
        $this->assertSame(0.4, AmazonAdsAdGroupSync::defaultBid(['defaultBid' => 0.4]));
    }
}
