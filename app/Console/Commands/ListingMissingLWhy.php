<?php

namespace App\Console\Commands;

use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Console\Command;

/**
 * Explains why each SKU on a listing page counts as Missing L:
 * what the channel's metrics row says (status, item id) and whether the SKU has a saved link.
 */
class ListingMissingLWhy extends Command
{
    protected $signature = 'listing:missing-l-why {channel=ebaythree} {--list : print every SKU}';

    protected $description = 'Group a channel\'s Missing L SKUs by reason (metrics status / item id / saved link)';

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');
        $channel = (string) $this->argument('channel');
        $cfg = ChannelListingRegistry::get($channel);
        if ($cfg === null) {
            $this->error('Unknown channel: '.$channel);

            return self::FAILURE;
        }

        $canon = fn ($s) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $s));
        $metricModel = (string) (($cfg['listed']['model'] ?? '') ?: '');
        $metrics = [];
        if ($metricModel !== '' && class_exists($metricModel)) {
            $table = (new $metricModel)->getTable();
            $cols = ['sku', 'item_id'];
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'listing_status')) {
                $cols[] = 'listing_status';
            }
            foreach ($metricModel::query()->whereNotNull('sku')->get($cols) as $m) {
                $metrics[$canon($m->sku)][] = $m;
            }
        }

        $groups = [];
        $total = 0;
        foreach (AutomatedListingPage::rows($channel, false, false) as $r) {
            $sku = trim((string) $r->sku);
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }
            if ((float) $r->INV <= 0 || $r->nr_req !== 'REQ' || $r->listed !== 'Pending') {
                continue;
            }
            $total++;

            $rows = $metrics[$canon($sku)] ?? [];
            if ($rows === []) {
                $metric = 'no metrics row';
            } else {
                $parts = [];
                foreach ($rows as $m) {
                    $id = trim((string) $m->item_id);
                    $idKind = $id === '' ? 'id=none' : (strcasecmp($id, $sku) === 0 ? 'id=sku' : 'id=set');
                    $parts[] = 'status='.(trim((string) ($m->listing_status ?? '')) ?: 'null').' '.$idKind;
                }
                $metric = implode(' | ', array_unique($parts));
            }

            $linkId = ListingCountsEngine::listingIdFromLinks($r->buyer_link, $r->seller_link);
            $link = $linkId !== '' ? 'saved link item '.$linkId : 'no saved link';

            $reason = $metric.' ; '.$link;
            $groups[$reason][] = $sku;
        }

        $this->info($channel.': Missing L (in stock, REQ) = '.$total);
        arsort($groups);
        uasort($groups, fn ($a, $b) => count($b) <=> count($a));
        foreach ($groups as $reason => $skus) {
            $preview = $reason;
            if (str_contains($reason, 'saved link item')) {
                $preview = preg_replace('/saved link item \d+/', 'saved link item <id>', $reason);
            }
            $this->line(sprintf('  %4d  %s', count($skus), $preview));
        }
        if ($this->option('list')) {
            $this->line('');
            foreach ($groups as $reason => $skus) {
                foreach ($skus as $sku) {
                    $this->line(sprintf('  %-34s %s', $sku, $reason));
                }
            }
        }

        return self::SUCCESS;
    }
}
