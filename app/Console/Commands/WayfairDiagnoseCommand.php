<?php

namespace App\Console\Commands;

use App\Services\WayfairApiService;
use Illuminate\Console\Command;

class WayfairDiagnoseCommand extends Command
{
    protected $signature = 'wayfair:diagnose
                            {--sku= : Supplier part number for the inventory dry run}
                            {--qty= : Quantity for the inventory dry run (dryRun: true, nothing is saved)}';

    protected $description = 'Read-only check of the Wayfair app: token claims, orders, catalog read and an inventory dry run';

    public function handle(WayfairApiService $wayfair): int
    {
        $supplierId = trim((string) config('services.wayfair.supplier_id', ''));
        $this->line('Supplier ID: '.($supplierId !== '' ? $supplierId : '(not set)'));
        if ($supplierId === '' || $supplierId === '2603') {
            $this->error('  2603 is Wayfair\'s test supplier. Set WAYFAIR_SUPPLIER_ID to your real supplier ID.');
        }
        $this->line('Audience: '.(string) config('services.wayfair.audience'));

        try {
            $token = $wayfair->getAccessTokenWithScope('');
        } catch (\Throwable $e) {
            $this->error('Token: FAILED - '.$e->getMessage());

            return 1;
        }
        $this->info('Token: OK');
        $claims = $this->jwtClaims($token);
        foreach (['aud', 'azp', 'client_id', 'scope', 'permissions', 'gty'] as $key) {
            if (array_key_exists($key, $claims)) {
                $value = is_array($claims[$key]) ? implode(', ', $claims[$key]) : (string) $claims[$key];
                $this->line("  {$key}: ".($value !== '' ? $value : '(empty)'));
            }
        }

        $this->check('Orders read (api.wayfair.com)', $wayfair, $token, 'https://api.wayfair.com/v1/graphql',
            'query { purchaseOrders(limit: 1) { poNumber } }', [], false);

        $this->check('Catalog read (product-catalog-api)', $wayfair, $token,
            (string) config('services.wayfair.product_catalog_graphql_url', 'https://api.wayfair.io/v1/product-catalog-api/graphql'),
            'query { supplierCatalogItems(input: { paginationOptions: { page: 1, pageSize: 1 } }) { ... on SupplierCatalogItems { paginationInfo { totalCount } } ... on SupplierCatalogItemsError { httpError { code message } internalError { code message } } } }',
            [], true);

        $sku = trim((string) $this->option('sku'));
        $qty = $this->option('qty');
        if ($sku === '' || $qty === null || ! is_numeric($qty)) {
            $this->line('Inventory dry run: skipped (pass --sku= and --qty= to test it).');

            return 0;
        }

        $row = ['supplierPartNumber' => $sku, 'quantityOnHand' => max(0, (int) $qty)];
        if (ctype_digit($supplierId)) {
            $row['supplierId'] = (int) $supplierId;
        }
        $this->check('Inventory dry run (inventory.save, dryRun: true)', $wayfair, $token, 'https://api.wayfair.com/v1/graphql',
            'mutation ($inventory: [inventoryInput]!) { inventory { save(inventory: $inventory, feedKind: DIFFERENTIAL, dryRun: true) { handle status errors { key message } } } }',
            ['inventory' => [$row]], false);

        return 0;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function check(string $label, WayfairApiService $wayfair, string $token, string $url, string $query, array $variables, bool $supplierHeader): void
    {
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        if ($supplierHeader) {
            $headers['X-SELECTED-SUPPLIER-ID'] = (string) config('services.wayfair.supplier_id', '');
        }

        try {
            $response = $wayfair->apiHttpClient()->withToken($token)->withHeaders($headers)
                ->post($url, ['query' => $query, 'variables' => $variables]);
        } catch (\Throwable $e) {
            $this->error("{$label}: FAILED - ".$e->getMessage());

            return;
        }

        $json = $response->json();
        $errors = is_array($json) ? ($json['errors'] ?? []) : [];
        if (! $response->successful() || $errors !== [] || ! is_array($json)) {
            $messages = [];
            foreach (is_array($errors) ? $errors : [] as $error) {
                $messages[] = is_array($error) ? (string) ($error['message'] ?? json_encode($error)) : (string) $error;
            }
            $detail = $messages !== [] ? implode('; ', $messages) : 'HTTP '.$response->status().' '.mb_substr($response->body(), 0, 300);
            $this->error("{$label}: FAILED - {$detail}");

            return;
        }

        $this->info("{$label}: OK - ".mb_substr((string) json_encode($json['data'] ?? []), 0, 300));
    }

    /**
     * @return array<string, mixed>
     */
    private function jwtClaims(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) < 2) {
            return [];
        }
        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $claims = is_string($payload) ? json_decode($payload, true) : null;

        return is_array($claims) ? $claims : [];
    }
}
