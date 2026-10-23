<?php

namespace App\Services\ProductMaster;

use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use App\Services\MarketplaceManager\EbayLiveListingMapper;
use App\Services\Support\EbayTradingReviseItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pull live eBay item specifics into Technical Specifications, and push those values back.
 * Pull uses the first eBay account that lists the SKU. Push updates every account that lists it.
 */
class EbayTechnicalSpecificationSync
{
    /** @var array<string, array{service: class-string, table: string}> */
    private const ACCOUNTS = [
        'eBay 1' => ['service' => EbayApiService::class, 'table' => 'ebay_metrics'],
        'eBay 2' => ['service' => Ebay2ApiService::class, 'table' => 'ebay_2_metrics'],
        'eBay 3' => ['service' => EbayThreeApiService::class, 'table' => 'ebay_3_metrics'],
    ];

    /**
     * @return array{success: bool, message: string, specs: list<array{key: string, value: string}>, account: string}
     */
    public function pull(string $sku): array
    {
        $sku = trim($sku);
        if ($this->isParentSku($sku)) {
            return $this->emptyResult(false, 'Parent rows are not listed on eBay.');
        }

        foreach (self::ACCOUNTS as $label => $cfg) {
            $itemId = $this->listingId($cfg['table'], $sku);
            if ($itemId === null) {
                continue;
            }
            try {
                $service = app($cfg['service']);
                $item = $service->getItem($itemId);
            } catch (\Throwable $e) {
                Log::warning('Technical specifications eBay pull failed', ['sku' => $sku, 'account' => $label, 'error' => $e->getMessage()]);

                return $this->emptyResult(false, $label.': '.$e->getMessage());
            }
            if (! is_array($item)) {
                $why = trim((string) ($service->lastGetItemError ?? ''));

                return $this->emptyResult(false, $label.': '.($why !== '' ? $why : 'eBay did not return the listing.'));
            }

            $specs = $this->specsFromItem($item, $sku);
            $count = count($specs);

            return [
                'success' => true,
                'message' => 'Pulled '.$count.' specification'.($count === 1 ? '' : 's').' from '.$label.'.',
                'specs' => $specs,
                'account' => $label,
            ];
        }

        return $this->emptyResult(false, 'This SKU is not listed on eBay 1, eBay 2, or eBay 3.');
    }

    /**
     * Blank values are omitted. eBay keeps its current value for those names.
     *
     * @param  list<array{key: string, value: string}>  $specs
     * @return array{success: bool, message: string}
     */
    public function push(string $sku, array $specs): array
    {
        $sku = trim($sku);
        if ($this->isParentSku($sku)) {
            return ['success' => false, 'message' => 'Parent rows are not listed on eBay.'];
        }

        $overrides = [];
        foreach ($specs as $row) {
            $name = trim((string) ($row['key'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($name !== '' && $value !== '') {
                $overrides[] = ['name' => $name, 'value' => $value];
            }
        }

        $messages = [];
        $found = false;
        $failed = false;
        foreach (self::ACCOUNTS as $label => $cfg) {
            $itemId = $this->listingId($cfg['table'], $sku);
            if ($itemId === null) {
                continue;
            }
            $found = true;
            try {
                /** @var EbayApiService|Ebay2ApiService|EbayThreeApiService $service */
                $service = app($cfg['service']);
                $item = $service->getItem($itemId);
                if (! is_array($item)) {
                    $why = trim((string) ($service->lastGetItemError ?? ''));
                    $failed = true;
                    $messages[] = $label.': '.($why !== '' ? $why : 'eBay did not return the listing.');

                    continue;
                }
                $ctx = $service->tradingReviseContext();
                $result = EbayTradingReviseItem::reviseTechnicalSpecifications(
                    $ctx['endpoint'],
                    $ctx['compatLevel'],
                    $ctx['devId'],
                    $ctx['appId'],
                    $ctx['certId'],
                    $ctx['siteId'],
                    $ctx['authToken'],
                    $item,
                    $sku,
                    $overrides
                );
                if (! ($result['success'] ?? false)) {
                    $failed = true;
                }
                $messages[] = $label.': '.$result['message'];
            } catch (\Throwable $e) {
                Log::warning('Technical specifications eBay push failed', ['sku' => $sku, 'account' => $label, 'error' => $e->getMessage()]);
                $failed = true;
                $messages[] = $label.': '.$e->getMessage();
            }
        }

        if (! $found) {
            return ['success' => false, 'message' => 'This SKU is not listed on eBay 1, eBay 2, or eBay 3.'];
        }

        return ['success' => ! $failed, 'message' => implode(' ', $messages)];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<array{key: string, value: string}>
     */
    private function specsFromItem(array $item, string $sku): array
    {
        $node = is_array($item['Item'] ?? null) ? $item['Item'] : $item;
        $rows = EbayTradingReviseItem::normalizeItemSpecificsNameValueLists($node['ItemSpecifics'] ?? null);
        $specs = [];
        foreach ($rows as $row) {
            $specs[] = ['key' => $row['name'], 'value' => implode(', ', $row['values'])];
        }

        $variation = EbayTradingReviseItem::findVariationForSku($item, $sku);
        if ($variation === null) {
            return $specs;
        }
        foreach (EbayLiveListingMapper::nameValueMap($variation['VariationSpecifics']['NameValueList'] ?? null) as $name => $value) {
            $replaced = false;
            foreach ($specs as &$spec) {
                if (strcasecmp($spec['key'], (string) $name) === 0) {
                    $spec['value'] = (string) $value;
                    $replaced = true;
                    break;
                }
            }
            unset($spec);
            if (! $replaced) {
                $specs[] = ['key' => (string) $name, 'value' => (string) $value];
            }
        }

        return $specs;
    }

    private function listingId(string $table, string $sku): ?string
    {
        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'item_id') || ! Schema::hasColumn($table, 'sku')) {
                return null;
            }
            $key = strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
            $ids = DB::table($table)
                ->whereRaw('UPPER(TRIM(sku)) = ?', [$key])
                ->whereNotNull('item_id')
                ->where('item_id', '!=', '')
                ->pluck('item_id');
        } catch (\Throwable $e) {
            Log::warning('Technical specifications eBay listing lookup failed', ['table' => $table, 'error' => $e->getMessage()]);

            return null;
        }

        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id !== '' && strcasecmp($id, trim($sku)) !== 0) {
                return $id;
            }
        }

        return null;
    }

    private function isParentSku(string $sku): bool
    {
        return stripos(trim($sku), 'PARENT ') === 0;
    }

    /**
     * @return array{success: bool, message: string, specs: list<array{key: string, value: string}>, account: string}
     */
    private function emptyResult(bool $success, string $message): array
    {
        return ['success' => $success, 'message' => $message, 'specs' => [], 'account' => ''];
    }
}
