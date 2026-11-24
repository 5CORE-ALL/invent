<?php

namespace App\Console\Commands;

use App\Services\SheinApiService;
use Illuminate\Console\Command;

/**
 * Read-only: shows what Shein answers when the app looks up the skuCode of a seller SKU. Run it on the server (Shein only
 * accepts calls from the whitelisted IP) when a mismatch SKU cannot be pushed:
 *   php artisan shein:probe-sku "SC 026"
 */
class ProbeSheinSkuCommand extends Command
{
    protected $signature = 'shein:probe-sku {sku* : Seller SKU(s), e.g. "SC 026"}';

    protected $description = 'Show the saved Shein code and what Shein answers for a seller SKU (read-only)';

    public function handle(SheinApiService $api): int
    {
        foreach ((array) $this->argument('sku') as $sku) {
            $this->line(json_encode($api->probeSku((string) $sku), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
