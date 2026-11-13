<?php

namespace App\Services\MarketplaceManager\StaleListing;

use App\Models\ReverbListingStatus;
use App\Models\ReverbProduct;
use App\Services\ReverbApiService;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Asks Reverb about every listing id the app calls Listed (reverb_products.reverb_listing_id and the listing id
 * in reverb_listing_statuses.value, the two things ChannelListingRegistry::listedReverb reads) and takes the
 * Listed state away from the ones Reverb no longer has live:
 *  - listing not found (HTTP 404 / 410), or the id belongs to a listing with another SKU: the id is removed
 *    and the state is set to ended;
 *  - listing ended, sold or draft on Reverb: the stored state follows Reverb (ended / sold are hidden by
 *    the Listed rule, drafts already were).
 * Any error, timeout, rate limit, auth failure or unreadable answer keeps the row and counts as an error.
 */
class ReverbStaleListingVerifier implements StaleListingVerifier
{
    private const MAX_IDS = 1500;

    private const MAX_SECONDS = 240;

    private const OFFSET_KEY = 'stale_listing_verify_reverb_offset';

    public function verify(): array
    {
        $out = ['ok' => false, 'message' => '', 'checked' => 0, 'cleared' => 0, 'kept' => 0, 'errors' => 0];

        if (! Schema::hasTable('reverb_products') && ! Schema::hasTable('reverb_listing_statuses')) {
            $out['message'] = 'Reverb: listing tables not found.';

            return $out;
        }
        $api = app(ReverbApiService::class);
        if (! $api->isConfigured()) {
            $out['message'] = 'Reverb: API credentials missing, nothing checked.';

            return $out;
        }

        // listing id => rows that hold it
        $targets = $this->collectTargets();
        if ($targets === []) {
            $out['ok'] = true;
            $out['message'] = 'Reverb: no listed SKU with a listing id to check.';

            return $out;
        }

        $ids = array_keys($targets);
        sort($ids, SORT_STRING);
        $offset = ((int) Cache::get(self::OFFSET_KEY, 0)) % count($ids);
        $ids = array_merge(array_slice($ids, $offset), array_slice($ids, 0, $offset));

        $answers = [];
        $errorSkus = [];
        $consecutiveErrors = 0;
        $aborted = false;
        $processed = 0;
        $started = time();
        foreach ($ids as $id) {
            if ($processed >= self::MAX_IDS || (time() - $started) > self::MAX_SECONDS) {
                break;
            }
            $processed++;
            try {
                $res = $api->lookupListingState((string) $id);
            } catch (\Throwable $e) {
                $res = ['status' => 'error', 'state' => '', 'sku' => '', 'http' => 0, 'message' => $e->getMessage()];
            }

            if ($res['status'] === 'error') {
                foreach ($targets[$id] as $row) {
                    $errorSkus[$row['norm']] = true;
                }
                $consecutiveErrors++;
                Log::warning('ReverbStaleListingVerifier: could not verify listing', ['listing_id' => $id, 'message' => $res['message']]);
                if ($consecutiveErrors >= 5) {
                    $aborted = true;
                    break;
                }

                continue;
            }
            $consecutiveErrors = 0;
            $answers[$id] = $res;
            usleep(150000);
        }
        Cache::forever(self::OFFSET_KEY, ($offset + $processed) % count($ids));

        // Decide per row.
        $decisions = [];
        $checkedSkus = [];
        foreach ($answers as $id => $res) {
            foreach ($targets[$id] as $row) {
                $checkedSkus[$row['norm']] = true;
                $decision = $this->decide($res, $row['sku']);
                if ($decision !== null) {
                    $decisions[] = ['row' => $row, 'id' => $id, 'action' => $decision[0], 'state' => $decision[1], 'reason' => $decision[2]];
                }
            }
        }

        // Reverb answering "not found" for most of the stored listings means the lookup is broken, not that they are gone.
        $gone = count(array_filter($decisions, fn ($d) => $d['action'] === 'gone'));
        $skipGone = $gone >= 20 && $gone > (int) floor(count($checkedSkus) * 0.8);
        if ($skipGone) {
            Log::warning('ReverbStaleListingVerifier: too many listings reported gone, nothing removed', ['gone' => $gone, 'checked' => count($checkedSkus)]);
        }

        $clearedSkus = [];
        foreach ($decisions as $d) {
            if ($d['action'] === 'gone' && $skipGone) {
                $errorSkus[$d['row']['norm']] = true;

                continue;
            }
            try {
                $this->apply($d['row'], $d['action'], $d['state']);
                $clearedSkus[$d['row']['norm']] = true;
                Log::info('ReverbStaleListingVerifier: Reverb listing no longer live', [
                    'sku' => $d['row']['sku'],
                    'listing_id' => $d['id'],
                    'action' => $d['action'],
                    'reason' => $d['reason'],
                ]);
            } catch (\Throwable $e) {
                $errorSkus[$d['row']['norm']] = true;
                Log::warning('ReverbStaleListingVerifier: update failed', ['sku' => $d['row']['sku'], 'listing_id' => $d['id'], 'error' => $e->getMessage()]);
            }
        }
        // A SKU that errored on one of its rows is not counted as cleared.
        foreach (array_keys($errorSkus) as $norm) {
            unset($clearedSkus[$norm]);
        }

        $notReached = max(0, count($ids) - $processed);
        $out['checked'] = count($checkedSkus) + count(array_diff_key($errorSkus, $checkedSkus));
        $out['cleared'] = count($clearedSkus);
        $out['errors'] = count($errorSkus);
        $out['kept'] = max(0, $out['checked'] - $out['cleared'] - $out['errors']);
        $out['ok'] = ! $aborted;
        $out['message'] = "Checked {$out['checked']} listed SKU(s) on Reverb: {$out['cleared']} no longer live".($out['cleared'] > 0 ? ' (now Missing L)' : '')
            .", {$out['kept']} still live, {$out['errors']} could not be verified."
            .($aborted ? ' Five failures in a row (rate limit or credentials); stopped, run again later.' : '')
            .($notReached > 0 && ! $aborted ? " {$notReached} listing(s) are checked on the next run." : '');

        return $out;
    }

