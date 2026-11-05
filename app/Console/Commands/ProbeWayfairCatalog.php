<?php

namespace App\Console\Commands;

use App\Services\WayfairApiService;
use Illuminate\Console\Command;

/**
 * TEMPORARY, read-only: reads one product through the Wayfair *Supplier Catalog* API (the same endpoint, token and
 * headers wayfair:catalog-price uses for prices) and prints which content fields it exposes, so the Wayfair LQS
 * scorer can be built on real field names. Writes nothing.
 */
class ProbeWayfairCatalog extends Command
{
    protected $signature = 'wayfair:probe-catalog {sku? : supplier part number (default: first SKU with a stored Wayfair price)}';

    protected $description = 'Read-only probe of the Wayfair Supplier Catalog API product/SKU fields (for LQS scorer design).';

    private const URL = 'https://api.wayfair.io/v1/supplier-catalog-api/graphql';

    /** @var array<string, array<string, array{name: string, kind: string}>> */
    private array $cache = [];

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
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'X-SELECTED-SUPPLIER-ID' => $supplierId];
        $post = function (string $query, array $variables = []) use ($wayfair, $token, $headers): array {
            $payload = ['query' => $query];
            if ($variables !== []) {
                $payload['variables'] = $variables;
            }
            $json = $wayfair->apiHttpClient()->withToken($token)->withHeaders($headers)->post(self::URL, $payload)->json();

            return is_array($json) ? $json : [];
        };

        $this->line('supplier id: '.$supplierId);

        // 1) Field list of the product and its SKUs, one level of nested objects.
        foreach (['Product', 'Sku'] as $type) {
            $this->info("== {$type}");
            foreach ($this->fields($post, $type) as $name => $info) {
                $this->line(sprintf('   - %s: %s (%s)', $name, $info['name'], $info['kind']));
                if ($info['kind'] === 'OBJECT' && ! in_array($info['name'], ['Product', 'Sku'], true)) {
                    foreach ($this->fields($post, $info['name']) as $n2 => $i2) {
                        $this->line(sprintf('       . %s: %s (%s)', $n2, $i2['name'], $i2['kind']));
                    }
                }
            }
        }

        // 2) Read one real product with every scalar leaf (two levels deep).
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

        $selection = $this->selection($post, 'Product', 2);
        $this->info("== sample '{$sku}'");
        $this->line('selection: '.mb_substr($selection, 0, 800));
        $json = $post(
            'query ($supplierId: Int!, $filter: ProductFilter, $paginationOptions: PaginationOptions) { supplierCatalog(supplierId: $supplierId, filter: $filter, paginationOptions: $paginationOptions) { products '.$selection.' } }',
            [
                'supplierId' => (int) $supplierId,
                'filter' => ['supplierPartNumber' => ['in' => [$sku]]],
                'paginationOptions' => ['page' => 1, 'pageSize' => 1],
            ]
        );
        $this->line(mb_substr((string) json_encode($json), 0, 8000));

        return self::SUCCESS;
    }

    /** @return array<string, array{name: string, kind: string}> */
    private function fields(callable $post, string $type): array
    {
        if (isset($this->cache[$type])) {
            return $this->cache[$type];
        }
        $ref = 'name kind ofType { name kind ofType { name kind ofType { name kind } } }';
        $res = $post('query($n: String!) { __type(name: $n) { fields { name type { '.$ref.' } } } }', ['n' => $type]);
        $out = [];
        foreach ((array) ($res['data']['__type']['fields'] ?? []) as $f) {
            $leaf = $f['type'];
            while (! empty($leaf['ofType'])) {
                $leaf = $leaf['ofType'];
            }
            $out[(string) $f['name']] = ['name' => (string) ($leaf['name'] ?? ''), 'kind' => (string) ($leaf['kind'] ?? '')];
        }
        if ($out === []) {
            $this->warn("   (no fields returned for {$type}: ".mb_substr(json_encode($res['errors'] ?? $res), 0, 160).')');
        }

        return $this->cache[$type] = $out;
    }

    /** "{ a b c { d e } }" of scalar/enum leaves, descending into objects up to $depth levels. */
    private function selection(callable $post, string $type, int $depth): string
    {
        $parts = [];
        foreach ($this->fields($post, $type) as $name => $info) {
            if (in_array($info['kind'], ['SCALAR', 'ENUM'], true)) {
                $parts[] = $name;
            } elseif ($info['kind'] === 'OBJECT' && $depth > 1 && $info['name'] !== $type) {
                $inner = $this->selection($post, $info['name'], $depth - 1);
                if (! preg_match('/\{\s*\}/', $inner)) {
                    $parts[] = $name.' '.$inner;
                }
            }
        }

        return '{ '.implode(' ', $parts).' }';
    }
}
