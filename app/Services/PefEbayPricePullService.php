<?php

namespace App\Services;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayThreeDataView;
use App\Models\EbayTwoDataView;
use Illuminate\Support\Facades\Log;

/**
 * PEF: after eBay 1/2/3 SPRICE push, pull live listing Price (GetItem) for only those SKUs
 * and return Price + SPRICE so the UI can patch just those rows.
 */
class PefEbayPricePullService
{
    /**
     * @param  list<array{sku?:string,marketplace?:string,row_id?:string|null}>  $items
     * @return list<array{success:bool,sku:string,marketplace:string,row_id:?string,price:?float,sprice:?float,message:string}>
     */
    public function pullItems(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? ''));
            $mp = $this->normalizeMarketplace((string) ($item['marketplace'] ?? ''));
            $rowId = isset($item['row_id']) ? (string) $item['row_id'] : null;
            if ($sku === '' || $mp === null) {
                $out[] = [
                    'success' => false,
                    'sku' => $sku,
                    'marketplace' => (string) ($item['marketplace'] ?? ''),
                    'row_id' => $rowId,
                    'price' => null,
                    'sprice' => null,
                    'message' => 'sku and ebay1/2/3 marketplace required',
                ];
                continue;
            }
            $out[] = $this->pullOne($sku, $mp, $rowId);
        }

        return $out;
    }

    /**
     * @return array{success:bool,sku:string,marketplace:string,row_id:?string,price:?float,sprice:?float,message:string}
     */
    public function pullOne(string $sku, string $marketplace, ?string $rowId = null): array
    {
        $base = [
            'success' => false,
            'sku' => $sku,
            'marketplace' => $marketplace,
            'row_id' => $rowId,
            'price' => null,
            'sprice' => null,
            'message' => '',
        ];

        try {
            [$metric, $api, $dvClass] = $this->resolveChannel($sku, $marketplace);
            if (! $metric || ! $metric->item_id) {
                $base['message'] = 'eBay listing not found for SKU';

                return $base;
            }

            $itemId = trim((string) $metric->item_id);
            $apiSku = trim((string) ($metric->sku ?: $sku));
            $resp = $api->getItem($itemId);
            if (! is_array($resp)) {
                $base['message'] = 'GetItem failed';

                return $base;
            }

            $live = $this->extractPriceFromGetItem($resp, $apiSku);
            if (! ($live > 0)) {
                $base['message'] = 'Live price not found in GetItem response';

                return $base;
            }

            $metric->ebay_price = $live;
            $metric->save();

            $sprice = $this->readSprice($dvClass, $sku);
            $base['success'] = true;
            $base['price'] = round($live, 2);
            $base['sprice'] = $sprice;
            $base['message'] = 'Pulled live price $'.number_format($live, 2);

            return $base;
        } catch (\Throwable $e) {
            Log::warning('PEF eBay price pull failed', [
                'sku' => $sku,
                'marketplace' => $marketplace,
                'error' => $e->getMessage(),
            ]);
            $base['message'] = $e->getMessage();

            return $base;
        }
    }

    public function normalizeMarketplace(string $raw): ?string
    {
        $mp = strtolower(preg_replace('/\s+/', '', $raw) ?? '');
        if (in_array($mp, ['ebay', 'ebay1', 'ebayone'], true)) {
            return 'ebay1';
        }
        if (in_array($mp, ['ebay2', 'ebaytwo'], true)) {
            return 'ebay2';
        }
        if (in_array($mp, ['ebay3', 'ebaythree'], true)) {
            return 'ebay3';
        }

        return null;
    }

    /**
     * @return array{0:?object,1:object,2:class-string}|array{0:null,1:null,2:null}
     */
    private function resolveChannel(string $sku, string $marketplace): array
    {
        $find = static function (string $modelClass) use ($sku) {
            $m = $modelClass::query()->where('sku', $sku)->first();
            if ($m) {
                return $m;
            }

            return $modelClass::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
                ->first();
        };

        return match ($marketplace) {
            'ebay1' => [$find(EbayMetric::class), app(EbayApiService::class), EbayDataView::class],
            'ebay2' => [$find(Ebay2Metric::class), app(Ebay2ApiService::class), EbayTwoDataView::class],
            'ebay3' => [$find(Ebay3Metric::class), app(EbayThreeApiService::class), EbayThreeDataView::class],
            default => [null, null, null],
        };
    }

    /**
     * @param  class-string  $dvClass
     */
    private function readSprice(string $dvClass, string $sku): ?float
    {
        $dv = $dvClass::query()->where('sku', $sku)->first()
            ?: $dvClass::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first();
        if (! $dv) {
            return null;
        }
        $val = is_array($dv->value) ? $dv->value : [];
        $sp = $val['SPRICE'] ?? $val['sprice'] ?? null;
        if (! is_numeric($sp) || ! ((float) $sp > 0)) {
            return null;
        }

        return round((float) $sp, 2);
    }

    private function extractPriceFromGetItem(array $resp, string $sku): ?float
    {
        $item = $resp['Item'] ?? null;
        if (! is_array($item)) {
            return null;
        }

        $vars = $item['Variations']['Variation'] ?? null;
        if ($vars !== null && $sku !== '') {
            $list = (is_array($vars) && (isset($vars['SKU']) || isset($vars['StartPrice'])))
                ? [$vars]
                : (is_array($vars) ? $vars : []);
            foreach ($list as $v) {
                if (! is_array($v)) {
                    continue;
                }
                if (strcasecmp(trim((string) ($v['SKU'] ?? '')), $sku) !== 0) {
                    continue;
                }
                $p = $this->parseEbayMoney($v['StartPrice'] ?? null)
                    ?? $this->parseEbayMoney($v['SellingStatus']['CurrentPrice'] ?? null);
                if ($p !== null && $p > 0) {
                    return $p;
                }
            }
        }

        return $this->parseEbayMoney($item['SellingStatus']['CurrentPrice'] ?? null)
            ?? $this->parseEbayMoney($item['StartPrice'] ?? null);
    }

    private function parseEbayMoney(mixed $node): ?float
    {
        if ($node === null || $node === '') {
            return null;
        }
        if (is_numeric($node)) {
            $n = (float) $node;

            return $n > 0 ? $n : null;
        }
        if (is_array($node)) {
            if (isset($node[0]) && is_numeric($node[0])) {
                $n = (float) $node[0];

                return $n > 0 ? $n : null;
            }
            if (isset($node['value']) && is_numeric($node['value'])) {
                $n = (float) $node['value'];

                return $n > 0 ? $n : null;
            }
            foreach ($node as $k => $v) {
                if ($k === '@attributes') {
                    continue;
                }
                if (is_numeric($v)) {
                    $n = (float) $v;

                    return $n > 0 ? $n : null;
                }
            }
        }

        return null;
    }
}
