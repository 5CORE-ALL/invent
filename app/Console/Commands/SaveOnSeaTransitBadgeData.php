<?php

namespace App\Console\Commands;

use App\Models\BadgeData;
use App\Support\Badges\OnSeaTransitBadgeCalculator;
use Illuminate\Console\Command;

class SaveOnSeaTransitBadgeData extends Command
{
    protected $signature = 'badges:save-on-sea-transit';

    protected $description = 'Snapshot On Sea Transit badge metrics into badges_data (runs without opening the page).';

    public function handle(): int
    {
        $saved = BadgeData::saveForCalculator(OnSeaTransitBadgeCalculator::class);
        $data = $saved['data'];

        $this->info(sprintf(
            'On Sea Transit badges saved: pre_load=%d, on_sea=%d, landed=%d, transit=%d, total_value=$%s, due=$%s, value=$%s',
            $data['pre_load'],
            $data['on_sea'],
            $data['landed'],
            $data['transit'],
            number_format($data['total_value']),
            number_format($data['due']),
            number_format($data['value']),
        ));

        return Command::SUCCESS;
    }
}
