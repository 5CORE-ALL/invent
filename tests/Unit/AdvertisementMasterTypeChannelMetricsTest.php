<?php

namespace Tests\Unit;

use App\Http\Controllers\AdvertisementMaster\AdvertisementMasterController;
use ReflectionMethod;
use Tests\TestCase;

class AdvertisementMasterTypeChannelMetricsTest extends TestCase
{
    public function test_type_rows_do_not_receive_t_sales_or_tcos(): void
    {
        $rows = [[
            'channel' => 'Amazon Total',
            'channel_key' => 'Amazon',
            'channel_group' => 'Amazon',
            'marketplace' => 'amazon',
            'is_sum_row' => true,
            'spend' => 2000,
            'sales' => 8000,
            '_children' => [[
                'channel' => 'Amazon · KW',
                'channel_key' => 'Amazon · KW',
                'channel_group' => 'Amazon',
                'marketplace' => 'amazon',
                'is_sub_row' => true,
                'spend' => 900,
                'sales' => 4000,
                't_sales' => 95403,
                'has_t_sales' => true,
                'tcos' => 3,
                'has_tcos' => true,
            ]],
        ]];

        $this->invoke('attachTSalesWalk', $rows, ['amazon' => 95403.0]);
        $this->invoke('clearTypeRowChannelMetrics', $rows);
        $this->invoke('attachTotalRowAcos', $rows);

        $parent = $rows[0];
        $type = $rows[0]['_children'][0];

        $this->assertTrue($parent['has_t_sales']);
        $this->assertSame(95403.0, $parent['t_sales']);
        $this->assertTrue($parent['has_tcos']);
        $this->assertGreaterThan(0, $parent['tcos']);

        $this->assertFalse($type['has_t_sales']);
        $this->assertSame(0.0, $type['t_sales']);
        $this->assertFalse($type['has_tcos']);
        $this->assertSame(0, $type['tcos']);
    }

    public function test_custom_type_rows_do_not_receive_channel_sales_metrics(): void
    {
        $rows = [[
            'channel' => 'Custom KW',
            'channel_key' => 'custom:1',
            'channel_group' => 'Amazon',
            'is_custom' => true,
            'is_sub_row' => true,
            'spend' => 10,
            't_sales' => 95403,
            'has_t_sales' => true,
            'tcos' => 9,
            'has_tcos' => true,
        ]];

        $this->invoke('attachTSalesWalk', $rows, ['amazon' => 95403.0]);
        $this->invoke('clearTypeRowChannelMetrics', $rows);

        $this->assertFalse($rows[0]['has_t_sales']);
        $this->assertFalse($rows[0]['has_tcos']);
    }

    public function test_leaf_total_gets_a_type_row_with_channel_name_and_ads_metrics(): void
    {
        $rows = [[
            'channel' => 'Aliexpress Total',
            'channel_key' => 'Aliexpress Total',
            'channel_group' => 'Aliexpress',
            'marketplace' => 'aliexpress',
            'is_sum_row' => true,
            'is_group_total' => true,
            'spend' => 25.5,
            'clicks' => 10,
            'sold' => 2,
            'sales' => 80.0,
            'cvr' => 20.0,
            'acos' => 32,
            'active' => 1,
        ]];

        $this->invoke('ensureDefaultTypeRows', $rows);

        $this->assertCount(1, $rows[0]['_children']);
        $type = $rows[0]['_children'][0];
        $this->assertSame('Aliexpress', $type['channel_group']);
        $this->assertSame('Aliexpress', $type['channel']);
        $this->assertTrue($type['is_sub_row']);
        $this->assertTrue($type['is_default_type']);
        $this->assertFalse($type['is_sum_row']);
        $this->assertSame(25.5, $type['spend']);
        $this->assertSame(80.0, $type['sales']);
        $this->assertFalse($type['has_t_sales']);
    }

    public function test_channel_with_type_rows_is_not_given_an_extra_default_type(): void
    {
        $rows = [[
            'channel' => 'Amazon Total',
            'channel_key' => 'Amazon',
            'channel_group' => 'Amazon',
            'is_sum_row' => true,
            '_children' => [[
                'channel' => 'Amazon · KW',
                'channel_key' => 'Amazon · KW',
                'channel_group' => 'Amazon',
                'is_sub_row' => true,
            ]],
        ]];

        $this->invoke('ensureDefaultTypeRows', $rows);

        $this->assertCount(1, $rows[0]['_children']);
        $this->assertSame('Amazon · KW', $rows[0]['_children'][0]['channel_key']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  mixed  ...$extra
     */
    private function invoke(string $method, array &$rows, ...$extra): void
    {
        $controller = new AdvertisementMasterController;
        $ref = new ReflectionMethod($controller, $method);
        $ref->setAccessible(true);
        $args = array_merge([&$rows], $extra);
        $ref->invokeArgs($controller, $args);
    }
}
