<?php

namespace App\Console\Commands;

use App\Services\WayfairApiService;
use Illuminate\Console\Command;

class WayfairFindPriceFields extends Command
{
    protected $signature = 'wayfair:find-price-fields
                            {--pattern= : Regex (case-insensitive) for field names to list. Default: price, cost, wholesale, msrp, map}';

    protected $description = 'Read-only: introspect the Wayfair GraphQL APIs and list any price / cost fields this app can read';

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
        $endpoints = [
            'orders / main API' => ['https://api.wayfair.com/v1/graphql', false],
            'product catalog API' => [(string) config('services.wayfair.product_catalog_graphql_url', 'https://api.wayfair.io/v1/product-catalog-api/graphql'), true],
            'supplier catalog API' => ['https://api.wayfair.io/v1/supplier-catalog-api/graphql', true],
        ];

        $raw = trim((string) $this->option('pattern'));
        $pattern = '/'.str_replace('/', '\/', $raw !== '' ? $raw : 'price|cost|wholesale|msrp|map').'/i';
        $query = '{ __schema { types { name kind fields { name } inputFields { name } } } }';
        $found = false;

        foreach ($endpoints as $label => [$url, $needsSupplier]) {
            $this->line('');
            $this->info("== {$label}: {$url}");

            $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
            if ($needsSupplier) {
                $headers['X-SELECTED-SUPPLIER-ID'] = $supplierId;
            }

            try {
                $response = $wayfair->apiHttpClient()->withToken($token)->withHeaders($headers)->post($url, ['query' => $query]);
            } catch (\Throwable $e) {
                $this->warn('  request failed: '.$e->getMessage());
                continue;
            }

            $json = $response->json();
            $types = $json['data']['__schema']['types'] ?? null;
            if (! is_array($types)) {
                $msg = is_array($json['errors'] ?? null) ? ($json['errors'][0]['message'] ?? 'GraphQL error') : 'HTTP '.$response->status().' '.mb_substr($response->body(), 0, 200);
                $this->warn('  schema not readable (introspection may be disabled): '.$msg);
                continue;
            }

            $rows = [];
            foreach ($types as $type) {
                $name = (string) ($type['name'] ?? '');
                if ($name === '' || str_starts_with($name, '__')) {
                    continue;
                }
                $fields = array_merge(
                    array_column((array) ($type['fields'] ?? []), 'name'),
                    array_column((array) ($type['inputFields'] ?? []), 'name')
                );
                foreach ($fields as $field) {
                    if (preg_match($pattern, (string) $field)) {
                        $rows[] = [$name, (string) $type['kind'], $field];
                    }
                }
            }

            if ($rows === []) {
                $this->line('  no matching fields.');
                continue;
            }
            $found = true;
            $this->table(['Type', 'Kind', 'Field'], $rows);
        }

        $this->line('');
        $this->line($found
            ? 'Fields above are candidates for a real price read. Send this output back.'
            : 'No price/cost field is readable with this API app, so a PO-independent price pull is not possible from the API.');

        return self::SUCCESS;
    }
}
