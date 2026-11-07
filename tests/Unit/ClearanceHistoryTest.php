<?php

namespace Tests\Unit;

use App\Support\ClearanceHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClearanceHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('inv_days_clearances', function ($t) {
            $t->id(); $t->string('sku_key')->unique(); $t->string('sku'); $t->string('value'); $t->timestamps();
        });
        Schema::create('inv_days_clearance_logs', function ($t) {
            $t->id(); $t->string('sku_key'); $t->string('sku'); $t->string('from_value')->nullable(); $t->string('to_value'); $t->timestamps();
        });
        Schema::create('shopify_skus', function ($t) {
            $t->id(); $t->string('sku'); $t->integer('inv')->default(0); $t->timestamps();
        });
        Schema::create('clearance_daily_counts', function ($t) {
            $t->id(); $t->date('record_date')->unique(); $t->unsignedInteger('sku_count')->default(0);
            $t->unsignedBigInteger('inv_units')->default(0); $t->string('source', 12)->default('live'); $t->timestamps();
        });
    }

    public function test_snapshot_counts_only_yes_skus_with_inventory(): void
    {
        DB::table('inv_days_clearances')->insert([
            ['sku_key' => 'a', 'sku' => 'A', 'value' => 'YES'],
            ['sku_key' => 'b', 'sku' => 'B', 'value' => 'YES'],
            ['sku_key' => 'c', 'sku' => 'C', 'value' => 'NO'],
            ['sku_key' => 'parent x', 'sku' => 'PARENT X', 'value' => 'YES'],
        ]);
        DB::table('shopify_skus')->insert([
            ['sku' => 'A', 'inv' => 10], ['sku' => 'b', 'inv' => 0], ['sku' => 'C', 'inv' => 5], ['sku' => 'PARENT X', 'inv' => 9],
        ]);

        $t = ClearanceHistory::snapshotToday();

        $this->assertSame(['count' => 1, 'inv' => 10], $t);
        $this->assertSame(1, DB::table('clearance_daily_counts')->count());
    }

    public function test_backfill_replays_the_change_log_and_keeps_live_rows(): void
    {
        $today = Carbon::now(ClearanceHistory::TZ)->startOfDay();
        $turnedOn = $today->copy()->subDays(3)->addHours(12)->utc()->toDateTimeString();
        DB::table('inv_days_clearances')->insert(['sku_key' => 'a', 'sku' => 'A', 'value' => 'YES', 'created_at' => $turnedOn]);
        DB::table('inv_days_clearance_logs')->insert(['sku_key' => 'a', 'sku' => 'A', 'from_value' => 'NO', 'to_value' => 'YES', 'created_at' => $turnedOn]);
        DB::table('shopify_skus')->insert(['sku' => 'A', 'inv' => 4]);
        DB::table('clearance_daily_counts')->insert(['record_date' => $today->copy()->subDay()->toDateString(), 'sku_count' => 99, 'inv_units' => 99, 'source' => 'live']);

        ClearanceHistory::backfill(5);

        $rows = DB::table('clearance_daily_counts')->pluck('sku_count', 'record_date')->all();
        $this->assertSame(0, (int) $rows[$today->copy()->subDays(4)->toDateString()]);
        $this->assertSame(1, (int) $rows[$today->copy()->subDays(3)->toDateString()]);
        $this->assertSame(1, (int) $rows[$today->copy()->subDays(2)->toDateString()]);
        $this->assertSame(99, (int) $rows[$today->copy()->subDay()->toDateString()]);
    }
}
