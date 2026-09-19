<?php

namespace Tests\Feature;

use App\Models\Temu3Metric;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FetchTemu3MetricsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\CloseDbConnections::class);
    }

    public function test_import_from_json_upserts_temu3_metrics(): void
    {
        if (! Schema::hasTable('temu3_metrics')) {
            $this->markTestSkipped('temu3_metrics table is missing');
        }

        $path = storage_path('app/temu3-metrics-test.json');
        file_put_contents($path, json_encode([
            [
                'sku' => 'TEMU3 TEST SKU',
                'sku_id' => '123',
                'goods_id' => '456',
                'quantity' => 12,
                'listing_status' => 'active',
            ],
        ]));

        try {
            $this->artisan('app:fetch-temu3-metrics', ['--from-json' => $path])
                ->assertExitCode(0);

            $row = Temu3Metric::query()->where('sku', 'TEMU3 TEST SKU')->first();
            $this->assertNotNull($row);
            $this->assertSame('123', (string) $row->sku_id);
            $this->assertSame('456', (string) $row->goods_id);
            $this->assertSame(12, (int) $row->quantity);
            $this->assertSame('active', $row->listing_status);
        } finally {
            @unlink($path);
            Temu3Metric::query()->where('sku', 'TEMU3 TEST SKU')->delete();
        }
    }
}
