<?php

namespace App\Console\Commands;

use App\Models\BadgeData;
use App\Support\Badges\AllMarketplaceMasterBadgeCalculator;
use Illuminate\Console\Command;

class SaveAllMarketplaceMasterBadgeData extends Command
{
    protected $signature = 'badges:save-all-marketplace-master';

    protected $description = 'Snapshot All Marketplace Master badge metrics into badges_data (runs without opening the page).';

    public function handle(): int
    {
        $saved = BadgeData::saveForCalculator(AllMarketplaceMasterBadgeCalculator::class);
        $data = $saved['data'];

        $this->info(sprintf(
            'All Marketplace Master badges saved: channels=%d, sales=$%s, orders=%s, ad_spend=$%s, cvr=%s%%',
            $data['channels'] ?? 0,
            number_format($data['l30_sales'] ?? 0),
            number_format($data['l30_orders'] ?? 0),
            number_format($data['ad_spend'] ?? 0),
            $data['cvr_pct'] ?? '—',
        ));

        return Command::SUCCESS;
    }
}
