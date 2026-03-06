<?php

namespace App\Console\Commands;

use App\Services\WayfairApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WayfairTestScopesCommand extends Command
{
    protected $signature = 'wayfair:test-scopes 
                            {--sku= : SKU to use in mutation (default: TEST-SCOPE-SKU)} 
                            {--apply : Use validateOnly: false (default is true to avoid updating)}';

    protected $description = 'Test Wayfair API scopes to find which one grants catalog write (title update) permission';

    protected array $scopesToTest = [
        'write:catalog_items',
        'write:products',
        'write:listings',
        'manage:catalog',
        'product:write',
        'catalog:write',
    ];

    public function handle(): int
    {
        $this->info('Wayfair API scope tester – finding scope that allows updateMarketSpecificCatalogItems');
        $this->newLine();

        if (empty(config('services.wayfair.client_id')) || empty(config('services.wayfair.client_secret'))) {
            $this->error('WAYFAIR_CLIENT_ID and WAYFAIR_CLIENT_SECRET must be set in .env');
            return 1;
        }

        $sku = $this->option('sku') ?: 'TEST-SCOPE-SKU';
        $validateOnly = ! (bool) $this->option('apply');
        $url = config('services.wayfair.product_catalog_graphql_url', 'https://api.wayfair.io/v1/product-catalog-api/graphql');
        $supplierId = (string) config('services.wayfair.supplier_id', '2603');
        $brand = config('services.wayfair.brand', 'WAYFAIR');
        $country = config('services.wayfair.country', 'UNITED_STATES');
        $locale = config('services.wayfair.locale', 'en-US');

        $mutation = <<<'GRAPHQL'
        mutation UpdateMarketSpecificCatalogItems($input: UpdateMarketSpecificCatalogItemsInput!) {
          updateCatalogEntitiesMutations {
            updateMarketSpecificCatalogItems(input: $input) {
              requestId
            }
          }
        }
        GRAPHQL;

        $variables = [
            'input' => [
                'marketContext' => ['locale' => $locale, 'country' => $country, 'brand' => $brand],
                'supplierId' => $supplierId,
                'catalogItemsToUpdate' => [
                    ['supplierPartNumber' => $sku, 'itemName' => 'Test Title Scope Check'],
                ],
                'validateOnly' => $validateOnly,
            ],
        ];

        $service = new WayfairApiService;
        $workingScope = null;

        foreach ($this->scopesToTest as $scope) {
            $this->line("Testing scope: <comment>{$scope}</comment>");
            try {
                $token = $service->getAccessTokenWithScope($scope);
            } catch (\Throwable $e) {
                $this->warn("  Token request failed: " . $e->getMessage());
                Log::info('Wayfair scope test – token failed', ['scope' => $scope, 'error' => $e->getMessage()]);
                continue;
            }

            $response = Http::withoutVerifying()
                ->withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, ['query' => $mutation, 'variables' => $variables]);

            $data = $response->json();
            $errors = $data['errors'] ?? null;
            $requestId = $data['data']['updateCatalogEntitiesMutations']['updateMarketSpecificCatalogItems']['requestId'] ?? null;

            if ($errors) {
                $msg = is_array($errors[0]) ? ($errors[0]['message'] ?? json_encode($errors[0])) : (string) $errors[0];
                $isAccessDenied = (stripos($msg, 'Access Denied') !== false || stripos($msg, 'access_denied') !== false);
                if ($isAccessDenied) {
                    $this->warn("  Access Denied");
                } else {
                    $this->warn("  Error: " . $msg);
                }
                Log::info('Wayfair scope test – mutation failed', ['scope' => $scope, 'errors' => $errors]);
                continue;
            }

            if ($requestId !== null) {
                $this->info("  OK – requestId: {$requestId}");
                Log::info('Wayfair scope test – success', ['scope' => $scope, 'requestId' => $requestId]);
                if ($workingScope === null) {
                    $workingScope = $scope;
                }
            } else {
                $this->warn("  Unexpected response (no requestId)");
                Log::info('Wayfair scope test – no requestId', ['scope' => $scope, 'response' => $data]);
            }
        }

        $this->newLine();
        if ($workingScope !== null) {
            $this->info("Working scope found: <info>{$workingScope}</info>");
            $this->line('Add to .env:');
            $this->line("  WAYFAIR_CATALOG_SCOPE={$workingScope}");
            $this->newLine();
            return 0;
        }

        $this->warn('No scope in the list granted catalog write access. Check Wayfair Partner Portal for the correct scope name and add it to $scopesToTest in this command or set WAYFAIR_CATALOG_SCOPE in .env.');
        return 1;
    }
}
