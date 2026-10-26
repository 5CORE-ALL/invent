<?php

namespace App\Services\ProductMaster;

use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use App\Services\CategoryMasters\CategoryChannelSyncService;
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
     * @return array{success: bool, message: string, specs: list<array{key: string, value: string}>, account: string, category_id: string, category_path: string}
     */
    public function pull(string $sku): array
    {
        $sku = trim($sku);
        if ($this->isParentSku($sku)) {
            return $this->emptyResult(false, 'Parent rows are not listed on eBay.');
        }

        foreach (['eBay 1' => self::ACCOUNTS['eBay 1']] as $label => $cfg) {
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

            $category = $this->categoryFromItem($item);
            $specs = $this->specsFromItem($item, $sku);
            if ($specs === []) {
                return $this->emptyResult(false, $label.': the listing has no item specifics. Saved specifications were left unchanged.', $category);
            }
            $count = count($specs);

            return [
                'success' => true,
                'message' => 'Pulled '.$count.' specification'.($count === 1 ? '' : 's').' from '.$label.'.',
                'specs' => $specs,
                'account' => $label,
                'category_id' => $category['id'],
                'category_path' => $category['path'],
            ];
        }

        return $this->emptyResult(false, 'This SKU is not listed on eBay 1.');
    }

    /**
     * The live primary category of the listing. GetItem names it "Root:Sub:Leaf".
     *
     * @param  array<string, mixed>  $item
     * @return array{id: string, path: string}
     */
    private function categoryFromItem(array $item): array
    {
        $node = $item['Item']['PrimaryCategory'] ?? $item['PrimaryCategory'] ?? [];
        $id = is_array($node) ? trim((string) ($node['CategoryID'] ?? '')) : '';
        if ($id === '') {
            return ['id' => '', 'path' => ''];
        }
        $path = CategoryChannelSyncService::normalizePath((string) ($node['CategoryName'] ?? ''), ':');

        return ['id' => $id, 'path' => $path !== '' ? $path : 'eBay category '.$id];
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
        foreach (['eBay 1' => self::ACCOUNTS['eBay 1']] as $label => $cfg) {
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
            return ['success' => false, 'message' => 'This SKU is not listed on eBay 1.'];
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
        foreach ($this->specsFromDescription($node['Description'] ?? '') as $spec) {
            $this->addSpec($specs, $spec['key'], $spec['value']);
        }
        $this->addSpec($specs, 'Condition', $this->plainText($node['ConditionDisplayName'] ?? ''));
        $details = is_array($node['ProductListingDetails'] ?? null) ? $node['ProductListingDetails'] : [];
        $this->addSpec($specs, 'UPC', $this->plainText($details['UPC'] ?? ''));

        $variation = EbayTradingReviseItem::findVariationForSku($item, $sku);
        if ($variation === null) {
            return $specs;
        }
        foreach (EbayLiveListingMapper::nameValueMap($variation['VariationSpecifics']['NameValueList'] ?? null) as $name => $value) {
            $this->addSpec($specs, (string) $name, (string) $value, true);
        }

        return $specs;
    }

    /**
     * Specification lines live in the listing description when they are not item specifics.
     * The block starts at a "Specification" heading and continues through Key: Value lines.
     *
     * @return list<array{key: string, value: string}>
     */
    private function specsFromDescription(mixed $html): array
    {
        $html = trim(is_array($html) ? (string) reset($html) : (string) $html);
        if ($html === '') {
            return [];
        }

        $text = html_entity_decode(strip_tags(str_ireplace(
            ['<br>', '<br/>', '<br />', '</tr>', '</li>', '</p>', '</div>', '</h1>', '</h2>', '</h3>', '</td>'],
            "\n",
            $html
        )));
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? '');
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        $start = null;
        foreach ($lines as $index => $line) {
            if (preg_match('/\bspecifications?\b\s*:?\s*$/iu', $line)) {
                $start = $index + 1;
                break;
            }
        }
        if ($start === null) {
            return [];
        }

        $out = [];
        for ($index = $start, $count = count($lines); $index < $count; $index++) {
            if (! preg_match('/^(.{1,80}?)\s*:\s*(.+)$/u', $lines[$index], $match)) {
                if ($out !== []) {
                    break;
                }

                continue;
            }
            $key = trim($match[1]);
            $value = trim($match[2]);
            if ($key === '' || $value === '' || preg_match('/^https?:/i', $key)) {
                continue;
            }
            $out[] = ['key' => $key, 'value' => $value];
        }

        return $out;
    }

    /**
     * @param  list<array{key: string, value: string}>  $specs
     */
    private function plainText(mixed $value): string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return trim(html_entity_decode(strip_tags((string) $value)));
    }

    private function addSpec(array &$specs, string $key, string $value, bool $overwrite = false): void
    {
        $key = trim($key);
        $value = trim($value);
        if ($key === '' || $value === '') {
            return;
        }
        foreach ($specs as &$spec) {
            if (strcasecmp($spec['key'], $key) === 0) {
                if ($overwrite) {
                    $spec['value'] = $value;
                }

                return;
            }
        }
        unset($spec);
        $specs[] = ['key' => $key, 'value' => $value];
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
     * @param  array{id: string, path: string}|null  $category
     * @return array{success: bool, message: string, specs: list<array{key: string, value: string}>, account: string, category_id: string, category_path: string}
     */
    private function emptyResult(bool $success, string $message, ?array $category = null): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'specs' => [],
            'account' => '',
            'category_id' => (string) ($category['id'] ?? ''),
            'category_path' => (string) ($category['path'] ?? ''),
        ];
    }
}
