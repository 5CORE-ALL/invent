<?php

namespace App\Console\Commands;

use App\Services\TemuApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TemuTestUpdateTitleCommand extends Command
{
    protected $signature = 'temu:test-update-title 
                            {--sku= : SKU to test (e.g. AMP 200+200)} 
                            {--title= : Title to use (default: test title)}';

    protected $description = 'Test different Temu API request structures for title update to fix "Add at least one SKU" (150010016)';

    protected array $skuListFieldNames = ['skuList', 'skuInfoList', 'skus', 'skuInfos', 'outSkuSnList'];

    protected array $apiTypesToTest = ['bg.local.goods.update', 'bg.goods.update', 'bg.product.update', 'bg.local.product.update'];

    public function handle(): int
    {
        $sku = trim((string) $this->option('sku'));
        $title = trim((string) $this->option('title')) ?: 'Test Title Update';

        if ($sku === '') {
            $this->error('Provide --sku=YOUR-SKU (e.g. --sku="AMP 200+200")');
            return 1;
        }

        $this->info("Temu title update – testing request structure variations for SKU: {$sku}");
        $this->newLine();

        $service = new TemuApiService;
        $goodsId = $service->getGoodsIdBySku($sku);
        $skuInfo = $service->getSkuInfoForGoodsAndSku($goodsId ?? '', $sku);

        if ($goodsId === null || $goodsId === '') {
            $this->error("Could not resolve goodsId for SKU: {$sku}");
            return 1;
        }

        $skuId = $skuInfo['skuId'] ?? null;
        if ($skuId === null || $skuId === '') {
            $this->warn("Could not resolve skuId – will try variations with outGoodsSn only.");
        } else {
            $this->info("Resolved: goodsId={$goodsId}, skuId={$skuId}");
        }

        $url = 'https://openapi-b-us.temu.com/openapi/router';
        $price = $service->getProductPrice($sku) ?? 1.00;

        $skuEntryOfficial = $skuId !== null ? [
            'skuId' => (int) $skuId,
            'outSkuSn' => $sku,
            'listPrice' => ['amount' => (string) $price, 'currency' => 'USD'],
            'weight' => '1',
            'length' => '1',
            'width' => '1',
            'height' => '1',
            'weightUnit' => 'g',
            'volumeUnit' => 'cm',
            'images' => [],
        ] : null;

        $skuEntry = $skuId !== null ? [
            'skuId' => (int) $skuId,
            'outSkuSn' => $sku,
            'skuName' => $title,
        ] : ['outSkuSn' => $sku];

        $skuEntryD = $skuId !== null ? [
            'skuId' => (int) $skuId,
            'skuCode' => $sku,
            'skuName' => $title,
        ] : ['skuCode' => $sku];

        $workingResult = null;

        if ($skuEntryOfficial !== null) {
            $this->line("Testing: Official docs structure (goodsBasic.goodsName, skuList with listPrice)");
            $body = [
                'type' => 'bg.local.goods.update',
                'goodsId' => (int) $goodsId,
                'goodsBasic' => ['goodsName' => $title],
                'skuList' => [$skuEntryOfficial],
            ];
            $result = $this->tryRequest($url, $body, $service, $sku);
            if ($result['success']) {
                $this->info("  OK – Official docs structure works!");
                $workingResult = ['type' => 'bg.local.goods.update', 'field' => 'skuList', 'structure' => 'official'];
            } else {
                $this->warn("  Failed: " . ($result['message'] ?? 'Unknown'));
            }
            $this->newLine();
        }

        foreach ($this->apiTypesToTest as $apiType) {
            $this->line("Testing API type: <comment>{$apiType}</comment>");
            $baseBody = [
                'type' => $apiType,
                'goodsId' => (int) $goodsId,
                'goodsName' => $title,
                'outGoodsSn' => $sku,
            ];

            foreach ($this->skuListFieldNames as $fieldName) {
                $value = ($fieldName === 'outSkuSnList') ? [$sku] : [$skuEntry];
                $body = array_merge($baseBody, [$fieldName => $value]);
                $result = $this->tryRequest($url, $body, $service, $sku);
                if ($result['success']) {
                    $this->info("  OK – {$apiType} + {$fieldName} works!");
                    $workingResult = ['type' => $apiType, 'field' => $fieldName, 'structure' => 'array'];
                    break 2;
                }
            }
            $this->warn("  All field variations failed for {$apiType}");
        }

        if ($workingResult === null) {
            $apiType = config('services.temu.goods_update_type', 'bg.local.goods.update');
            $baseBody = ['type' => $apiType, 'goodsId' => (int) $goodsId, 'goodsName' => $title, 'outGoodsSn' => $sku];

            $this->newLine();
            $this->line("Testing Variation B: skuInfoList as object (not array)");
            $body = array_merge($baseBody, ['skuInfoList' => $skuEntry]);
            $result = $this->tryRequest($url, $body, $service, $sku);
            if ($result['success']) {
                $this->info("  OK – skuInfoList as object works!");
                $workingResult = ['type' => $apiType, 'field' => 'skuInfoList', 'structure' => 'object'];
            } else {
                $this->warn("  Failed: " . ($result['message'] ?? 'Unknown'));
            }
        }

        if ($workingResult === null) {
            $this->newLine();
            $this->line("Testing Variation C: skuId at root level");
            $apiType = config('services.temu.goods_update_type', 'bg.local.goods.update');
            $bodyRoot = [
                'type' => $apiType,
                'goodsId' => (int) $goodsId,
                'skuId' => (int) ($skuId ?? 0),
                'goodsName' => $title,
                'outGoodsSn' => $sku,
            ];
            $result = $this->tryRequest($url, $bodyRoot, $service, $sku);
            if ($result['success']) {
                $this->info("  OK – skuId at root works!");
                $workingResult = ['type' => $apiType, 'field' => 'skuId (root)', 'structure' => 'root'];
            } else {
                $this->warn("  Failed: " . ($result['message'] ?? 'Unknown'));
            }
        }

        if ($workingResult === null) {
            $this->newLine();
            $this->line("Testing Variation D: itemName + skuInfos with skuCode");
            $apiType = config('services.temu.goods_update_type', 'bg.local.goods.update');
            $body = [
                'type' => $apiType,
                'goodsId' => (int) $goodsId,
                'itemName' => $title,
                'outGoodsSn' => $sku,
                'skuInfos' => [$skuEntryD],
            ];
            $result = $this->tryRequest($url, $body, $service, $sku);
            if ($result['success']) {
                $this->info("  OK – Variation D (itemName, skuCode) works!");
                $workingResult = ['type' => $apiType, 'field' => 'skuInfos', 'structure' => 'variation_d'];
            } else {
                $this->warn("  Failed: " . ($result['message'] ?? 'Unknown'));
            }
        }

        $this->newLine();
        if ($workingResult !== null) {
            $this->info("Working structure: <info>" . json_encode($workingResult) . "</info>");
            if (($workingResult['structure'] ?? '') === 'official') {
                $this->line("Official docs structure works – no .env changes needed (defaults are correct).");
            } else {
                $this->line("Add to .env:");
                $this->line("  TEMU_GOODS_UPDATE_TYPE=" . $workingResult['type']);
                $this->line("  TEMU_SKU_LIST_FIELD=" . $workingResult['field']);
                if (($workingResult['structure'] ?? '') === 'variation_d') {
                    $this->line("  TEMU_GOODS_NAME_FIELD=itemName");
                    $this->line("  TEMU_SKU_CODE_FIELD=skuCode");
                }
            }
            return 0;
        }

        $this->warn('No variation succeeded. Check logs for full request/response. You may need to contact Temu support with your goodsId and the exact error.');
        return 1;
    }

    private function tryRequest(string $url, array $body, TemuApiService $service, string $sku): array
    {
        $signedRequest = $service->signRequest($body);
        $request = Http::withHeaders(['Content-Type' => 'application/json']);
        if (config('filesystems.default') === 'local') {
            $request = $request->withoutVerifying();
        }
        $response = $request->post($url, $signedRequest);
        $data = $response->json();
        $success = $response->successful() && ($data['success'] ?? false);
        $message = $data['errorMsg'] ?? $data['message'] ?? $response->body();
        Log::info('Temu test-update-title attempt', [
            'body_keys' => array_keys($body),
            'success' => $success,
            'errorMsg' => $message,
            'sku' => $sku,
        ]);
        return ['success' => $success, 'message' => $message];
    }

}
