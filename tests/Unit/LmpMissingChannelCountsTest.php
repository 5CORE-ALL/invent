<?php

namespace Tests\Unit;

use App\Support\Marketplace\LmpMissingChannelCounts;
use App\Support\Marketplace\LmpMissingPageCounts;
use PHPUnit\Framework\TestCase;

class LmpMissingChannelCountsTest extends TestCase
{
    public function test_sum_counted_skips_nr_channels(): void
    {
        $total = LmpMissingChannelCounts::sumCounted([
            ['key' => 'amazon', 'lmp_missing' => 12, 'nr' => false],
            ['key' => 'ebay', 'lmp_missing' => 4, 'nr' => true],
            ['key' => 'vinted', 'lmp_missing' => 3],
        ]);

        $this->assertSame(15, $total);
    }

    public function test_missing_in_counts_in_stock_skus_without_lmp(): void
    {
        $total = LmpMissingPageCounts::missingIn(
            ['AAA' => true, 'BBB' => true, 'CCC OPEN BOX' => true],
            ['BBB' => true, 'CCC' => true],
            true
        );

        $this->assertSame(1, $total);
    }

    public function test_sum_counted_is_zero_when_every_channel_is_nr(): void
    {
        $total = LmpMissingChannelCounts::sumCounted([
            ['lmp_missing' => 9, 'nr' => true],
            ['lmp_missing' => 2, 'nr' => 1],
        ]);

        $this->assertSame(0, $total);
    }
}
