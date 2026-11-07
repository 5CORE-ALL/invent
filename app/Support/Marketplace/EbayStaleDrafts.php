<?php

namespace App\Support\Marketplace;

use App\Models\ChannelMaster;
use App\Models\ListingManagerChannelDraft;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * A Listing Manager draft marked "listed" keeps its eBay item id even after the listing was
 * ended or deleted on eBay. That blocks Create draft / Publish with "already listed".
 * These helpers move such drafts back to Drafts and clear the old item id.
 */
final class EbayStaleDrafts
{
    public const CHANNEL_KEYS = ['ebay', 'ebay2', 'ebay3'];

    /**
     * Move one "listed" draft back to Drafts (ready when complete, otherwise draft).
     */
    public static function reset(ListingManagerChannelDraft $draft, string $channelName, string $reason): void
    {
        $ready = ListingManagerPublishStatus::readiness(
            $draft->title,
            $draft->price,
            $draft->quantity,
            is_array($draft->listing_details) ? $draft->listing_details : [],
            'draft',
            $channelName
        );
        $oldId = trim((string) $draft->external_listing_id);
        $draft->status = $ready['ready'] ? 'ready' : 'draft';
        $draft->external_listing_id = null;
        $draft->listed_at = null;
        $draft->publish_checked_at = now();
        $draft->notes = trim((string) $draft->notes."\nMoved back to Drafts: ".$reason.($oldId !== '' ? ' (old item id '.$oldId.' cleared)' : ''));
        $draft->save();
    }

    /**
     * Reset every "listed" draft of an eBay store whose SKU is no longer live on eBay.
     *
     * @param  string  $channelKey  ebay | ebay2 | ebay3
     * @param  list<string>  $skus
     */
    public static function releaseForSkus(string $channelKey, array $skus, string $reason): int
    {
        if ($skus === [] || ! Schema::hasTable('listing_manager_channel_drafts') || ! Schema::hasTable('channel_master')) {
            return 0;
        }

        $channelIds = ChannelMaster::query()
            ->whereNotNull('channel')
            ->get(['id', 'channel'])
            ->filter(fn ($c) => ListingChannelCounts::normalize((string) $c->channel) === $channelKey)
            ->pluck('channel', 'id');
        if ($channelIds->isEmpty()) {
            return 0;
        }

        $wanted = [];
        foreach ($skus as $sku) {
            $sku = strtoupper(trim((string) $sku));
            if ($sku !== '') {
                $wanted[$sku] = true;
            }
        }
        if ($wanted === []) {
            return 0;
        }

        $count = 0;
        try {
            ListingManagerChannelDraft::query()
                ->whereIn('channel_id', $channelIds->keys()->all())
                ->where('status', 'listed')
                ->get()
                ->each(function (ListingManagerChannelDraft $draft) use ($wanted, $channelIds, $reason, &$count) {
                    if (! isset($wanted[strtoupper(trim((string) $draft->seller_sku))])) {
                        return;
                    }
                    self::reset($draft, (string) ($channelIds[$draft->channel_id] ?? ''), $reason);
                    $count++;
                });
        } catch (\Throwable $e) {
            Log::warning('EbayStaleDrafts: release failed', ['channel' => $channelKey, 'error' => $e->getMessage()]);
        }

        return $count;
    }
}
