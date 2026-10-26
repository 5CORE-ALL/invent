<?php

namespace App\Services\CategoryMasters;

use App\Services\AmazonSpApiService;
use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use App\Services\Temu2ApiService;
use App\Services\Temu3ApiService;
use App\Services\TemuApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pull / push one SKU's category with a sales channel.
 *
 *  - eBay: accounts 1, 2 and 3 (Trading API GetItem / ReviseFixedPriceItem).
 *  - Amazon: the single seller account (Catalog classifications / Listings recommended_browse_nodes).
 *  - Temu: accounts 1, 2 and 3. Pull only: the app has no call that changes a live Temu category.
 *
 * Pull reports the first account that has the SKU; the other accounts are checked and
 * differences are reported in the message. Push goes to every account that lists the SKU.
 */
class CategoryChannelSyncService
{
    public const CHANNELS = ['ebay', 'amz', 'temu'];

    /** @var array<string, array{service: class-string, table: string}> */
    protected const EBAY_ACCOUNTS = [
        'eBay 1' => ['service' => EbayApiService::class, 'table' => 'ebay_metrics'],
        'eBay 2' => ['service' => Ebay2ApiService::class, 'table' => 'ebay_2_metrics'],
        'eBay 3' => ['service' => EbayThreeApiService::class, 'table' => 'ebay_3_metrics'],
    ];

    /** @var array<string, array{service: class-string, table: string, pricing: string|null}> */
    protected const TEMU_ACCOUNTS = [
        'Temu 1' => ['service' => TemuApiService::class, 'table' => 'temu_metrics', 'pricing' => 'temu_pricing'],
        'Temu 2' => ['service' => Temu2ApiService::class, 'table' => 'temu2_metrics', 'pricing' => 'temu2_pricing'],
        'Temu 3' => ['service' => Temu3ApiService::class, 'table' => 'temu3_metrics', 'pricing' => 'temu3_pricing'],
    ];

    public const TEMU_PUSH_UNSUPPORTED = 'Changing the category of a live Temu listing is not supported by the Temu integration in this app, so nothing was sent.';

    // ---------------------------------------------------------------- Pull

    /**
     * @return array{success: bool, message: string, id: string, path: string, accounts: list<array<string, mixed>>}
     */
    public function pull(string $channel, string $sku): array
    {
        return match ($channel) {
            'ebay' => $this->pullEbay($sku),
            'amz' => $this->pullAmazon($sku),
            'temu' => $this->pullTemu($sku),
            default => $this->failedPull('Unknown channel.'),
        };
    }

    /**
     * Read the live category from one eBay account only (default eBay 1). Other accounts are not called.
     *
     * @return array{success: bool, message: string, id: string, path: string, accounts: list<array<string, mixed>>}
     */
    public function pullEbayAccount(string $sku, string $account = 'eBay 1'): array
    {
        $cfg = self::EBAY_ACCOUNTS[$account] ?? null;
        if ($cfg === null) {
            return $this->failedPull('Unknown eBay account.');
        }
        $itemId = $this->listingId($cfg['table'], 'item_id', $sku);
        if ($itemId === null) {
            return $this->aggregatePull([['account' => $account, 'status' => 'not_listed', 'message' => 'Not listed']], $account);
        }

        return $this->aggregatePull([['account' => $account] + $this->readEbayCategory($cfg['service'], $itemId)], $account);
    }

    protected function pullEbay(string $sku): array
    {
        $accounts = [];
        foreach (self::EBAY_ACCOUNTS as $label => $cfg) {
            $itemId = $this->listingId($cfg['table'], 'item_id', $sku);
            if ($itemId === null) {
                $accounts[] = ['account' => $label, 'status' => 'not_listed', 'message' => 'Not listed'];

                continue;
            }
            $accounts[] = ['account' => $label] + $this->readEbayCategory($cfg['service'], $itemId);
        }

        return $this->aggregatePull($accounts, 'eBay');
    }

