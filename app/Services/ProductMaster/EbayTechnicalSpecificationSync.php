<?php

namespace App\Services\ProductMaster;

use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use App\Services\Support\EbayTradingReviseItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Push the technical specifications of a SKU to eBay.
 * Specifications go to eBay 1 only. The UPC goes to every eBay account that lists the SKU.
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
     * Blank values are omitted. eBay keeps its current value for those names.
     *
     * @param  list<array{key: string, value: string}>  $specs
     * @return array{success: bool, message: string}
     */
    public function push(string $sku, array $specs, string $upc = ''): array
    {
        $sku = trim($sku);
        if ($this->isParentSku($sku)) {
            return ['success' => false, 'message' => 'Parent rows are not listed on eBay.'];
        }

        // UPC is not an item specific. It goes to ProductListingDetails on every eBay account (see below).
        $overrides = [];
        foreach ($specs as $row) {
            $name = trim((string) ($row['key'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($name !== '' && $value !== '' && strcasecmp($name, 'UPC') !== 0) {
                $overrides[] = ['name' => $name, 'value' => $value];
            }
        }

        $messages = [];
        $found = false;
        $failed = false;
        foreach (self::ACCOUNTS as $label => $cfg) {
            $specsHere = $label === 'eBay 1';
            if (! $specsHere && $upc === '') {
                continue;
            }
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
                if ($specsHere) {
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
                }
                if ($upc !== '') {
                    $upcResult = EbayTradingReviseItem::reviseUpc(
                        $ctx['endpoint'],
                        $ctx['compatLevel'],
                        $ctx['devId'],
                        $ctx['appId'],
                        $ctx['certId'],
                        $ctx['siteId'],
                        $ctx['authToken'],
                        $item,
                        $sku,
                        $upc
                    );
                    if (! ($upcResult['success'] ?? false)) {
                        $failed = true;
                    }
                    $messages[] = $label.' UPC: '.$upcResult['message'];
                }
            } catch (\Throwable $e) {
                Log::warning('Technical specifications eBay push failed', ['sku' => $sku, 'account' => $label, 'error' => $e->getMessage()]);
                $failed = true;
                $messages[] = $label.': '.$e->getMessage();
            }
        }

        if (! $found) {
            return ['success' => false, 'message' => $upc !== '' ? 'This SKU is not listed on eBay 1, 2 or 3.' : 'This SKU is not listed on eBay 1.'];
        }

        return ['success' => ! $failed, 'message' => implode(' ', $messages)];
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
}
