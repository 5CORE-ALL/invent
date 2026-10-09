<?php

namespace App\Support\Marketplace;

use App\Models\ProductMaster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

/**
 * No-API marketplaces: current listings come from an uploaded sheet
 * and are matched to CP Master the same way API channels match catalog IDs.
 */
class SheetListingCatalogService
{
    /** @var list<string> */
    public const SKU_HEADERS = [
        'sku',
        'skus',
        'seller sku',
        'seller_sku',
        'inventory sku',
        'inventory_sku',
        'product sku',
        'product_sku',
        'listing sku',
        'listing_sku',
        'item sku',
        'item_sku',
        'style',
        'style number',
        'style_number',
    ];

    /**
     * @return array{processed: int, listed: int, skipped: int, unmatched: int, message: string}
     */
    public function importUploadedCatalog(string $channel, UploadedFile $file): array
    {
        $slug = ListingChannelCounts::normalize($channel);
        $cfg = SheetListingCatalog::get($slug);
        if ($cfg === null) {
            throw new \InvalidArgumentException('Unknown sheet listing channel: '.$channel);
        }

        $statusClass = $cfg['status'];
        $table = (new $statusClass)->getTable();
        if (! Schema::hasTable($table)) {
            throw new \RuntimeException($table.' is not ready. Run migrations.');
        }

        $path = $file->getRealPath();
        if ($path === false) {
            throw new \RuntimeException('Could not read the uploaded CSV.');
        }

        $rows = $this->parseCsvFile($path);
        $masterByKey = $this->productMasterLookup();
        if ($masterByKey === []) {
            throw new \RuntimeException('CP Master has no SKUs to match this sheet against.');
        }

        $listedCanonical = [];
        $skipped = 0;
        $unmatched = 0;

        foreach ($rows as $row) {
            $rawSku = $this->skuFromRow($row);
            if ($rawSku === '') {
                $skipped++;
                continue;
            }
            if (! $this->rowIsListed($row)) {
                $skipped++;
                continue;
            }

            $canonical = $this->canonicalMasterSku($rawSku, $masterByKey);
            if ($canonical === null) {
                $unmatched++;
                continue;
            }

            $listedCanonical[$canonical] = [
                'sku' => $canonical,
                'listing_id' => $this->cell($row, ['listing_id', 'listing id', 'item_id', 'item id', 'id']) ?: $canonical,
                'buyer_link' => $this->cell($row, ['buyer_link', 'buyer link', 'url', 'listing_url', 'listing url', 'link']),
                'seller_link' => $this->cell($row, ['seller_link', 'seller link']),
            ];
        }

        $this->replaceListedCatalog($statusClass, $listedCanonical, $masterByKey);
        $counts = $this->refreshMissingListing($slug);

        $listed = count($listedCanonical);
        $label = $cfg['label'];

        return [
            'processed' => $listed + $skipped + $unmatched,
            'listed' => $listed,
            'skipped' => $skipped,
            'unmatched' => $unmatched,
            'counts' => $counts,
            'message' => $label.' sheet imported. Matched '.$listed.' CP Master SKUs'
                .($unmatched > 0 ? ', '.$unmatched.' sheet SKUs not in CP Master' : '')
                .'. Listed is now '.number_format((int) ($counts['Listed'] ?? 0))
                .'. Missing L is now '.number_format((int) ($counts['Pending'] ?? 0))
                .' (CP Master REQ with stock that is not Listed).',
        ];
    }

    /**
     * Manual Listed / Pending on the listing page. The SKU is the CP Master SKU.
     *
     * @param  array<string, mixed>  $fields
     * @return array{REQ: int, NRL: int, Listed: int, Pending: int}
     */
    public function saveStatusFields(string $channel, string $sku, array $fields): array
    {
        $slug = ListingChannelCounts::normalize($channel);
        $cfg = SheetListingCatalog::get($slug);
        if ($cfg === null) {
            throw new \InvalidArgumentException('Unknown sheet listing channel: '.$channel);
        }

        $statusClass = $cfg['status'];
        $table = (new $statusClass)->getTable();
        if (! Schema::hasTable($table)) {
            throw new \RuntimeException($table.' is not ready. Run migrations.');
        }

        $sku = trim($sku);
        $exact = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('sku', $sku)
            ->value('sku');
        $canonical = is_string($exact) && trim($exact) !== ''
            ? trim($exact)
            : ($this->canonicalMasterSku($sku, $this->productMasterLookup()) ?? $sku);

        $status = $statusClass::query()->where('sku', $canonical)->first()
            ?: $statusClass::query()->where('sku', $sku)->first();
        $value = $status && is_array($status->value) ? $status->value : [];

        foreach (['nr_req', 'listed', 'buyer_link', 'seller_link'] as $field) {
            if (array_key_exists($field, $fields)) {
                $value[$field] = $fields[$field] ?? '';
            }
        }

        if (array_key_exists('listed', $fields)) {
            $isListed = ListingCountsEngine::statusValueIsListed(['listed' => $fields['listed']]);
            $value['listed'] = $isListed ? 'Listed' : 'Pending';
            unset($value['state'], $value['listing_state']);
            if ($isListed && trim((string) ($value['listing_id'] ?? '')) === '') {
                $value['listing_id'] = $canonical;
            }
        }

        $statusClass::updateOrCreate(['sku' => $canonical], ['value' => $value]);

        return $this->refreshMissingListing($slug);
    }

