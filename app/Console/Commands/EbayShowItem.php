<?php

namespace App\Console\Commands;

use App\Support\Marketplace\ListingManagerEbayTradingPublisher;
use Illuminate\Console\Command;

/**
 * Show what eBay holds for an item id: title, owner, status, quantity, link. Read only.
 */
class EbayShowItem extends Command
{
    protected $signature = 'ebay:show-item {item : eBay item id} {--store=2 : eBay store 1, 2 or 3 whose credentials are used}';

    protected $description = 'Show the eBay listing behind an item id (title, owner account, status, quantity, link)';

    public function handle(): int
    {
        $slug = [1 => 'ebay', 2 => 'ebaytwo', 3 => 'ebaythree'][(int) $this->option('store')] ?? 'ebaytwo';
        $res = ListingManagerEbayTradingPublisher::itemInfo($slug, (string) $this->argument('item'));
        if (! ($res['ok'] ?? false)) {
            $this->error((string) ($res['message'] ?? 'Could not read the item.'));

            return 1;
        }
        foreach ($res['fields'] as $label => $value) {
            $this->line(str_pad($label, 30).': '.($value !== '' ? $value : '-'));
        }

        return 0;
    }
}
