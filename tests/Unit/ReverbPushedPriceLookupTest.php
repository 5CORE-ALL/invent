<?php

namespace Tests\Unit;

use App\Services\ChannelLivePriceSync;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReverbPushedPriceLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite_reverb_test');
        Config::set('database.connections.sqlite_reverb_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite_reverb_test');

        Schema::create('reverb_data_view', function ($t) {
            $t->id();
            $t->string('sku');
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('reverb_view_data', function ($t) {
            $t->id();
            $t->string('sku');
            $t->string('parent')->nullable();
            $t->text('values')->nullable();
            $t->string('bump_req')->nullable();
            $t->timestamps();
        });
    }

    public function test_newer_server_push_beats_old_data_view_stamp(): void
    {
        // Old page push stamped 50.00 in reverb_data_view.
        DB::table('reverb_data_view')->insert([
            'sku' => 'GT 1 BLK',
            'value' => json_encode(['SPRICE_PUSHED_VALUE' => 50.00, 'SPRICE_PUSHED_AT' => '2026-10-01 10:00:00']),
        ]);
        // Newer server push of 59.99 only lands in reverb_view_data.
        DB::table('reverb_view_data')->insert([
            'sku' => 'GT 1 BLK',
            'values' => json_encode(['SPRICE_PUSHED_VALUE' => 59.99, 'SPRICE_PUSHED_AT' => '2026-10-20 04:25:00']),
        ]);

        $map = ChannelLivePriceSync::lookupMap('reverb');
        $this->assertSame(59.99, $map['GT 1 BLK']);

        // reverb:fetch: API says 59.99, stale stamp used to win (50.00). Now RV Price follows the real push.
        $this->assertSame(59.99, ChannelLivePriceSync::preferIncoming('reverb', 'GT 1 BLK', 59.99, $map));
    }

    public function test_newer_data_view_stamp_beats_old_view_data_push(): void
    {
        DB::table('reverb_data_view')->insert([
            'sku' => 'GT 2',
            'value' => json_encode(['SPRICE_PUSHED_VALUE' => 70.00, 'SPRICE_PUSHED_AT' => '2026-10-21 09:00:00']),
        ]);
        DB::table('reverb_view_data')->insert([
            'sku' => 'GT 2',
            'values' => json_encode(['SPRICE_PUSHED_VALUE' => 65.00, 'SPRICE_PUSHED_AT' => '2026-10-10 09:00:00']),
        ]);

        $map = ChannelLivePriceSync::lookupMap('reverb');
        $this->assertSame(70.00, $map['GT 2']);
    }

    public function test_sku_only_in_one_table_is_found(): void
    {
        DB::table('reverb_view_data')->insert([
            'sku' => 'ONLY VIEW',
            'values' => json_encode(['SPRICE_PUSHED_VALUE' => 12.5]),
        ]);
        DB::table('reverb_data_view')->insert([
            'sku' => 'ONLY STAMP',
            'value' => json_encode(['CHANNEL_PUSHED_PRICE' => 33.0]),
        ]);

        $map = ChannelLivePriceSync::lookupMap('reverb');
        $this->assertSame(12.5, $map['ONLY VIEW']);
        $this->assertSame(33.0, $map['ONLY STAMP']);
    }
}
