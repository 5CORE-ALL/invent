<?php

namespace App\Console\Commands;

use App\Models\BadgeData;
use App\Support\Badges\BadgeCalculatorRegistry;
use Illuminate\Console\Command;

class SaveAllBadgeData extends Command
{
    protected $signature = 'badges:save-all {--page= : Save only one page (e.g. on-sea-transit)}';

    protected $description = 'Snapshot toolbar badge metrics for every registered page into badges_data.';

    public function handle(): int
    {
        $page = $this->option('page');

        if ($page) {
            $calculatorClass = BadgeCalculatorRegistry::find($page);
            if (! $calculatorClass) {
                $this->error("No badge calculator registered for page \"{$page}\".");

                return Command::FAILURE;
            }

            $saved = BadgeData::saveForCalculator($calculatorClass);
            $this->line($this->formatSavedLine($saved['page_name'], $saved['data']));

            return Command::SUCCESS;
        }

        $savedRows = BadgeData::saveAllRegistered();
        if ($savedRows->isEmpty()) {
            $this->warn('No badge calculators registered.');

            return Command::SUCCESS;
        }

        foreach ($savedRows as $saved) {
            $this->line($this->formatSavedLine($saved['page_name'], $saved['data']));
        }

        $this->info('Saved badge data for '.$savedRows->count().' page(s).');

        return Command::SUCCESS;
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