    /**
     * @param  class-string<Model>  $statusClass
     * @param  array<string, array{sku: string, listing_id: string, buyer_link: string, seller_link: string}>  $listedCanonical
     * @param  array<string, string>  $masterByKey
     */
    private function replaceListedCatalog(string $statusClass, array $listedCanonical, array $masterByKey): void
    {
        $existing = $statusClass::query()->get();
        $seen = [];

        foreach ($existing as $row) {
            $sku = trim((string) $row->sku);
            $canonical = $this->canonicalMasterSku($sku, $masterByKey) ?? $sku;
            $value = is_array($row->value) ? $row->value : [];
            unset($value['state'], $value['listing_state']);
            if (isset($listedCanonical[$canonical])) {
                $incoming = $listedCanonical[$canonical];
                $value['listed'] = 'Listed';
                $value['listing_id'] = $incoming['listing_id'] !== '' ? $incoming['listing_id'] : $canonical;
                if ($incoming['buyer_link'] !== '') {
                    $value['buyer_link'] = $incoming['buyer_link'];
                }
                if ($incoming['seller_link'] !== '') {
                    $value['seller_link'] = $incoming['seller_link'];
                }
                if ($sku !== $canonical) {
                    $taken = $statusClass::query()
                        ->where('sku', $canonical)
                        ->where('id', '!=', $row->id)
                        ->exists();
                    if (! $taken) {
                        $row->sku = $canonical;
                    }
                }
                $row->value = $value;
                $row->save();
                $seen[$canonical] = true;
                continue;
            }

            $value['listed'] = 'Pending';
            $row->value = $value;
            $row->save();
        }

        foreach ($listedCanonical as $sku => $incoming) {
            if (isset($seen[$sku])) {
                continue;
            }
            $statusClass::updateOrCreate(
                ['sku' => $sku],
                ['value' => [
                    'listed' => 'Listed',
                    'listing_id' => $incoming['listing_id'],
                    'buyer_link' => $incoming['buyer_link'] ?: null,
                    'seller_link' => $incoming['seller_link'] ?: null,
                ]]
            );
        }
    }

    /**
     * @return list<array<string, string>>
     */
    public function parseCsvFile(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        if ($lines === []) {
            return [];
        }

        $rows = [];
        foreach ($lines as $line) {
            $rows[] = str_getcsv($line);
        }

        $first = array_map(fn ($h) => $this->normalizeHeader((string) $h), $rows[0] ?? []);
        $hasSkuHeader = $this->headerIndex($first) !== null;

        if ($hasSkuHeader) {
            $header = $first;
            array_shift($rows);
        } else {
            $header = ['sku'];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row) || $row === []) {
                continue;
            }
            if (! $hasSkuHeader) {
                $out[] = ['sku' => trim((string) ($row[0] ?? ''))];
                continue;
            }
            $headerCount = count($header);
            if (count($row) < $headerCount) {
                $row = array_pad($row, $headerCount, '');
            } elseif (count($row) > $headerCount) {
                $row = array_slice($row, 0, $headerCount);
            }
            $combined = array_combine($header, $row);
            if (! is_array($combined)) {
                continue;
            }
            $out[] = array_map(fn ($v) => trim((string) $v), $combined);
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $row
     */
    public function skuFromRow(array $row): string
    {
        foreach (self::SKU_HEADERS as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return trim((string) ($row['sku'] ?? ''));
    }

    /**
     * @param  list<string>  $header
     */
    public function headerIndex(array $header): ?int
    {
        foreach ($header as $i => $name) {
            if (in_array($name, self::SKU_HEADERS, true)) {
                return $i;
            }
        }

        return null;
    }

    public function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = strtolower(trim(str_replace(["\xC2\xA0", '_', '-'], [' ', ' ', ' '], $header)));
        $header = preg_replace('/\s+/', ' ', $header) ?? $header;

        return $header;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function rowIsListed(array $row): bool
    {
        $listed = strtolower(trim((string) ($row['listed'] ?? $row['status'] ?? '')));
        if ($listed === '') {
            return true;
        }

        if (in_array($listed, ['pending', 'no', 'n', 'false', '0', 'nrl', 'nr', 'missing'], true)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $keys
     */
    private function cell(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    private function productMasterLookup(): array
    {
        $map = [];
        if (! Schema::hasTable((new ProductMaster)->getTable())) {
            return $map;
        }

        $skus = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->pluck('sku');

        foreach ($skus as $sku) {
            $canonical = trim((string) $sku);
            if ($canonical === '') {
                continue;
            }
            foreach (ListingCountsEngine::skuLookupKeys($canonical) as $key) {
                if (! isset($map[$key])) {
                    $map[$key] = $canonical;
                }
            }
            foreach (ListingCountsEngine::skuIndexKeys($canonical) as $key) {
                if (! isset($map[$key])) {
                    $map[$key] = $canonical;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $masterByKey
     */
    private function canonicalMasterSku(string $sku, array $masterByKey): ?string
    {
        foreach (array_merge(
            ListingCountsEngine::skuLookupKeys($sku),
            ListingCountsEngine::skuIndexKeys($sku)
        ) as $key) {
            if (isset($masterByKey[$key])) {
                return $masterByKey[$key];
            }
        }

        return null;
    }

    /**
     * @return array{REQ: int, NRL: int, Listed: int, Pending: int}
     */
    private function refreshMissingListing(string $slug): array
    {
        try {
            return ListingChannelCounts::refreshChannelOnMissingListingPage($slug);
        } catch (\Throwable $e) {
            return ListingChannelCounts::forChannel($slug, false);
        }
    }
}
