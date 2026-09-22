<?php

namespace Tests\Unit;

use App\Support\AmazonAdsTargetCounts;
use PHPUnit\Framework\TestCase;

class AmazonAdsTargetCountsTest extends TestCase
{
    public function test_tally_counts_keywords_and_targets_once_per_campaign(): void
    {
        $counts = ['352682222387785' => 0, 'other' => 0];
        $seen = [];

        AmazonAdsTargetCounts::tally([
            ['campaignId' => '352682222387785', 'keywordId' => '11', 'keywordText' => 'drum'],
            ['campaignId' => '352682222387785', 'keywordId' => '11', 'keywordText' => 'drum'],
            ['campaignId' => 352682222387785, 'keywordId' => '12'],
            ['campaignId' => '352682222387785', 'targetId' => '99'],
            ['campaignId' => 'missing', 'keywordId' => '1'],
        ], $counts, $seen);

        $this->assertSame(3, $counts['352682222387785']);
        $this->assertSame(0, $counts['other']);
    }

    public function test_normalize_ids_drops_blanks_and_duplicates(): void
    {
        $this->assertSame(
            ['10', '20'],
            AmazonAdsTargetCounts::normalizeIds([' 10 ', '', '20', '10', null])
        );
    }
}
