<?php

namespace App\Console\Commands;

use App\Services\TemuApiService;
use Illuminate\Console\Command;

class CreateTemuAd extends Command
{
    protected $signature = 'temu:create-ad
                            {--goods-id= : Temu goods ID}
                            {--budget= : Daily budget in USD (e.g. 10)}
                            {--roas= : Target ROAS multiple (e.g. 12)}';

    protected $description = 'Create a Temu search ad via temu.searchrec.ad.create';

    public function handle(TemuApiService $temuApi): int
    {
        $goodsId = trim((string) $this->option('goods-id'));
        $budget = (float) $this->option('budget');
        $roas = (float) $this->option('roas');

        if ($goodsId === '' || $budget < 1 || $roas < 0.1) {
            $this->error('Usage: php artisan temu:create-ad --goods-id=602442267775049 --budget=10 --roas=12');

            return 1;
        }

        $this->info("Creating Temu ad for goods {$goodsId} (budget \${$budget}, ROAS {$roas})...");
        $result = $temuApi->createAd($goodsId, $budget, $roas);

        if (! $result['ok']) {
            $this->error($result['error_msg'] ?? 'Create failed');

            return 1;
        }

        $this->info('Created.');
        $this->line(json_encode($result['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return 0;
    }
}
