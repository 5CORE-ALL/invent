<?php

namespace App\Jobs;

use App\Services\MacysRuleSpriceApplyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunMacysRuleSpriceApplyJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    /**
     * @param  list<string>|null  $onlySkus
     */
    public function __construct(public ?array $onlySkus = null) {}

    public function uniqueId(): string
    {
        $keys = array_values(array_unique(array_filter(array_map(
            static fn ($s) => strtoupper(trim((string) $s)),
            $this->onlySkus ?? []
        ))));
        sort($keys);

        return 'macys-rule-sprice-apply:'.($keys === [] ? 'all' : md5(implode(',', $keys)));
    }

    public function handle(MacysRuleSpriceApplyService $service): void
    {
        $summary = $service->run(onlySkus: $this->onlySkus);
        $stats = $summary['stats'] ?? [];
        Log::info('[MacysRuleSpriceApply] job finished', [
            'applied' => $stats['applied'] ?? 0,
            'cleared' => $stats['cleared'] ?? 0,
            'unchanged' => $stats['skipped_unchanged'] ?? 0,
            'skipped' => $stats['skipped'] ?? 0,
        ]);
    }
}
