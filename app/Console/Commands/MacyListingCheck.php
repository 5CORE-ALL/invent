<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\MacyMcmOfferIndex;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Console\Command;

/**
 * Pull every Macy's MCM offer (active + inactive), recount Listed / Missing L
 * from it, and print the SKUs that are really not on Macy's.
 */
class MacyListingCheck extends Command
{
    protected $signature = 'macy:listing-check {--limit=200 : Max missing SKUs to print}';

    protected $description = "Recount Macy's Missing L from the live MCM offer list and print the missing SKUs.";

    public function handle(MacyMcmOfferIndex $offerIndex): int
    {
        $this->info("Reading all Macy's MCM offers (OF21, inactive included)...");
        $index = $offerIndex->refresh();
        if ($index === null) {
            $this->error("Could not read Macy's MCM offers (check MACY_MCM_API_KEY / MACY_SHOP_ID and the log).");

            return self::FAILURE;
        }

        $offers = $index['offers'];
        $active = count(array_filter($offers, static fn ($o) => ! empty($o['active'])));
        $this->line(sprintf('Offers on Macy\'s: %d (active %d, inactive %d)', count($offers), $active, count($offers) - $active));

        $counts = ListingChannelCounts::refreshChannelOnMissingListingPage('macys');
        $this->line(sprintf('REQ %d | NRL %d | Listed %d | Missing L %d', $counts['REQ'], $counts['NRL'], $counts['Listed'], $counts['Pending']));

        $missing = AutomatedListingPage::rows('macys')
            ->filter(fn ($r) => (int) ($r->INV ?? 0) > 0 && ($r->nr_req ?? '') === 'REQ' && ($r->listed ?? '') !== 'Listed')
            ->values();

        $compactOffers = [];
        foreach ($offers as $o) {
            foreach ([(string) $o['shop_sku'], (string) $o['product_sku']] as $s) {
                $c = ListingCountsEngine::skuLookupKeys($s);
                if ($s !== '' && $c !== []) {
                    $compactOffers[end($c)] = $o['shop_sku'];
                }
            }
        }

        $this->line('');
        $this->info('Not on Macy\'s ('.$missing->count().'):');
        foreach ($missing->take((int) $this->option('limit')) as $row) {
            $sku = (string) $row->sku;
            $keys = ListingCountsEngine::skuLookupKeys($sku);
            $mine = (string) end($keys);
            $near = [];
            foreach ($compactOffers as $c => $shopSku) {
                if ($mine !== '' && (str_contains($c, $mine) || str_contains($mine, $c)) && strlen($c) >= 4) {
                    $near[] = $shopSku;
                    if (count($near) >= 3) {
                        break;
                    }
                }
            }
            $this->line(sprintf('  %-32s INV %-5d %s', $sku, (int) $row->INV, $near !== [] ? 'similar on Macy\'s: '.implode(', ', $near) : ''));
        }

        return self::SUCCESS;
    }
}
