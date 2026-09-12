<?php

namespace Tests\Unit;

use App\Services\MacysApiService;
use Illuminate\Http\Client\Response;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Tests\TestCase;

class MacysApiServiceBatchPriceTest extends TestCase
{
    public function test_unlisted_skus_fail_without_import(): void
    {
        $api = new class extends MacysApiService
        {
            public int $imports = 0;

            protected function miraklMcmApiKey(): ?string
            {
                return 'test-key';
            }

            protected function resolveLocalListedMacyOfferSkus(array $skus): array
            {
                return [];
            }

            protected function miraklMcmPostPricingImport(string $apiKey, string $url, string $csv, string $filename): mixed
            {
                $this->imports++;

                return new Response(new Psr7Response(200, [], json_encode(['import_id' => 'should-not-run'])));
            }
        };

        $first = $api->updatePrice('CS 04 2W 2PAIR', 12.34);
        $second = $api->updatePrice('CS 06 2W', 15.00);

        $this->assertSame(0, $api->imports);
        $this->assertFalse($first['success']);
        $this->assertSame(404, $first['status_code']);
        $this->assertFalse($second['success']);
    }

    public function test_each_listed_sku_gets_its_own_pri01_import(): void
    {
        $api = new class extends MacysApiService
        {
            public int $imports = 0;

            public array $csvs = [];

            protected function miraklMcmApiKey(): ?string
            {
                return 'test-key';
            }

            protected function resolveLocalListedMacyOfferSkus(array $skus): array
            {
                $out = [];
                foreach ($skus as $sku) {
                    $out[$sku] = $sku;
                }

                return $out;
            }

            protected function miraklMcmPostPricingImport(string $apiKey, string $url, string $csv, string $filename): mixed
            {
                $this->imports++;
                $this->csvs[] = $csv;

                return new Response(new Psr7Response(200, [], json_encode(['import_id' => 'imp-'.$this->imports])));
            }

            protected function waitForPricingImport(string $importId, string $apiKey, string $baseUrl, int $maxPolls = 10): array
            {
                return [
                    'status' => 'COMPLETE',
                    'lines_in_success' => 1,
                    'lines_in_error' => 0,
                ];
            }

            protected function syncLocalMacyPriceAfterPush(string $sku, string $offerSku, float $price): void
            {
            }
        };

        $first = $api->updatePrice('CS 05 2W WoG', 20.62);
        $second = $api->updatePrice('CS 06 2W WoG', 21.82);

        $this->assertSame(2, $api->imports);
        $this->assertStringContainsString('CS 05 2W WoG', $api->csvs[0]);
        $this->assertStringNotContainsString('CS 06 2W WoG', $api->csvs[0]);
        $this->assertStringContainsString('CS 06 2W WoG', $api->csvs[1]);
        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertSame('imp-1', $first['import_id']);
        $this->assertSame('imp-2', $second['import_id']);
    }

    public function test_error_report_marks_only_missing_offers_failed(): void
    {
        $api = new class extends MacysApiService
        {
            protected function miraklMcmApiKey(): ?string
            {
                return 'test-key';
            }

            protected function resolveLocalListedMacyOfferSkus(array $skus): array
            {
                $out = [];
                foreach ($skus as $sku) {
                    $out[$sku] = $sku;
                }

                return $out;
            }

            protected function miraklMcmPostPricingImport(string $apiKey, string $url, string $csv, string $filename): mixed
            {
                return new Response(new Psr7Response(200, [], json_encode(['import_id' => 'imp-2'])));
            }

            protected function waitForPricingImport(string $importId, string $apiKey, string $baseUrl, int $maxPolls = 10): array
            {
                return [
                    'status' => 'COMPLETE',
                    'lines_in_success' => 1,
                    'lines_in_error' => 1,
                ];
            }

            protected function fetchPricingImportFailedOfferSkus(string $importId, string $apiKey, string $baseUrl): array
            {
                return [
                    'CS 04 2W 2PAIR' => "No existing offer with SKU 'CS 04 2W 2PAIR' found",
                ];
            }

            protected function syncLocalMacyPriceAfterPush(string $sku, string $offerSku, float $price): void
            {
            }
        };

        $out = $api->updatePrices([
            'CS 05 2W WoG' => 20.62,
            'CS 04 2W 2PAIR' => 18.00,
        ]);

        $this->assertTrue($out['CS 05 2W WoG']['success']);
        $this->assertFalse($out['CS 04 2W 2PAIR']['success']);
        $this->assertStringContainsString('No existing offer', $out['CS 04 2W 2PAIR']['message']);
    }

    public function test_parses_pri01_error_report_skus(): void
    {
        $api = new class extends MacysApiService
        {
            public function parse(string $body): array
            {
                return $this->parsePricingImportFailedOfferSkus($body);
            }
        };

        $body = "offer-sku;error-message\n"
            ."CS 04 2W 2PAIR;No existing offer with SKU 'CS 04 2W 2PAIR' found\n";

        $failed = $api->parse($body);

        $this->assertArrayHasKey('CS 04 2W 2PAIR', $failed);
        $this->assertStringContainsString('No existing offer', $failed['CS 04 2W 2PAIR']);
    }
}
