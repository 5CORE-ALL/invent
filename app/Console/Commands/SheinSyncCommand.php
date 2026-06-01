<?php

namespace App\Console\Commands;

use App\Services\SheinApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SheinSyncCommand extends Command
{
    protected $signature = 'shein:sync';

    protected $description = 'Fetch SHEIN seller products via Open API and populate shein_metrics';

    public function handle(SheinApiService $shein): int
    {
        if (! Schema::hasTable('shein_metrics')) {
            $this->warn('Table shein_metrics does not exist. Run: php artisan migrate --path=database/migrations/2026_03_22_120000_ensure_shein_metrics_table_exists.php');
            $this->warn('Sync will still call the API, but rows will not be saved until the table exists.');
        }

        $this->info('Starting SHEIN product sync (this may take a while)...');

        $result = $shein->syncAllProductData();

        if (! ($result['success'] ?? false)) {
            $this->error($result['message'] ?? 'Sync failed.');

            return self::FAILURE;
        }

        $this->info($result['message'] ?? 'Done.');
        $this->line('Products in response: '.(string) ($result['total_products'] ?? 0));
        if (($result['db_persisted'] ?? false) === true) {
            $this->line('DB: created '.(string) ($result['db_created'] ?? 0).' / updated '.(string) ($result['db_updated'] ?? 0));
        }

        return self::SUCCESS;
    }
}
