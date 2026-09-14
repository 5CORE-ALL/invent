<?php

namespace Tests\Unit;

use App\Support\Marketplace\ChannelMasterInventoryGuard;
use PHPUnit\Framework\TestCase;

class ChannelMasterInventoryGuardTest extends TestCase
{
    public function test_sep11_style_dip_is_isolated(): void
    {
        $this->assertTrue(ChannelMasterInventoryGuard::isIsolatedDip(1784197, 971689, 1532552));
    }

    public function test_fourteen_percent_move_is_not_isolated(): void
    {
        $this->assertFalse(ChannelMasterInventoryGuard::isIsolatedDip(1784197, 1532552, 1700000));
    }

    public function test_chart_repairs_v_dip_by_carrying_previous_day(): void
    {
        $repaired = ChannelMasterInventoryGuard::repairChartPoints([
            ['date' => 'Sep 10', 'value' => 1784197],
            ['date' => 'Sep 11', 'value' => 971689],
            ['date' => 'Sep 12', 'value' => 1532552],
            ['date' => 'Sep 13', 'value' => 1780000],
        ]);

        $this->assertSame(1784197.0, (float) $repaired[0]['value']);
        $this->assertSame(1784197.0, (float) $repaired[1]['value']);
        $this->assertEqualsWithDelta(1532552.0, (float) $repaired[2]['value'], 0.01);
        $this->assertEqualsWithDelta(1780000.0, (float) $repaired[3]['value'], 0.01);
    }

    public function test_stabilize_summary_rejects_collapsed_inv(): void
    {
        $out = ChannelMasterInventoryGuard::stabilizeSummary(
            ['inventory_value_amazon' => 971689, 'inv_at_sp' => 2000000, 'inv_at_lp' => 900000],
            ['inventory_value_amazon' => 1784197, 'inv_at_sp' => 2100000, 'inv_at_lp' => 950000]
        );

        $this->assertSame(1784197.0, (float) $out['inventory_value_amazon']);
        $this->assertSame(2000000.0, (float) $out['inv_at_sp']);
    }

    public function test_backfill_writes_missing_sp_from_inventory_ratio(): void
    {
        $row = new \App\Models\ChannelMasterSummary();
        $row->summary_data = ['inventory_value_amazon' => 1800000];

        ChannelMasterInventoryGuard::backfillMissingOnRows([$row], 2.2 / 1.8, 0.5, false);

        $sd = \App\Models\ChannelMasterSummary::decodeSummaryData($row->summary_data);
        $this->assertEqualsWithDelta(2200000.0, (float) $sd['inv_at_sp'], 0.01);
        $this->assertEqualsWithDelta(900000.0, (float) $sd['inv_at_lp'], 0.01);
    }
}
