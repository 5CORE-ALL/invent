<?php

namespace Tests\Unit;

use App\Http\Controllers\ProductMaster\InvDaysController;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AttachAgeDaysTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::put('inv_days_age_map_v1', ['kbb wh hd' => 95, 'fr 15 140 ms' => 12], 900);
    }

    public function test_stamps_age_days_on_child_rows_and_tree_children(): void
    {
        $rows = app(InvDaysController::class)->attachAgeDays([
            ['(Child) sku' => 'KBB WH HD', 'INV' => 5],
            ['(Child) sku' => 'PARENT FR', 'is_parent_summary' => true, '_children' => [
                ['(Child) sku' => 'FR  15 140 MS', 'INV' => 2],
                ['(Child) sku' => 'NO AGE SKU', 'INV' => 1],
            ]],
        ]);

        $this->assertSame(95, $rows[0]['age_days']);
        $this->assertArrayNotHasKey('age_days', $rows[1]);
        $this->assertSame(12, $rows[1]['_children'][0]['age_days']);
        $this->assertNull($rows[1]['_children'][1]['age_days']);
    }
}
