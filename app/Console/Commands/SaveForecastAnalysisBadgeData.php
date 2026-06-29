<?php

namespace App\Console\Commands;

use App\Models\BadgeData;
use App\Support\Badges\ForecastAnalysisBadgeCalculator;
use Illuminate\Console\Command;

class SaveForecastAnalysisBadgeData extends Command
{
    protected $signature = 'badges:save-forecast-analysis';

    protected $description = 'Snapshot Forecast Analysis badge metrics into badges_data (runs without opening the page).';

    public function handle(): int
    {
        $saved = BadgeData::saveForCalculator(ForecastAnalysisBadgeCalculator::class);
        $data = $saved['data'];

        $this->info(sprintf(
            'Forecast Analysis badges saved: msl_lp=$%s, inv=$%s, mip=$%s, trn=$%s, cbm=%d, zero_stock=%d%%',
            number_format($data['total_msl_c'] ?? 0),
            number_format($data['total_inv_value'] ?? 0),
            number_format($data['total_mip_value'] ?? 0),
            number_format($data['total_transit_value'] ?? 0),
            $data['total_cbm'] ?? 0,
            $data['zero_stock_pct'] ?? 0,
        ));

        return Command::SUCCESS;
    }
}
