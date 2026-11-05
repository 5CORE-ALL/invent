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

    private const SCALARS = ['String', 'Int', 'Float', 'Boolean', 'ID'];

    public function handle(WayfairApiService $api): int
    {
        $this->line('catalog scope configured: '.(trim((string) config('services.wayfair.catalog_scope', '')) !== '' ? 'yes' : 'no').'; supplier id: '.config('services.wayfair.supplier_id'));

        $types = [];
        foreach (['CatalogEntityAttribute', 'CatalogInsights', 'CatalogItemStatus', 'SupplierCatalogItemsInput', 'SupplierCatalogItemsFilter', 'MarketContext'] as $name) {
            $this->info("== {$name}");
            $types[$name] = $this->describe($api, $name);
            foreach ($types[$name] as $field => $type) {
                $this->line("   - {$field}: {$type}");
            }
            // One level deeper for object-typed fields of the insights type (quality hints live there).
            if ($name === 'CatalogInsights') {
                foreach ($types[$name] as $field => $type) {
                    $inner = trim(str_replace(['[', ']', '!'], '', $type));
                    if (! in_array($inner, self::SCALARS, true)) {
                        $this->line("   >> {$inner}");
                        foreach ($this->describe($api, $inner) as $f => $t) {
                            $this->line("      - {$f}: {$t}");
                        }
                    }
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
        if ($sku === '') {
            return self::SUCCESS;
        }

        // Scalar selections only (we do not know nested shapes yet).
        $attrSel = $this->scalarSelection($types['CatalogEntityAttribute'] ?? []);
        $insightSel = $this->scalarSelection($types['CatalogInsights'] ?? []);
        $selection = 'supplierPartNumber url catalogItemStatus { __typename } listings { listingId isLive }'
            .($attrSel !== '' ? " attributes { {$attrSel} }" : '')
            .($insightSel !== '' ? " insights { {$insightSel} }" : '');
        $query = 'query ($input: SupplierCatalogItemsInput!) { supplierCatalogItems(input: $input) { ... on SupplierCatalogItems { catalogItems { '.$selection.' } } ... on SupplierCatalogItemsError { httpError { code message } internalError { code message } } } }';

        $supplier = (int) config('services.wayfair.supplier_id', 2603);
        $variants = [
            'no supplierId' => [],
            'supplierId (string)' => ['supplierId' => (string) $supplier],
            'supplierId (int)' => ['supplierId' => $supplier],
        ];
        foreach ($variants as $label => $extra) {
            $this->info("== sample '{$sku}' with {$label}");
            $json = $api->catalogQueryRaw($query, ['input' => $extra + [
                'filter' => ['supplierPartNumbers' => [$sku]],
                'paginationOptions' => ['page' => 1, 'pageSize' => 3],
            ]]);
            $text = json_encode($json);
            $this->line(mb_substr((string) $text, 0, 3500));
            if (empty($json['errors']) && isset($json['data']['supplierCatalogItems']['catalogItems'])) {
                break;
            }
        }

        return self::SUCCESS;
    }

    /** @return array<string, string> field => type text (fields or input fields) */
    private function describe(WayfairApiService $api, string $type): array
    {
        $typeRef = 'name kind ofType { name kind ofType { name kind ofType { name kind } } }';
        $json = $api->catalogQueryRaw(
            'query ($n: String!) { __type(name: $n) { name kind fields { name type { '.$typeRef.' } } inputFields { name type { '.$typeRef.' } } enumValues { name } } }',
            ['n' => $type]
        );
        $node = $json['data']['__type'] ?? null;
        if (! is_array($node)) {
            $this->warn("   introspection of {$type} returned nothing: ".mb_substr(json_encode($json['errors'] ?? $json), 0, 200));

            return [];
        }
        $out = [];
        foreach (array_merge((array) ($node['fields'] ?? []), (array) ($node['inputFields'] ?? [])) as $f) {
            $out[$f['name']] = $this->typeText($f['type'] ?? []);
        }
        foreach ((array) ($node['enumValues'] ?? []) as $e) {
            $out['(enum) '.$e['name']] = '';
        }

        return $out;
    }

    /** @param  array<string, string>  $fields */
    private function scalarSelection(array $fields): string
    {
        $names = [];
        foreach ($fields as $name => $type) {
            if (str_starts_with($name, '(enum)')) {
                continue;
            }
            if (in_array(trim(str_replace(['[', ']', '!'], '', $type)), self::SCALARS, true)) {
                $names[] = $name;
            }
        }

        return implode(' ', $names);
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
}