    /**
     * @return array{status: string, id?: string, path?: string, message: string, item_id: string}
     */
    protected function readEbayCategory(string $serviceClass, string $itemId): array
    {
        try {
            $service = app($serviceClass);
            $item = $service->getItem($itemId);
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage(), 'item_id' => $itemId];
        }

        if (! is_array($item)) {
            $why = trim((string) ($service->lastGetItemError ?? ''));

            return ['status' => 'error', 'message' => $why !== '' ? $why : 'eBay did not return the listing.', 'item_id' => $itemId];
        }

        $node = $item['Item']['PrimaryCategory'] ?? $item['PrimaryCategory'] ?? [];
        $id = trim((string) ($node['CategoryID'] ?? ''));
        if ($id === '') {
            return ['status' => 'error', 'message' => 'eBay returned no category for this listing.', 'item_id' => $itemId];
        }

        return [
            'status' => 'ok',
            'id' => $id,
            // GetItem names the category as "Root:Sub:Leaf".
            'path' => self::normalizePath((string) ($node['CategoryName'] ?? ''), ':') ?: 'eBay category '.$id,
            'message' => 'OK',
            'item_id' => $itemId,
        ];
    }

    protected function pullAmazon(string $sku): array
    {
        try {
            $read = app(AmazonSpApiService::class)->fetchListingCategory($sku);
        } catch (\Throwable $e) {
            $read = ['success' => false, 'message' => $e->getMessage()];
        }

        $account = ($read['success'] ?? false)
            ? ['account' => 'Amazon', 'status' => 'ok', 'id' => (string) $read['id'], 'path' => (string) $read['path'], 'message' => 'OK']
            : ['account' => 'Amazon', 'status' => 'error', 'message' => (string) ($read['message'] ?? 'Amazon lookup failed.')];

        return $this->aggregatePull([$account], 'Amazon');
    }

    protected function pullTemu(string $sku): array
    {
        $accounts = [];
        foreach (self::TEMU_ACCOUNTS as $label => $cfg) {
            $goodsId = $this->listingId($cfg['table'], 'goods_id', $sku);
            if ($goodsId === null) {
                $accounts[] = ['account' => $label, 'status' => 'not_listed', 'message' => 'Not listed'];

                continue;
            }
            $accounts[] = ['account' => $label] + $this->readTemuCategory($cfg['service'], $cfg['pricing'], $goodsId);
        }

        return $this->aggregatePull($accounts, 'Temu');
    }

    /**
     * @return array{status: string, id?: string, path?: string, message: string}
     */
    protected function readTemuCategory(string $serviceClass, ?string $pricingTable, string $goodsId): array
    {
        try {
            $response = app($serviceClass)->callOpenApi('bg.local.goods.detail.query', ['goodsId' => (int) $goodsId]);
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        if (! ($response['success'] ?? false)) {
            return ['status' => 'error', 'message' => (string) ($response['errorMsg'] ?? $response['message'] ?? 'Temu lookup failed.')];
        }

        $result = is_array($response['result'] ?? null) ? $response['result'] : [];
        $found = self::temuCategoryFromDetail($result);
        if ($found['id'] === '') {
            return [
                'status' => 'error',
                'message' => 'Temu returned no category for this goods ID (response fields: '.implode(', ', array_slice(array_keys($result), 0, 12)).').',
            ];
        }

        $path = $found['path'] !== '' ? $found['path'] : $this->temuNameFromPricing($found['id'], $pricingTable);

        return ['status' => 'ok', 'id' => $found['id'], 'path' => $path !== '' ? $path : 'Temu category '.$found['id'], 'message' => 'OK'];
    }

    /**
     * Category ID (and path when Temu sends one) from a goods detail result.
     *
     * @param  array<string, mixed>  $result
     * @return array{id: string, path: string}
     */
    public static function temuCategoryFromDetail(array $result): array
    {
        $id = '';
        $path = '';
        $walk = function ($node) use (&$walk, &$id, &$path): void {
            if (! is_array($node)) {
                return;
            }
            foreach ($node as $key => $value) {
                $k = strtolower((string) $key);
                if ($id === '' && in_array($k, ['catid', 'leafcatid', 'cat_id'], true) && is_scalar($value) && preg_match('/^\d+$/', (string) $value) && (int) $value > 0) {
                    $id = (string) $value;
                }
                if ($id === '' && $k === 'catidlist' && is_array($value)) {
                    $ids = array_values(array_filter($value, static fn ($v) => is_scalar($v) && preg_match('/^\d+$/', (string) $v) && (int) $v > 0));
                    if ($ids !== []) {
                        $id = (string) end($ids);
                    }
                }
                if ($path === '' && in_array($k, ['catpath', 'categorypath'], true) && is_string($value) && trim($value) !== '') {
                    $path = self::normalizePath($value, '/');
                }
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($result);

        return ['id' => $id, 'path' => $path];
    }

    /**
     * Temu category names live on the pricing sheets (category_id -> "A/B/C").
     */
    protected function temuNameFromPricing(string $catId, ?string $preferredTable): string
    {
        $tables = array_values(array_unique(array_filter([$preferredTable, 'temu2_pricing', 'temu3_pricing', 'temu_pricing'])));
        foreach ($tables as $table) {
            try {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'category_id') || ! Schema::hasColumn($table, 'category')) {
                    continue;
                }
                $name = DB::table($table)->where('category_id', $catId)->whereNotNull('category')->where('category', '!=', '')->value('category');
                if ($name) {
                    return self::normalizePath((string) $name, '/');
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return '';
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     */
    protected function aggregatePull(array $accounts, string $siteLabel): array
    {
        $ok = array_values(array_filter($accounts, static fn ($a) => ($a['status'] ?? '') === 'ok'));
        if ($ok === []) {
            $errors = array_values(array_filter($accounts, static fn ($a) => ($a['status'] ?? '') === 'error'));
            $message = $errors === []
                ? 'Not listed on '.$siteLabel.'.'
                : implode(' | ', array_map(static fn ($a) => $a['account'].': '.$a['message'], $errors));

            return ['success' => false, 'message' => $message, 'id' => '', 'path' => '', 'accounts' => $accounts];
        }

        $primary = $ok[0];
        $notes = [];
        foreach (array_slice($ok, 1) as $other) {
            if ($other['id'] !== $primary['id']) {
                $notes[] = $other['account'].' differs ('.$other['path'].')';
            }
        }
        foreach ($accounts as $a) {
            if (($a['status'] ?? '') === 'error') {
                $notes[] = $a['account'].' failed: '.$a['message'];
            }
        }

        return [
            'success' => true,
            'message' => 'From '.$primary['account'].($notes !== [] ? '; '.implode('; ', $notes) : ''),
            'id' => (string) $primary['id'],
            'path' => (string) $primary['path'],
            'accounts' => $accounts,
        ];
    }

    protected function failedPull(string $message): array
    {
        return ['success' => false, 'message' => $message, 'id' => '', 'path' => '', 'accounts' => []];
    }

    // ---------------------------------------------------------------- Push

    /**
     * Push the saved category of one SKU.
     *
     * @param  string  $text  Category name saved in Category Masters.
     * @param  string  $savedId  Channel category ID saved by Pull (empty when the name was typed).
     * @param  string  $overrideId  ID confirmed in the dry-run; wins over everything else.
     * @return array{status: string, success: bool, message: string, target_id: string, target_path: string, accounts: list<array<string, mixed>>}
     */
    public function push(string $channel, string $sku, string $text, string $savedId, string $overrideId, bool $dryRun): array
    {
        if ($channel === 'temu') {
            return $this->pushResult('unsupported', self::TEMU_PUSH_UNSUPPORTED, '', $text, []);
        }
        if (! in_array($channel, ['ebay', 'amz'], true)) {
            return $this->pushResult('error', 'Unknown channel.', '', $text, []);
        }

        $text = trim($text);
        if ($text === '' && trim($overrideId) === '') {
            return $this->pushResult('error', 'No category is saved for this SKU on this site.', '', '', []);
        }

        $target = $this->resolveTarget($channel, $text, $savedId, $overrideId);
        if ($target['id'] === '') {
            return $this->pushResult('error', $target['message'], '', $text, []);
        }

        return $channel === 'ebay'
            ? $this->pushEbay($sku, $target, $dryRun)
            : $this->pushAmazon($sku, $target, $dryRun);
    }

    /**
     * Where the numeric target ID comes from, in order: the ID confirmed in the dry-run, the ID saved by
     * Pull, an ID typed into the cell ("Name (12345)" or just "12345"), then (eBay only) eBay's category suggestions.
     *
     * @return array{id: string, path: string, message: string}
     */
    protected function resolveTarget(string $channel, string $text, string $savedId, string $overrideId): array
    {
        foreach ([$overrideId, $savedId, self::idFromText($text)] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && preg_match('/^\d+$/', $candidate)) {
                return ['id' => $candidate, 'path' => $text, 'message' => 'OK'];
            }
        }

        if ($channel === 'ebay') {
            try {
                $suggest = app(Ebay2ApiService::class)->getCategorySuggestions($text);
            } catch (\Throwable $e) {
                $suggest = ['success' => false, 'message' => $e->getMessage()];
            }
            $first = $suggest['categories'][0] ?? null;
            if (is_array($first) && ! empty($first['id'])) {
                return ['id' => (string) $first['id'], 'path' => (string) ($first['path'] ?? $text), 'message' => 'Matched by eBay suggestion'];
            }

            return ['id' => '', 'path' => '', 'message' => 'Could not match "'.$text.'" to an eBay category. Pull it first, or type the ID like "Name (12345)".'];
        }

        return ['id' => '', 'path' => '', 'message' => 'Amazon needs the browse node ID. Pull the category first, or type it like "Name (12345)".'];
    }

    public static function idFromText(string $text): string
    {
        $text = trim($text);
        if (preg_match('/^\d+$/', $text)) {
            return $text;
        }
        if (preg_match('/[(\[](\d+)[)\]]\s*$/', $text, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * @param  array{id: string, path: string, message: string}  $target
     */
    protected function pushEbay(string $sku, array $target, bool $dryRun): array
    {
        $accounts = [];
        foreach (self::EBAY_ACCOUNTS as $label => $cfg) {
            $itemId = $this->listingId($cfg['table'], 'item_id', $sku);
            if ($itemId === null) {
                continue;
            }

            $current = $this->readEbayCategory($cfg['service'], $itemId);
            $row = ['account' => $label, 'item_id' => $itemId, 'current' => (string) ($current['path'] ?? '')];

            if ($current['status'] !== 'ok') {
                $accounts[] = $row + ['status' => 'error', 'message' => 'Could not read the live category: '.$current['message']];

                continue;
            }
            if ($current['id'] === $target['id']) {
                $accounts[] = $row + ['status' => 'unchanged', 'message' => 'Already in this category'];

                continue;
            }
            if ($dryRun) {
                $accounts[] = $row + ['status' => 'ready', 'message' => 'Will move from '.$current['path']];

                continue;
            }

            try {
                $result = app($cfg['service'])->updateCategory($itemId, $target['id']);
            } catch (\Throwable $e) {
                $result = ['success' => false, 'message' => $e->getMessage()];
            }
            $accounts[] = $row + [
                'status' => ($result['success'] ?? false) ? 'pushed' : 'error',
                'message' => (string) ($result['message'] ?? ''),
            ];
        }

        if ($accounts === []) {
            return $this->pushResult('error', 'Not listed on any eBay account.', $target['id'], $target['path'], []);
        }

        return $this->summarizePush($accounts, $target);
    }

    /**
     * @param  array{id: string, path: string, message: string}  $target
     */
    protected function pushAmazon(string $sku, array $target, bool $dryRun): array
    {
        $amazon = app(AmazonSpApiService::class);
        try {
            $current = $amazon->fetchListingCategory($sku);
        } catch (\Throwable $e) {
            $current = ['success' => false, 'message' => $e->getMessage()];
        }

        $row = ['account' => 'Amazon', 'current' => (string) ($current['path'] ?? '')];
        if (! ($current['success'] ?? false)) {
            return $this->summarizePush([$row + ['status' => 'error', 'message' => 'Could not read the live category: '.($current['message'] ?? '')]], $target);
        }
        if ((string) $current['id'] === $target['id']) {
            return $this->summarizePush([$row + ['status' => 'unchanged', 'message' => 'Already in this category']], $target);
        }

        // Dry-run asks Amazon to validate the change without applying it.
        try {
            $result = $amazon->updateBrowseNode($sku, $target['id'], $dryRun);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        if ($result['success'] ?? false) {
            $row += ['status' => $dryRun ? 'ready' : 'pushed', 'message' => $dryRun ? 'Amazon accepts this browse node' : (string) $result['message']];
        } else {
            $row += ['status' => 'error', 'message' => (string) ($result['message'] ?? 'Amazon rejected the change.')];
        }

        return $this->summarizePush([$row], $target);
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     * @param  array{id: string, path: string, message: string}  $target
     */
    protected function summarizePush(array $accounts, array $target): array
    {
        $statuses = array_column($accounts, 'status');
        $status = match (true) {
            in_array('error', $statuses, true) => 'error',
            in_array('pushed', $statuses, true) => 'pushed',
            in_array('ready', $statuses, true) => 'ready',
            default => 'unchanged',
        };

        $message = match ($status) {
            'error' => implode(' | ', array_map(
                static fn ($a) => $a['account'].': '.$a['message'],
                array_values(array_filter($accounts, static fn ($a) => $a['status'] === 'error'))
            )),
            'pushed' => 'Pushed to '.implode(', ', array_column(array_filter($accounts, static fn ($a) => $a['status'] === 'pushed'), 'account')),
            'ready' => 'Will update '.implode(', ', array_column(array_filter($accounts, static fn ($a) => $a['status'] === 'ready'), 'account')),
            default => 'Already up to date.',
        };

        return $this->pushResult($status, $message, $target['id'], $target['path'], $accounts);
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     */
    protected function pushResult(string $status, string $message, string $targetId, string $targetPath, array $accounts): array
    {
        return [
            'status' => $status,
            'success' => in_array($status, ['ready', 'pushed', 'unchanged'], true),
            'message' => $message,
            'target_id' => $targetId,
            'target_path' => $targetPath,
            'accounts' => $accounts,
        ];
    }

    // -------------------------------------------------------------- Helpers

    /**
     * Remote listing ID of a SKU on one account's metrics table.
     */
    protected function listingId(string $table, string $column, string $sku): ?string
    {
        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                return null;
            }
            $key = strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
            $ids = DB::table($table)
                ->whereRaw('UPPER(TRIM(sku)) = ?', [$key])
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->pluck($column);
        } catch (\Throwable $e) {
            Log::warning('Category sync listing lookup failed', ['table' => $table, 'error' => $e->getMessage()]);

            return null;
        }

        foreach ($ids as $id) {
            $id = trim((string) $id);
            // Some sheets store the SKU in the ID column as a placeholder.
            if ($id !== '' && strcasecmp($id, trim($sku)) !== 0) {
                return $id;
            }
        }

        return null;
    }

    public static function normalizePath(string $path, string $separator): string
    {
        $parts = array_values(array_filter(array_map('trim', explode($separator, $path)), static fn ($p) => $p !== ''));

        return implode(' > ', $parts);
    }
}
