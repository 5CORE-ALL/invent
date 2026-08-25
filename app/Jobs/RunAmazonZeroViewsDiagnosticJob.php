<?php

namespace App\Jobs;

use App\Services\AmazonZeroViewsDiagnosticService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAmazonZeroViewsDiagnosticJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public int $uniqueFor = 1800;

    /**
     * @param  list<string>  $skus
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public array $skus = [],
        public array $options = []
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('amazon'));
    }

    public function uniqueId(): string
    {
        return 'amz-zero-views-diagnostic';
    }

    public function handle(AmazonZeroViewsDiagnosticService $service): void
    {
        $started = now();
        AmazonZeroViewsDiagnosticService::writeStatus([
            'running' => true,
            'status' => 'Running',
            'started_at' => $started->toDateTimeString(),
            'finished_at' => null,
            'message' => 'Queue worker running Amazon 0 Views Diagnostic…',
        ]);

        $skus = $this->skus;
        if ($skus === []) {
            $skus = $service->matchingSkus($this->options['filters'] ?? []);
        }

        $result = $service->runForSkus($skus, $this->options);
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
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RunAmazonZeroViewsDiagnosticJob failed', [
            'error' => $exception->getMessage(),
        ]);
        AmazonZeroViewsDiagnosticService::writeStatus([
            'running' => false,
            'status' => 'Failed',
            'message' => 'Retry Required: '.$exception->getMessage(),
            'finished_at' => now()->toDateTimeString(),
        ]);
    }
}
