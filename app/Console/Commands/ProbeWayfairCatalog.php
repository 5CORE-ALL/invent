<?php

namespace App\Console\Commands;

use App\Services\WayfairApiService;
use Illuminate\Console\Command;

/**
 * TEMPORARY, read-only: reads one item through Wayfair's current (V2) Product Catalog API and prints its attributes
 * and Wayfair's own insights, so the Wayfair LQS can be built on real data. Writes nothing.
 *
 * Findings so far: the V1 Supplier Catalog API is deprecated by Wayfair ("migrate to the next-generation V2 APIs"),
 * and V2 answers "Unauthorized supplier access" until the app is granted catalog read for the supplier.
 */
class ProbeWayfairCatalog extends Command
{
    protected $signature = 'wayfair:probe-catalog {sku? : supplier part number (default: first SKU with a stored Wayfair price)}';

    protected $description = 'Read-only probe of the Wayfair V2 Product Catalog read (attributes + insights) for LQS design.';

    public function handle(WayfairApiService $wayfair): int
    {
        $sku = trim((string) $this->argument('sku'));
        if ($sku === '') {
            try {
                $sku = (string) (\Illuminate\Support\Facades\DB::table('wayfair_pricing_prices')->where('price', '>', 0)->orderBy('id')->value('sku') ?? '');
            } catch (\Throwable) {
            }
        }
        if ($sku === '') {
            $this->warn('No SKU to read.');

            return self::SUCCESS;
        }

        $this->line('supplier id: '.config('services.wayfair.supplier_id').'; sku: '.$sku);

        $insight = 'insightId title explanation insightTypeId monthsInViolation';
        $query = 'query ($input: SupplierCatalogItemsInput!) { supplierCatalogItems(input: $input) {'
            .' ... on SupplierCatalogItems { catalogItems { supplierPartNumber url catalogItemStatus class { classId className }'
            .' listings { listingId isLive }'
            ." insights { problems { {$insight} } opportunities { {$insight} } warnings { {$insight} } }"
            .' attributes { attribute { attributeId title description requirement } chosenAttributeValues { value } } } }'
            .' ... on SupplierCatalogItemsError { httpError { code message } internalError { code message } } } }';

        $json = $wayfair->catalogQueryRaw($query, ['input' => [
            'filter' => ['supplierPartNumbers' => [$sku]],
            'paginationOptions' => ['page' => 1, 'pageSize' => 10], // the Wayfair APIs accept 10, 20 or 25
        ]]);

        if (! empty($json['errors'])) {
            $type = (string) ($json['errors'][0]['extensions']['errorType'] ?? $json['errors'][0]['extensions']['classification'] ?? '');
            $this->error('Wayfair refused the read: '.($json['errors'][0]['message'] ?? 'error').($type !== '' ? " ({$type})" : ''));
            if ($type === 'PERMISSION_DENIED') {
                $this->line('The app is not allowed to read this supplier\'s catalog. In the Wayfair partner portal, grant the');
                $this->line('API application catalog READ access for supplier '.config('services.wayfair.supplier_id').' (or correct WAYFAIR_SUPPLIER_ID), then re-run this.');
            }

            return self::FAILURE;
        }

        $this->info('Read OK:');
        $this->line(mb_substr((string) json_encode($json['data'] ?? $json), 0, 9000));

        return self::SUCCESS;
    }
}
