<?php

namespace Tests\Feature;

use App\Http\Controllers\MarketPlace\ShopifyOpenBoxAnalyticsController as C;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopifyOpenBoxAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('shopify_skus', function ($t) {
            $t->id();
            $t->string('variant_id')->nullable();
            $t->string('sku')->nullable();
            $t->integer('inv')->default(0);
            $t->integer('quantity')->default(0);
            $t->integer('views')->default(0);
            $t->decimal('price', 10, 2)->nullable();
            $t->string('image_src')->nullable();
            $t->string('product_link')->nullable();
            $t->timestamps();
        });
        Schema::create('product_master', function ($t) {
            $t->id();
            $t->string('sku')->nullable();
            $t->string('parent')->nullable();
            $t->text('Values')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function test_sku_detection_and_base_sku(): void
    {
        $this->assertTrue(C::isOpenBoxSku('36L BLACK OPEN BOX'));
        $this->assertTrue(C::isOpenBoxSku("36L BLACK OPEN\u{00A0}BOX"));
        $this->assertTrue(C::isOpenBoxSku('36l-black-open-box'));
        $this->assertFalse(C::isOpenBoxSku('36L BLACK'));
        $this->assertFalse(C::isOpenBoxSku(null));

        $this->assertSame('36L BLACK', C::baseNormFromOpenBoxSku('36L BLACK OPEN BOX'));
        $this->assertSame('36L BLACK', C::baseNormFromOpenBoxSku('36L-Black-Open-Box'));
        $this->assertSame('36L BLACK', C::baseNormFromOpenBoxSku('OPEN BOX 36L BLACK'));
    }

    public function test_calc(): void
    {
        // price 100, take-home 95% -> 95 - LP 40 - ship 5 = 50 unit profit
        $r = C::calc(price: 100, lp: 40, ship: 5, inv: 4, l30: 2, views: 50, basePrice: 125);

        $this->assertSame(50.0, $r['unit_profit']);
        $this->assertSame(50.0, $r['gpft']);
        $this->assertSame(125.0, $r['roi']);
        $this->assertSame(100.0, $r['profit_l30']);
        $this->assertSame(200.0, $r['sales_l30']);
        $this->assertSame(50.0, $r['dil']);
        $this->assertSame(4.0, $r['cvr']);
        $this->assertSame(160.0, $r['stock_cost']);
        $this->assertSame(400.0, $r['stock_value']);
        $this->assertSame(20.0, $r['discount_vs_base']);
    }

    public function test_calc_with_no_price_or_cost_does_not_divide_by_zero(): void
    {
        $r = C::calc(price: 0, lp: 0, ship: 0, inv: 0, l30: 0, views: 0, basePrice: null);

        $this->assertSame(0.0, $r['gpft']);
        $this->assertSame(0.0, $r['roi']);
        $this->assertSame(0.0, $r['dil']);
        $this->assertNull($r['discount_vs_base']);
    }

    public function test_build_rows_lists_only_shopify_open_box_skus_with_base_cost(): void
    {
        DB::table('product_master')->insert([
            'sku' => '36L BLACK', 'parent' => 'P1', 'Values' => json_encode(['lp' => 40, 'ship' => 5]),
        ]);
        DB::table('shopify_skus')->insert([
            ['sku' => '36L BLACK', 'inv' => 10, 'quantity' => 0, 'views' => 0, 'price' => 125, 'variant_id' => '1'],
            ['sku' => '36L BLACK OPEN BOX', 'inv' => 4, 'quantity' => 2, 'views' => 50, 'price' => 100, 'variant_id' => '2'],
            ['sku' => "NO BASE OPEN\u{00A0}BOX", 'inv' => 1, 'quantity' => 0, 'views' => 0, 'price' => 30, 'variant_id' => '3'],
            ['sku' => 'JUST A PRODUCT', 'inv' => 9, 'quantity' => 0, 'views' => 0, 'price' => 10, 'variant_id' => '4'],
        ]);

        $rows = C::buildRows();
        $bySku = [];
        foreach ($rows as $r) {
            $bySku[$r['sku']] = $r;
        }

        $this->assertCount(2, $rows);
        $this->assertArrayNotHasKey('36L BLACK', $bySku);
        $this->assertArrayNotHasKey('JUST A PRODUCT', $bySku);

        $a = $bySku['36L BLACK OPEN BOX'];
        $this->assertSame('36L BLACK', $a['base_sku']);
        $this->assertSame(10.0, $a['base_inv']);
        $this->assertSame(4.0, $a['inv']);
        $this->assertSame(50.0, $a['gpft']);
        $this->assertSame(20.0, $a['discount_vs_base']);
        $this->assertFalse($a['base_missing']);

        $b = $bySku["NO BASE OPEN\u{00A0}BOX"];
        $this->assertTrue($b['base_missing']);
        $this->assertSame(0.0, $b['lp']);
    }
}
