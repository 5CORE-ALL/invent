<?php

namespace App\Console\Commands;

use App\Support\Marketplace\LmpMissingChannelCounts;
use Illuminate\Console\Command;

class SyncLmpMissingCounts extends Command
{
    protected $signature = 'lmp-missing:sync';

    protected $description = 'Rebuild LMP M. counts from inventory and LMP tables. NR channels stay out of the total.';

    public function handle(): int
    {
        $this->info('Syncing LMP M. counts from inventory and LMP data...');
        $rows = LmpMissingChannelCounts::syncRealCounts();
        $counted = LmpMissingChannelCounts::sumCounted($rows);

        $this->table(
            ['Channel', 'LMP M.', 'NR'],
            array_map(static function (array $row): array {
                return [
                    $row['channel'] ?? '',
                    number_format((int) ($row['lmp_missing'] ?? 0)),
                    ! empty($row['nr']) ? 'NR' : '',
                ];
            }, $rows)
        );

        $this->info('LMP M. total (NR excluded): '.number_format($counted));

        return self::SUCCESS;
    }
}
