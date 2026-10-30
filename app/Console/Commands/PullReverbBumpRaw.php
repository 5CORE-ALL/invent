<?php

namespace App\Console\Commands;

use App\Services\ReverbApiService;
use App\Services\Support\ReverbBumpRawPuller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PullReverbBumpRaw extends Command
{
    protected $signature = 'reverb:pull-bump-raw
        {--limit= : Max listings to pull}';

    protected $description = 'Pull raw GET /listings/{id}/bump for every non-ended Reverb listing into reverb_bump_raw (shown on /reverb-bump)';

    public function handle(): int
    {
        @set_time_limit(0);

        if (! Schema::hasTable('reverb_bump_raw')) {
            $this->error('reverb_bump_raw table missing — run php artisan migrate.');

            return self::FAILURE;
        }

        if (! ReverbApiService::getReverbBearerToken()) {
            $this->error('Reverb API token not configured.');

            return self::FAILURE;
        }

        $query = ReverbBumpRawPuller::pullableProductsQuery()->orderBy('id');
        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        // One pull per listing id (several SKU rows can share a listing).
        $listings = $query->get(['sku', 'reverb_listing_id'])
            ->unique(fn ($p) => trim((string) $p->reverb_listing_id));
        $total = $listings->count();
        $this->info("Pulling raw bump data for {$total} listing(s)...");

        $api = app(ReverbApiService::class);
        $ok = 0;
        $fail = 0;
        $i = 0;
        foreach ($listings as $p) {
            $i++;
            $res = ReverbBumpRawPuller::pullOne($api, trim((string) $p->reverb_listing_id), (string) $p->sku);
            $res['success'] ? $ok++ : $fail++;
            if ($i % 50 === 0) {
                $this->info("  {$i}/{$total} ({$ok} ok, {$fail} failed)...");
            }
            usleep(150000);
        }

        $this->info("Done: {$ok} ok, {$fail} failed.");

        return self::SUCCESS;
    }
}
