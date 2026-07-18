<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\BadgeData;
use App\Services\CronMonitor\CronExecutionContext;
use App\Support\Badges\BadgeCalculatorRegistry;
use Illuminate\Console\Command;

class SaveAllBadgeData extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'badges:save-all
        {--page= : Save only one page (e.g. on-sea-transit)}
        {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Snapshot toolbar badge metrics for every registered page into badges_data.';

    protected string $monitorJobName = 'Save All Badge Data';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeSave($m),
            $this->monitorJobName
        );
    }

    protected function executeSave(CronExecutionContext $monitor): int
    {
        $page = $this->option('page');
        $chunkSize = $this->monitoredChunkSize();

        if ($page) {
            $calculatorClass = BadgeCalculatorRegistry::find($page);
            if (! $calculatorClass) {
                $this->error("No badge calculator registered for page \"{$page}\".");

                return self::FAILURE;
            }

            $monitor->setExpected(1);
            $saved = BadgeData::saveForCalculator($calculatorClass);
            $monitor->setFetched(1);
            $monitor->incrementProcessed(1);
            $monitor->incrementUpdated(1);
            $this->line($this->formatSavedLine($saved['page_name'], $saved['data']));

            return self::SUCCESS;
        }

        $calculators = BadgeCalculatorRegistry::all();
        if ($calculators === []) {
            $this->warn('No badge calculators registered.');
            $monitor->setExpected(0);

            return self::SUCCESS;
        }

        $monitor->setFetched(count($calculators));
        $monitor->setExpected(count($calculators));

        $savedCount = 0;
        foreach (array_chunk($calculators, $chunkSize) as $chunk) {
            foreach ($chunk as $calculatorClass) {
                $saved = BadgeData::saveForCalculator($calculatorClass);
                $this->line($this->formatSavedLine($saved['page_name'], $saved['data']));
                $savedCount++;
            }
            $monitor->incrementProcessed(count($chunk));
            $monitor->incrementUpdated(count($chunk));
        }

        $this->info('Saved badge data for '.$savedCount.' page(s).');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatSavedLine(string $pageName, array $data): string
    {
        $pairs = collect($data)
            ->map(fn ($value, $key) => $key.'='.(is_float($value) ? number_format($value, 2) : $value))
            ->implode(', ');

        return "{$pageName}: {$pairs}";
    }
}
