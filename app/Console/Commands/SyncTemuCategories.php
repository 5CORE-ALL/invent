<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\TemuCategoryTree;
use App\Services\Temu2ApiService;
use App\Services\TemuApiService;
use Illuminate\Console\Command;

class SyncTemuCategories extends Command
{
    protected $signature = 'temu:sync-categories {--shop=1 : 1 = Temu 1, 2 = Temu 2} {--fresh : start over} {--id= : print the path of this category id when done}';

    protected $description = 'Download the Temu category tree so category IDs show their names (resumable).';

    public function handle(): int
    {
        $shopKey = $this->option('shop') === '2' ? 'temu2' : 'temu';
        $api = $shopKey === 'temu2' ? app(Temu2ApiService::class) : app(TemuApiService::class);
        if (! $api->isConfigured()) {
            $this->error('Temu credentials are not configured for '.$shopKey.'.');

            return self::FAILURE;
        }

        $tree = new TemuCategoryTree($api, $shopKey);
        $this->info('Downloading the Temu category tree for '.$shopKey.' (this can take several minutes; safe to stop and run again)...');
        $result = $tree->crawl(86400, (bool) $this->option('fresh'), function (int $nodes, int $pending, int $calls) {
            $this->line("  {$nodes} categories, {$pending} parents left, {$calls} calls");
        });

        $this->info(($result['done'] ? 'Done' : 'Stopped').": {$result['nodes']} categories, {$result['pending']} parents left.");
        if ($result['error'] !== '') {
            $this->warn('Last Temu error: '.$result['error']);
        }
        $id = trim((string) $this->option('id'));
        if ($id !== '') {
            $this->line($id.' => '.(TemuCategoryTree::path($shopKey, $id) ?: '(not found)'));
        }

        return $result['done'] ? self::SUCCESS : self::FAILURE;
    }
}
