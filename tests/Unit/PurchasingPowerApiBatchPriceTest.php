<?php

namespace Tests\Unit;

use App\Services\PurchasingPowerApiService;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Tests\TestCase;

class PurchasingPowerApiBatchPriceTest extends TestCase
{
    /** A PP API double: no DB, no network. $lines is what the import status reports. */
    private function api(array $lines, array $unlisted = []): object
    {
        config(['services.purchasingpower.mcm_base_url' => 'https://mcm.test']);

        return new class($lines, $unlisted) extends PurchasingPowerApiService
        {
            public int $imports = 0;

            public array $csvs = [];

            public array $single = [];

            public array $synced = [];

            public function __construct(private array $lines, private array $unlisted)
            {
            }

            protected function miraklMcmApiKey(): ?string
            {
                return 'test-key';
            }

            protected function resolveBatchOfferSku(string $sku, string $apiKey, string $baseUrl): array
            {
                if (in_array($sku, $this->unlisted, true)) {
                    return [null, ['success' => false, 'message' => 'SKU is not listed on Purchasing Power MCM: '.$sku, 'status_code' => 404]];
                }

                return [$sku, null];
            }

            protected function miraklMcmPostPricingImport(string $apiKey, string $url, string $csv, string $filename): mixed
            {
                $this->imports++;
                $this->csvs[] = $csv;

                return new Response(new Psr7Response(200, [], json_encode(['import_id' => 'imp-'.$this->imports])));
            }

            protected function waitForPricingImport(string $importId, string $apiKey, string $baseUrl, int $maxPolls = 10): array
            {
                return $this->lines;
            }

            protected function syncLocalPriceAfterPush(string $offerSku, float $price): void
            {
                $this->synced[$offerSku] = $price;
            }

            public function updatePrice(string $sku, float $price): array
            {
                $this->single[] = $sku;

                return ['success' => true, 'message' => 'single', 'status_code' => 200];
            }
        };
    }

    public function test_many_skus_go_in_one_import(): void
    {
        $api = $this->api(['status' => 'COMPLETE', 'lines_in_success' => 3, 'lines_in_error' => 0]);

        $out = $api->updatePricesBatch([
            ['sku' => 'A 1', 'price' => 10.5],
            ['sku' => 'B 2', 'price' => 20],
            ['sku' => 'C 3', 'price' => 30.25],
        ]);

        $this->assertSame(1, $api->imports);
        $this->assertSame([], $api->single);
        $this->assertStringContainsString('"A 1";10.50', $api->csvs[0]);
        $this->assertStringContainsString('"B 2";20.00', $api->csvs[0]);
        $this->assertStringContainsString('"C 3";30.25', $api->csvs[0]);
        foreach (['A 1', 'B 2', 'C 3'] as $sku) {
            $this->assertTrue($out[$sku]['success']);
        }
        $this->assertSame(['A 1' => 10.5, 'B 2' => 20.0, 'C 3' => 30.25], $api->synced);
    }

    public function test_an_import_with_an_error_line_falls_back_to_one_by_one(): void
    {
        $api = $this->api(['status' => 'COMPLETE', 'lines_in_success' => 1, 'lines_in_error' => 1]);

        $out = $api->updatePricesBatch([
            ['sku' => 'A 1', 'price' => 10],
            ['sku' => 'B 2', 'price' => 20],
        ]);

        $this->assertSame(1, $api->imports);
        $this->assertSame(['A 1', 'B 2'], $api->single);
        $this->assertSame([], $api->synced, 'a failed batch import must not update the local price');
        $this->assertTrue($out['A 1']['success']);
    }

    public function test_an_unfinished_import_falls_back_to_one_by_one(): void
    {
        $api = $this->api([]);

        $api->updatePricesBatch([
            ['sku' => 'A 1', 'price' => 10],
            ['sku' => 'B 2', 'price' => 20],
        ]);

        $this->assertSame(['A 1', 'B 2'], $api->single);
        $this->assertSame([], $api->synced);
    }

    public function test_an_unlisted_sku_is_reported_and_not_in_the_import(): void
    {
        $api = $this->api(['status' => 'COMPLETE', 'lines_in_success' => 2, 'lines_in_error' => 0], ['GONE']);

        $out = $api->updatePricesBatch([
            ['sku' => 'A 1', 'price' => 10],
            ['sku' => 'GONE', 'price' => 99],
            ['sku' => 'B 2', 'price' => 20],
        ]);

        $this->assertFalse($out['GONE']['success']);
        $this->assertSame(404, $out['GONE']['status_code']);
        $this->assertStringNotContainsString('GONE', $api->csvs[0]);
        $this->assertTrue($out['A 1']['success']);
        $this->assertTrue($out['B 2']['success']);
    }

    public function test_one_ready_sku_uses_the_normal_single_push(): void
    {
        $api = $this->api(['status' => 'COMPLETE', 'lines_in_success' => 1, 'lines_in_error' => 0]);

        $api->updatePricesBatch([['sku' => 'A 1', 'price' => 10]]);

        $this->assertSame(0, $api->imports);
        $this->assertSame(['A 1'], $api->single);
    }
}
