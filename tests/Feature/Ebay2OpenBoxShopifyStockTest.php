<?php

namespace Tests\Feature;

use App\Http\Controllers\MarketPlace\EbayTwoController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Open Box Analytics (/ebay2op-tabulator-view) must also list Shopify OPEN BOX SKUs that have stock.
 */
class Ebay2OpenBoxShopifyStockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('shopify_skus', function ($table) {
            $table->id();
            $table->string('variant_id')->nullable();
            $table->string('sku')->nullable();
            $table->integer('inv')->default(0);
            $table->integer('quantity')->default(0);
            $table->string('image_src')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /** @return array<string, object> */
    private function shopifyOpenBoxRows(): array
    {
        $m = new ReflectionMethod(EbayTwoController::class, 'shopifyOpenBoxStockRows');
        $m->setAccessible(true);

        return $m->invoke(app(EbayTwoController::class));
    }

    public function test_is_open_box_sku_tolerates_shopify_whitespace_and_case(): void
    {
        $this->assertTrue(EbayTwoController::isOpenBoxSku('36L BLACK OPEN BOX'));
        $this->assertTrue(EbayTwoController::isOpenBoxSku("36L BLACK OPEN\u{00A0}BOX"));
        $this->assertTrue(EbayTwoController::isOpenBoxSku('36L-Black-open-box'));
        $this->assertFalse(EbayTwoController::isOpenBoxSku('36L BLACK'));
        $this->assertFalse(EbayTwoController::isOpenBoxSku('BOX OPENER'));
    }

    public function test_only_shopify_open_box_skus_with_stock_are_returned(): void
    {
        DB::table('shopify_skus')->insert([
            ['sku' => '36L BLACK OPEN BOX', 'inv' => 4, 'quantity' => 2, 'variant_id' => '111'],
            ['sku' => "DS CH YLW OPEN\u{00A0}BOX", 'inv' => 1, 'quantity' => 0, 'variant_id' => '222'],
            ['sku' => 'ZERO STOCK OPEN BOX', 'inv' => 0, 'quantity' => 5, 'variant_id' => '333'],
            ['sku' => '36L BLACK', 'inv' => 50, 'quantity' => 9, 'variant_id' => '444'],
            ['sku' => 'OPENBOX NOSPACE', 'inv' => 3, 'quantity' => 0, 'variant_id' => '555'],
        ]);

        $rows = $this->shopifyOpenBoxRows();

        $this->assertSame(['36L BLACK OPEN BOX', 'DS CH YLW OPEN BOX'], array_keys($rows));
        $this->assertSame(4, (int) $rows['36L BLACK OPEN BOX']->inv);
        $this->assertSame(1, (int) $rows['DS CH YLW OPEN BOX']->inv);
    }

    public function test_duplicate_sku_prefers_row_with_variant_id(): void
    {
        DB::table('shopify_skus')->insert([
            ['sku' => 'KBB OPEN BOX', 'inv' => 7, 'variant_id' => null],
            ['sku' => 'KBB OPEN BOX', 'inv' => 2, 'variant_id' => '999'],
        ]);

        $rows = $this->shopifyOpenBoxRows();

        $this->assertCount(1, $rows);
        $this->assertSame('999', (string) $rows['KBB OPEN BOX']->variant_id);
    }
}
