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