    /**
     * Every stored listing id the Listed rule counts, with the rows holding it.
     *
     * @return array<string, list<array{type: string, key: int, sku: string, norm: string}>>
     */
    private function collectTargets(): array
    {
        $targets = [];

        if (Schema::hasTable('reverb_products')) {
            ReverbProduct::query()
                ->whereNotNull('sku')->where('sku', '!=', '')
                ->whereNotNull('reverb_listing_id')->where('reverb_listing_id', '!=', '')
                ->orderBy('id')
                ->get(['id', 'sku', 'reverb_listing_id', 'listing_state'])
                ->each(function ($row) use (&$targets) {
                    $sku = trim((string) $row->sku);
                    if (! $this->isCheckable($sku, $row->listing_state)) {
                        return;
                    }
                    $targets[trim((string) $row->reverb_listing_id)][] = [
                        'type' => 'product', 'key' => (int) $row->id, 'sku' => $sku, 'norm' => ReverbProduct::normalizeSkuForLookup($sku),
                    ];
                });
        }

        if (Schema::hasTable('reverb_listing_statuses')) {
            ReverbListingStatus::query()
                ->whereNotNull('sku')->where('sku', '!=', '')
                ->orderBy('id')
                ->get(['id', 'sku', 'value'])
                ->each(function ($row) use (&$targets) {
                    $value = is_array($row->value) ? $row->value : (json_decode((string) $row->value, true) ?: []);
                    $listingId = trim((string) ($value['listing_id'] ?? ''));
                    $sku = trim((string) $row->sku);
                    if ($listingId === '' || ! $this->isCheckable($sku, $value['state'] ?? null)) {
                        return;
                    }
                    $targets[$listingId][] = [
                        'type' => 'status', 'key' => (int) $row->id, 'sku' => $sku, 'norm' => ReverbProduct::normalizeSkuForLookup($sku),
                    ];
                });
        }

        return $targets;
    }

    /**
     * Parent SKUs and rows the Listed rule already ignores (review / draft / ended / sold) need no lookup.
     */
    private function isCheckable(string $sku, mixed $state): bool
    {
        if ($sku === '' || stripos($sku, 'PARENT') !== false) {
            return false;
        }

        return ! ListingCountsEngine::isPendingOrReviewListingState((string) $state)
            && ! ChannelListingRegistry::isReverbNotLiveState($state);
    }

    /**
     * @param  array{status: string, state: string, sku: string, http: int, message: string}  $res
     * @return array{0: string, 1: string, 2: string}|null  [action, state to store, reason]; null = keep
     */
    private function decide(array $res, string $sku): ?array
    {
        if ($res['status'] === 'not_found') {
            return ['gone', 'ended', $res['message']];
        }

        $state = strtolower(trim($res['state']));
        if (in_array($state, ChannelListingRegistry::REVERB_NOT_LIVE_STATES, true) || $state === 'draft') {
            return ['flip', $state, 'Reverb says the listing is '.$state];
        }

        // Live (or out of stock / suspended / in review): keep unless the listing carries another SKU.
        $apiSku = $this->compactSku($res['sku']);
        if ($apiSku !== '' && $apiSku !== $this->compactSku($sku)) {
            return ['gone', 'ended', 'listing id belongs to SKU '.$res['sku']];
        }

        return null;
    }

    private function compactSku(string $sku): string
    {
        return str_replace(' ', '', ReverbProduct::normalizeSkuForLookup($sku));
    }

    /**
     * @param  array{type: string, key: int, sku: string, norm: string}  $row
     */
    private function apply(array $row, string $action, string $state): void
    {
        if ($row['type'] === 'product') {
            $payload = ['listing_state' => $state];
            if ($action === 'gone') {
                $payload['reverb_listing_id'] = null;
            }
            ReverbProduct::query()->whereKey($row['key'])->update($payload);

            return;
        }

        $status = ReverbListingStatus::query()->find($row['key']);
        if (! $status) {
            return;
        }
        $value = is_array($status->value) ? $status->value : (json_decode((string) $status->value, true) ?: []);
        $value['state'] = $state;
        if ($action === 'gone') {
            $value['listing_id'] = '';
        }
        $status->value = $value;
        $status->save();
    }
}
