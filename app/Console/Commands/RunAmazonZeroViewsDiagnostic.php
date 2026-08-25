<?php

namespace App\Console\Commands;

use App\Services\AmazonZeroViewsDiagnosticService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunAmazonZeroViewsDiagnostic extends Command
{
    protected $signature = 'amazon:run-zero-views-diagnostic
        {--sku= : Single SKU}
        {--asin= : Single ASIN}
        {--skus-file= : File with one SKU per line}
        {--payload-file= : JSON payload with skus/filters}
        {--zero-only : Limit to products with 0 L30 views}
        {--all : Diagnose all product-master SKUs}';

    protected $description = 'Evaluate Amazon 0-views diagnostics from already-synced local Amazon data';

    public function handle(AmazonZeroViewsDiagnosticService $service): int
    {
        $lock = Cache::lock(AmazonZeroViewsDiagnosticService::CACHE_LOCK_KEY, 1800);
        if (! $lock->get()) {
            $this->warn('A diagnostic run is already in progress.');

            return self::SUCCESS;
        }

        $started = now();
        AmazonZeroViewsDiagnosticService::writeStatus([
            'running' => true,
            'status' => 'Running',
            'started_at' => $started->toDateTimeString(),
            'finished_at' => null,
            'done' => 0,
            'ok' => 0,
            'fail' => 0,
            'message' => 'Starting Amazon 0 Views Diagnostic…',
        ]);

        try {
            $skus = $this->resolveSkus($service);
            AmazonZeroViewsDiagnosticService::writeStatus([
                'running' => true,
                'status' => 'Running',
                'total' => count($skus),
                'message' => 'Diagnosing '.count($skus).' SKU(s) from synced Amazon data…',
            ]);

            $result = $service->runForSkus($skus);
            $status = ($result['fail'] ?? 0) > 0 && ($result['ok'] ?? 0) === 0 ? 'Failed' : 'Completed';

            AmazonZeroViewsDiagnosticService::writeStatus([
                'running' => false,
                'status' => $status,
                'total' => $result['total'] ?? count($skus),
                'done' => $result['total'] ?? count($skus),
                'ok' => $result['ok'] ?? 0,
                'fail' => $result['fail'] ?? 0,
                'finished_at' => now()->toDateTimeString(),
                'message' => $status.' · '.($result['ok'] ?? 0).' ok · '.($result['fail'] ?? 0).' failed',
            ]);

            $this->info('Diagnostic '.$status.': '.json_encode($result));

            return ($result['fail'] ?? 0) > 0 && ($result['ok'] ?? 0) === 0
                ? self::FAILURE
                : self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('amazon:run-zero-views-diagnostic failed', [
                'error' => $e->getMessage(),
            ]);
            AmazonZeroViewsDiagnosticService::writeStatus([
                'running' => false,
                'status' => 'Failed',
                'finished_at' => now()->toDateTimeString(),
                'message' => 'Retry Required: '.$e->getMessage(),
            ]);
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @return list<string>
     */
    private function resolveSkus(AmazonZeroViewsDiagnosticService $service): array
    {
        $payload = [];
        $payloadFile = (string) $this->option('payload-file');
        if ($payloadFile !== '' && is_file($payloadFile)) {
            $decoded = json_decode((string) file_get_contents($payloadFile), true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        $skus = [];
        if (! empty($payload['skus']) && is_array($payload['skus'])) {
            $skus = $payload['skus'];
        }

        $sku = trim((string) ($this->option('sku') ?: ($payload['sku'] ?? '')));
        if ($sku !== '') {
            $skus[] = $sku;
        }

        $skusFile = (string) $this->option('skus-file');
        if ($skusFile !== '' && is_file($skusFile)) {
            foreach (preg_split('/\R/', (string) file_get_contents($skusFile)) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $skus[] = $line;
                }
            }
        }

        $asin = trim((string) ($this->option('asin') ?: ($payload['asin'] ?? '')));
        if ($asin !== '' && $skus === []) {
            $detail = $service->detail($asin);
            if (! empty($detail['sku'])) {
                $skus[] = $detail['sku'];
            }
        }

        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ))));

        if ($skus !== []) {
            return $skus;
        }

        $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
        if ($this->option('zero-only') || ($payload['zero_only'] ?? null) === true) {
            $filters['zero_only'] = 1;
            $filters['l30_views'] = '0';
        }
        if ($this->option('all') || ($payload['all'] ?? false)) {
            $filters['zero_only'] = 0;
            $filters['l30_views'] = 'all';
        }

        return $service->matchingSkus($filters);
    }
}
