<?php

namespace App\Console\Commands;

use App\Services\WayfairApiService;
use Illuminate\Console\Command;

/**
 * TEMPORARY, read-only: shows which listing fields Wayfair's Product Catalog API exposes (title, bullets,
 * description, images, ...) so the Wayfair LQS scorer can be built on real field names. Writes nothing.
 */
class ProbeWayfairCatalog extends Command
{
    protected $signature = 'wayfair:probe-catalog {sku? : supplier part number (default: first Wayfair SKU in wayfair_products)}';

    protected $description = 'Read-only GraphQL introspection of the Wayfair catalog read types (for LQS scorer design).';

    public function handle(WayfairApiService $api): int
    {
        $this->info('1) supplierCatalogItems return type');
        $root = $this->describe($api, 'SupplierCatalogItems');
        $itemType = $this->elementType($root, 'catalogItems');
        $this->line('   catalogItems element type: '.($itemType ?: '?'));

        $item = $itemType ? $this->describe($api, $itemType) : [];
        foreach ($item as $field => $type) {
            $this->line("   - {$field}: {$type}");
        }

        foreach (['listings', 'listing'] as $nested) {
            $t = $this->elementType($item, $nested);
            if ($t) {
                $this->info("2) {$nested} element type: {$t}");
                foreach ($this->describe($api, $t) as $field => $type) {
                    $this->line("   - {$field}: {$type}");
                }
            }
        }

        $sku = trim((string) $this->argument('sku'));
        if ($sku === '') {
            try {
                $sku = (string) (\App\Models\WayfairProduct::query()->whereNotNull('sku')->value('sku') ?? '');
            } catch (\Throwable) {
            }
        }
        if ($sku !== '') {
            $this->info("3) sample item for '{$sku}' (part number + listing ids only)");
            $json = $api->catalogQueryRaw(
                'query ($input: SupplierCatalogItemsInput!) { supplierCatalogItems(input: $input) { ... on SupplierCatalogItems { catalogItems { supplierPartNumber listings { listingId } } } ... on SupplierCatalogItemsError { httpError { code message } internalError { code message } } } }',
                ['input' => ['filter' => ['supplierPartNumbers' => [$sku]], 'paginationOptions' => ['page' => 1, 'pageSize' => 5]]]
            );
            $this->line(mb_substr(json_encode($json), 0, 1500));
        }

        return self::SUCCESS;
    }

    /** @return array<string, string> field => type text */
    private function describe(WayfairApiService $api, string $type): array
    {
        $json = $api->catalogQueryRaw(
            'query ($n: String!) { __type(name: $n) { name kind fields { name type { name kind ofType { name kind ofType { name kind ofType { name kind } } } } } } }',
            ['n' => $type]
        );
        $fields = $json['data']['__type']['fields'] ?? null;
        if (! is_array($fields)) {
            $this->warn("   introspection of {$type} returned nothing: ".mb_substr(json_encode($json['errors'] ?? $json), 0, 300));

            return [];
        }
        $out = [];
        foreach ($fields as $f) {
            $out[$f['name']] = $this->typeText($f['type'] ?? []);
        }

        return $out;
    }

    private function typeText(array $t): string
    {
        $name = $t['name'] ?? null;
        if ($name) {
            return $name;
        }
        $inner = $this->typeText($t['ofType'] ?? []);

        return ($t['kind'] ?? '') === 'LIST' ? '['.$inner.']' : $inner.(($t['kind'] ?? '') === 'NON_NULL' ? '!' : '');
    }

    /** @param  array<string, string>  $fields */
    private function elementType(array $fields, string $field): ?string
    {
        $t = $fields[$field] ?? null;

        return $t ? trim(str_replace(['[', ']', '!'], '', $t)) : null;
    }
}
