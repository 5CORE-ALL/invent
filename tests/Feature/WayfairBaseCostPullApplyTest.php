<?php

namespace Tests\Feature;

use App\Services\Wayfair\WayfairBaseCostPullService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Runs the real apply() against a throwaway in-memory SQLite database.
 * Start with: DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit tests/Feature/WayfairBaseCostPullApplyTest.php
 * It refuses to run on any other connection so it can never touch a real database.
 */
class WayfairBaseCostPullApplyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDefaultConnection() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Needs DB_CONNECTION=sqlite DB_DATABASE=:memory: (never runs on a real database).');
        }

        Schema::create('wayfair_pricing_prices', function ($t) {
            $t->unsignedBigInteger('id')->primary();
            $t->string('sku')->nullable();
            $t->decimal('price', 10, 2)->default(0);
            $t->integer('wayfair_stock')->default(0);
            $t->string('price_po_number', 64)->nullable();
            $t->dateTime('price_po_date')->nullable();
            $t->dateTime('price_synced_at')->nullable();
            $t->timestamps();
        });
        Schema::create('wayfair_price_uploads', function ($t) {
            $t->id();
            $t->string('status', 32)->default('GENERATED');
            $t->timestamps();
        });

        config(['wayfair_upload.base_cost_pull.max_change_percent' => 50]);
    }

    private function seedPrices(array $rows): void
    {
        $id = 1;
        foreach ($rows as $sku => $price) {
            DB::table('wayfair_pricing_prices')->insert(['id' => $id++, 'sku' => $sku, 'price' => $price]);
        }
    }

    private function export(array $costs): array
    {
        $out = [];
        foreach ($costs as $sku => $cost) {
            $out[strtoupper(trim(str_replace("\xC2\xA0", ' ', $sku)))] = ['sku' => $sku, 'status' => 'Live', 'cost' => $cost];
        }

        return $out;
    }

    private function price(string $sku): float
    {
        return (float) DB::table('wayfair_pricing_prices')->where('sku', $sku)->value('price');
    }

    public function test_changed_price_is_saved_and_unchanged_is_stamped(): void
    {
        $this->seedPrices(['A' => 10.00, 'B' => 5.00, 'C' => 7.00, 'D' => 9.00]);
        $result = (new WayfairBaseCostPullService())->apply($this->export(['A' => 10.5, 'B' => 5.00, 'C' => 7.00, 'D' => 9.00, 'NOT HERE' => 3.00]));

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(3, $result['unchanged']);
        $this->assertSame(1, $result['no_row']);
        $this->assertEqualsWithDelta(10.50, $this->price('A'), 0.001);
        $this->assertEqualsWithDelta(5.00, $this->price('B'), 0.001);
        $this->assertSame(WayfairBaseCostPullService::SOURCE_MARK, DB::table('wayfair_pricing_prices')->where('sku', 'A')->value('price_po_number'));
        $this->assertNotNull(DB::table('wayfair_pricing_prices')->where('sku', 'A')->value('price_synced_at'));
        $this->assertNotNull(DB::table('wayfair_pricing_prices')->where('sku', 'B')->value('price_synced_at'));
        $this->assertSame(0, DB::table('wayfair_pricing_prices')->where('sku', 'NOT HERE')->count(), 'rows must never be created');
    }

    public function test_dry_run_saves_nothing(): void
    {
        $this->seedPrices(['A' => 10.00, 'B' => 5.00, 'C' => 7.00]);
        $result = (new WayfairBaseCostPullService())->apply($this->export(['A' => 11.00, 'B' => 5.00, 'C' => 7.00]), true);

        $this->assertSame(1, $result['updated']);
        $this->assertEqualsWithDelta(10.00, $this->price('A'), 0.001);
        $this->assertSame(0, DB::table('wayfair_pricing_prices')->whereNotNull('price_synced_at')->count());
    }

    public function test_sku_with_non_breaking_space_still_matches(): void
    {
        $this->seedPrices(["WF 8140\u{00A0}DBL D2" => 41.98, 'X' => 1.00, 'Y' => 1.00]);
        $result = (new WayfairBaseCostPullService())->apply($this->export(['WF 8140 DBL D2' => 41.98, 'X' => 1.00, 'Y' => 1.00]));

        $this->assertSame(3, $result['matched']);
        $this->assertSame(0, $result['no_row']);
    }

    public function test_large_change_is_aborted_unless_forced(): void
    {
        $this->seedPrices(['A' => 10, 'B' => 10, 'C' => 10, 'D' => 10]);
        $export = $this->export(['A' => 20, 'B' => 20, 'C' => 20, 'D' => 10]); // 75% would change
        $service = new WayfairBaseCostPullService();

        $result = $service->apply($export);
        $this->assertTrue($result['aborted']);
        $this->assertFalse($result['success']);
        $this->assertEqualsWithDelta(10.00, $this->price('A'), 0.001, 'nothing saved when aborted');

        $forced = $service->apply($export, false, true);
        $this->assertTrue($forced['success']);
        $this->assertEqualsWithDelta(20.00, $this->price('A'), 0.001);
    }

    public function test_upload_in_flight_skips_unless_forced(): void
    {
        $this->seedPrices(['A' => 10, 'B' => 10, 'C' => 10]);
        DB::table('wayfair_price_uploads')->insert(['status' => 'PROCESSING', 'created_at' => now(), 'updated_at' => now()]);
        $export = $this->export(['A' => 11, 'B' => 10, 'C' => 10]);
        $service = new WayfairBaseCostPullService();

        $skipped = $service->apply($export);
        $this->assertTrue($skipped['skipped_upload_in_flight']);
        $this->assertEqualsWithDelta(10.00, $this->price('A'), 0.001);

        $forced = $service->apply($export, false, true);
        $this->assertFalse($forced['skipped_upload_in_flight']);
        $this->assertEqualsWithDelta(11.00, $this->price('A'), 0.001);
    }

    public function test_finished_upload_does_not_block(): void
    {
        $this->seedPrices(['A' => 10, 'B' => 10, 'C' => 10]);
        DB::table('wayfair_price_uploads')->insert(['status' => 'FAILED', 'created_at' => now(), 'updated_at' => now()]);
        $result = (new WayfairBaseCostPullService())->apply($this->export(['A' => 11, 'B' => 10, 'C' => 10]));

        $this->assertFalse($result['skipped_upload_in_flight']);
        $this->assertEqualsWithDelta(11.00, $this->price('A'), 0.001);
    }

    public function test_empty_export_is_an_error(): void
    {
        $this->seedPrices(['A' => 10]);
        $result = (new WayfairBaseCostPullService())->apply([]);

        $this->assertFalse($result['success']);
        $this->assertEqualsWithDelta(10.00, $this->price('A'), 0.001);
    }
}
