<?php

namespace App\Services\CronMonitor;

use App\Services\CronMonitor\Healers\DatabaseReconnectHealer;
use App\Services\CronMonitor\Healers\HealerInterface;
use App\Services\CronMonitor\Healers\QueueWatchdogHealer;
use Illuminate\Support\Facades\Log;
use Throwable;

class SelfHealingService
{
    /** @var list<HealerInterface> */
    protected array $healers = [];

    public function __construct()
    {
        $this->healers = [
            app(DatabaseReconnectHealer::class),
            app(QueueWatchdogHealer::class),
        ];
    }

    public function register(HealerInterface $healer): void
    {
        $this->healers[] = $healer;
    }

    /**
     * @param  array{category: string, recoverable: bool, root_cause: string, http_status: int|null}  $classification
     */
    public function attempt(CronExecutionContext $context, array $classification, ?Throwable $exception = null): bool
    {
        if (! config('cron-monitor.self_healing.enabled', true)) {
            return false;
        }

        if (! ($classification['recoverable'] ?? false)) {
            return false;
        }

        $healed = false;
        foreach ($this->healers as $healer) {
            if (! $healer->supports($classification, $exception)) {
                continue;
            }

            try {
                if ($healer->heal($context, $classification, $exception)) {
                    $healed = true;
                    Log::info('[CronMonitor] Healer succeeded', [
                        'healer' => $healer::class,
                        'job' => $context->jobName,
                        'category' => $classification['category'] ?? null,
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('[CronMonitor] Healer error: ' . $e->getMessage(), [
                    'healer' => $healer::class,
                ]);
            }
        }

        return $healed;
    }
}
