<?php

namespace App\Console\Commands;

use App\Services\WayfairApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WayfairCatalogPrice extends Command
{
    protected $signature = 'wayfair:catalog-price
                            {--sku=* : Supplier part numbers to read (default: first --limit SKUs that have a stored price)}
                            {--limit=10 : How many stored-price SKUs to sample when --sku is not given}';

    protected $description = 'Read-only: read Sku.retailPrice from the Wayfair Supplier Catalog API and compare it with the stored price';

    private const URL = 'https://api.wayfair.io/v1/supplier-catalog-api/graphql';

    public function handle(WayfairApiService $wayfair): int
    {
        if (! $wayfair->isConfigured()) {
            $this->error('WAYFAIR_CLIENT_ID and WAYFAIR_CLIENT_SECRET must be set in .env');

            return self::FAILURE;
        }

        try {
            $token = $wayfair->getAccessTokenWithScope('');
        } catch (\Throwable $e) {
            $this->error('Token failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $supplierId = (string) config('services.wayfair.supplier_id', '');
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-SELECTED-SUPPLIER-ID' => $supplierId,
        ];

        $post = function (string $query, array $variables = []) use ($wayfair, $token, $headers): array {
            $payload = ['query' => $query];
            if ($variables !== []) {
                $payload['variables'] = $variables;
            }
            $json = $wayfair->apiHttpClient()->withToken($token)->withHeaders($headers)->post(self::URL, $payload)->json();

            return is_array($json) ? $json : [];
        };

        // 1) What is retailPrice? Scalar, or an object with subfields?
        $typeInfo = $post('{ __type(name: "Sku") { fields { name type { name kind ofType { name kind ofType { name kind } } } } } }');
        $fields = $typeInfo['data']['__type']['fields'] ?? [];
        $priceField = null;
        foreach ((array) $fields as $field) {
            if (($field['name'] ?? '') === 'retailPrice') {
                $priceField = $field;
            }
        }
        if ($priceField === null) {
            $this->error('Sku.retailPrice not found (schema: '.json_encode($typeInfo['errors'] ?? 'no Sku type').').');

            return self::FAILURE;
        }
        $named = $priceField['type'];
        while (empty($named['name']) && ! empty($named['ofType'])) {
            $named = $named['ofType'];
        }
        $typeName = (string) ($named['name'] ?? '');
        $kind = (string) ($named['kind'] ?? '');
        $this->line("Sku.retailPrice type: {$typeName} ({$kind})");

        $selection = 'retailPrice';
        if ($kind === 'OBJECT') {
            $sub = $post('query($n: String!) { __type(name: $n) { fields { name type { name kind ofType { name kind } } } } }', ['n' => $typeName]);
            $names = [];
            foreach ((array) ($sub['data']['__type']['fields'] ?? []) as $f) {
                $leaf = $f['type'];
                while (empty($leaf['name']) && ! empty($leaf['ofType'])) {
                    $leaf = $leaf['ofType'];
                }
                if (($leaf['kind'] ?? '') === 'SCALAR' || ($leaf['kind'] ?? '') === 'ENUM') {
                    $names[] = $f['name'];
                }
            }
            if ($names === []) {
                $this->error("retailPrice is an object ({$typeName}) with no scalar subfields I can select.");

                return self::FAILURE;
            }
            $this->line('  subfields: '.implode(', ', $names));
            $selection = 'retailPrice { '.implode(' ', $names).' }';
        }

        // 2) SKUs to read
        $skus = array_values(array_filter(array_map('trim', (array) $this->option('sku'))));
        $stored = [];
        if ($skus === []) {
            $rows = DB::table('wayfair_pricing_prices')->where('price', '>', 0)->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get(['sku', 'price']);
            foreach ($rows as $r) {
                $skus[] = (string) $r->sku;
            }
        }
        foreach (DB::table('wayfair_pricing_prices')->whereIn('sku', $skus)->get(['sku', 'price']) as $r) {
            $stored[strtoupper(trim((string) $r->sku))] = (float) $r->price;
        }
        if ($skus === []) {
            $this->error('No SKUs to read.');

            return self::FAILURE;
        }

        // 3) Read them
        $query = <<<GRAPHQL
        query (\$supplierId: Int!, \$filter: ProductFilter, \$paginationOptions: PaginationOptions) {
          supplierCatalog(supplierId: \$supplierId, filter: \$filter, paginationOptions: \$paginationOptions) {
            products {
              supplierPartNumber
              status
              skus { sku displaySku isLive status {$selection} }
            }
          }
        }
        GRAPHQL;

        $result = $post($query, [
            'supplierId' => (int) $supplierId,
            'filter' => ['supplierPartNumber' => ['in' => array_slice($skus, 0, 25)]],
            'paginationOptions' => ['page' => 1, 'pageSize' => 25],
        ]);
        if (! empty($result['errors'])) {
            $this->error('Catalog query failed: '.json_encode($result['errors']));

            return self::FAILURE;
        }

        $table = [];
        foreach ((array) ($result['data']['supplierCatalog']['products'] ?? []) as $product) {
            $part = (string) ($product['supplierPartNumber'] ?? '');
            foreach ((array) ($product['skus'] ?? []) as $sku) {
                $retail = $sku['retailPrice'] ?? null;
                $retailText = is_array($retail) ? json_encode($retail) : (string) $retail;
                $s = $stored[strtoupper(trim($part))] ?? null;
                $table[] = [
                    $part,
                    (string) ($sku['sku'] ?? ''),
                    ! empty($sku['isLive']) ? 'live' : (string) ($sku['status'] ?? ''),
                    $retailText,
                    $s !== null ? number_format($s, 2) : '-',
                ];
            }
        }

        if ($table === []) {
            $this->warn('Catalog returned no products for those SKUs.');

            return self::SUCCESS;
        }
        $this->table(['Supplier part', 'Wayfair SKU', 'Status', 'retailPrice', 'Stored price'], $table);
        $this->line('Compare retailPrice with the stored price (and with Partner Home Base Cost) to see which one it matches.');

        return self::SUCCESS;
    }
}
