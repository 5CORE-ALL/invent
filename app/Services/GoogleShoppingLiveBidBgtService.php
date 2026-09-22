<?php

namespace App\Services;

use App\Support\GoogleShoppingLiveSyncStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pull Google Ads live bid and live budget on separate queries.
 * A failed pull leaves the last verified amount and green status untouched.
 */
class GoogleShoppingLiveBidBgtService
{
    private const TABLE = 'google_shopping_live_syncs';

    public function __construct(private ?GoogleAdsSbidService $ads = null) {}

    /**
     * @param  list<string>  $campaignIds
     * @return array{ok: bool, error: ?string}
     */
    public function pullAndStore(string $customerId, array $campaignIds, string $field, string $channel = 'shopping'): array
    {
        $field = $field === 'bid' ? 'bid' : 'bgt';
        $ids = self::normalizeIds($campaignIds);
        if ($ids === []) {
            return ['ok' => true, 'error' => null];
        }
        if (! Schema::hasTable(self::TABLE)) {
            return ['ok' => false, 'error' => 'Live bid/budget storage is not available.'];
        }
        if ($customerId === '') {
            $this->persistFetch($channel, $ids, $field, $this->errorMap($ids, 'Google Ads customer ID is not configured.'));

            return ['ok' => false, 'error' => 'Google Ads customer ID is not configured.'];
        }

        try {
            $resolved = $field === 'bid'
                ? $this->fetchBids($customerId, $ids)
                : $this->fetchBudgets($customerId, $ids);
        } catch (Throwable $e) {
            Log::error('Google Shopping live '.$field.' pull failed', [
                'customer_id' => $customerId,
                'campaign_ids' => $ids,
                'error' => $e->getMessage(),
            ]);
            $this->persistFetch($channel, $ids, $field, $this->errorMap($ids, self::shortError($e)));

            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $this->persistFetch($channel, $ids, $field, $resolved);

        return ['ok' => true, 'error' => null];
    }

    /**
     * One background pull for every shopping campaign. Writes a column only when
     * Google Ads returns a single live amount; that amount is then compared with
     * SBID or SBGT. A failed query does not change stored values.
     *
     * @param  array<string, array{sbid?: mixed, sbgt?: mixed}>  $targets
     * @return array{bid_updated: int, bgt_updated: int, error: ?string}
     */
    public function verifyAndStore(string $customerId, array $targets, string $channel = 'shopping'): array
    {
        $stats = ['bid_updated' => 0, 'bgt_updated' => 0, 'error' => null];
        if (! Schema::hasTable(self::TABLE)
            || ! Schema::hasColumn(self::TABLE, 'bid_green')
            || ! Schema::hasColumn(self::TABLE, 'bgt_green')) {
            $stats['error'] = 'Live bid/budget storage is not ready.';

            return $stats;
        }

        $normalized = [];
        foreach ($targets as $id => $row) {
            $cid = self::normalizeIds([(string) $id])[0] ?? '';
            if ($cid === '' || isset($normalized[$cid])) {
                continue;
            }
            $normalized[$cid] = is_array($row) ? $row : [];
        }
        $ids = array_keys($normalized);
        if ($ids === []) {
            return $stats;
        }

        $customerId = trim($customerId);
        if ($customerId === '') {
            Log::warning('Google Shopping live sync skipped: customer ID is not configured.');
            $stats['error'] = 'Google Ads customer ID is not configured.';

            return $stats;
        }

        try {
            $bids = $this->fetchBids($customerId, $ids);
            $stats['bid_updated'] = $this->persistVerified($channel, 'bid', $normalized, $bids);
        } catch (Throwable $e) {
            Log::error('Google Shopping live bid verification failed', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            $stats['error'] = $e->getMessage();
        }

        try {
            $budgets = $this->fetchBudgets($customerId, $ids);
            $stats['bgt_updated'] = $this->persistVerified($channel, 'bgt', $normalized, $budgets);
        } catch (Throwable $e) {
            Log::error('Google Shopping live budget verification failed', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            $stats['error'] = $stats['error'] ?? $e->getMessage();
        }

        return $stats;
    }

    public function recordPush(string $channel, string $campaignId, string $field, bool $ok, ?float $value): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }
        $id = self::normalizeIds([$campaignId])[0] ?? '';
        if ($id === '') {
            return;
        }
        $field = $field === 'bid' ? 'bid' : 'bgt';
        $now = now();
        $pushed = ($ok && $value !== null && $value > 0) ? round($value, $field === 'bid' ? 4 : 2) : null;
        $pushOk = $pushed !== null;

        $row = [
            'channel' => $channel,
            'campaign_id' => $id,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if ($field === 'bid') {
            $row['bid_push_ok'] = $pushOk;
            $row['bid_pushed_value'] = $pushed;
            $row['bid_pushed_at'] = $now;
            $update = ['bid_push_ok', 'bid_pushed_value', 'bid_pushed_at', 'updated_at'];
        } else {
            $row['bgt_push_ok'] = $pushOk;
            $row['bgt_pushed_value'] = $pushed;
            $row['bgt_pushed_at'] = $now;
            $update = ['bgt_push_ok', 'bgt_pushed_value', 'bgt_pushed_at', 'updated_at'];
        }

        DB::table(self::TABLE)->upsert([$row], ['channel', 'campaign_id'], $update);
    }

    /**
     * @param  list<string>  $campaignIds
     * @return array<string, array<string, mixed>>
     */
    public function statesFor(string $channel, array $campaignIds): array
    {
        $ids = self::normalizeIds($campaignIds);
        if ($ids === [] || ! Schema::hasTable(self::TABLE)) {
            return [];
        }

        $out = [];
        foreach (DB::table(self::TABLE)->where('channel', $channel)->whereIn('campaign_id', $ids)->get() as $row) {
            $out[(string) $row->campaign_id] = (array) $row;
        }

        return $out;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array{value: float|null, error: ?string}>
     */
    private function fetchBids(string $customerId, array $ids): array
    {
        $listingAmounts = [];
        foreach (array_chunk($ids, 50) as $chunk) {
            $inList = implode(', ', $chunk);
            $query = "
                SELECT campaign.id, ad_group_criterion.cpc_bid_micros
                FROM ad_group_criterion
                WHERE campaign.id IN ({$inList})
                  AND ad_group_criterion.listing_group.type = 'UNIT'
                  AND ad_group_criterion.negative = FALSE
            ";
            $rows = $this->ads()->runQuery($customerId, $query);
            foreach (GoogleShoppingLiveSyncStatus::positiveDollarsByCampaign($rows, 'cpc') as $cid => $amounts) {
                foreach ($amounts as $amount) {
                    $listingAmounts[$cid][] = $amount;
                }
            }
        }

        $out = [];
        $fallback = [];
        foreach ($ids as $id) {
            $amounts = $listingAmounts[$id] ?? [];
            if ($amounts === []) {
                $fallback[] = $id;

                continue;
            }
            $uniform = GoogleShoppingLiveSyncStatus::uniformPositive($amounts, GoogleShoppingLiveSyncStatus::BID_TOLERANCE);
            $out[$id] = $uniform === null
                ? ['value' => null, 'error' => 'mixed']
                : ['value' => $uniform, 'error' => null];
        }

        if ($fallback === []) {
            return $out;
        }

        try {
            $adGroupAmounts = $this->fetchAdGroupBidAmounts($customerId, $fallback);
        } catch (Throwable $e) {
            Log::warning('Google Shopping live bid ad-group fallback failed', [
                'customer_id' => $customerId,
                'campaign_ids' => $fallback,
                'error' => $e->getMessage(),
            ]);
            foreach ($fallback as $id) {
                $out[$id] = ['value' => null, 'error' => self::shortError($e)];
            }

            return $out;
        }

        foreach ($fallback as $id) {
            $amounts = $adGroupAmounts[$id] ?? [];
            if ($amounts === []) {
                $out[$id] = ['value' => null, 'error' => 'missing'];

                continue;
            }
            $uniform = GoogleShoppingLiveSyncStatus::uniformPositive($amounts, GoogleShoppingLiveSyncStatus::BID_TOLERANCE);
            $out[$id] = $uniform === null
                ? ['value' => null, 'error' => 'mixed']
                : ['value' => $uniform, 'error' => null];
        }

        return $out;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, list<float>>
     */
    private function fetchAdGroupBidAmounts(string $customerId, array $ids): array
    {
        $amounts = [];
        foreach (array_chunk($ids, 50) as $chunk) {
            $inList = implode(', ', $chunk);
            $query = "
                SELECT campaign.id, ad_group.cpc_bid_micros
                FROM ad_group
                WHERE campaign.id IN ({$inList})
                  AND ad_group.status != 'REMOVED'
            ";
            $rows = $this->ads()->runQuery($customerId, $query);
            foreach (GoogleShoppingLiveSyncStatus::positiveDollarsByCampaign($rows, 'cpc') as $cid => $chunkAmounts) {
                foreach ($chunkAmounts as $amount) {
                    $amounts[$cid][] = $amount;
                }
            }
        }

        return $amounts;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array{value: float|null, error: ?string}>
     */
    private function fetchBudgets(string $customerId, array $ids): array
    {
        $amounts = [];
        foreach (array_chunk($ids, 50) as $chunk) {
            $inList = implode(', ', $chunk);
            $query = "
                SELECT campaign.id, campaign_budget.amount_micros
                FROM campaign
                WHERE campaign.id IN ({$inList})
            ";
            $rows = $this->ads()->runQuery($customerId, $query);
            foreach (GoogleShoppingLiveSyncStatus::positiveDollarsByCampaign($rows, 'budget') as $cid => $chunkAmounts) {
                foreach ($chunkAmounts as $amount) {
                    $amounts[$cid][] = $amount;
                }
            }
        }

        $out = [];
        foreach ($ids as $id) {
            $samples = $amounts[$id] ?? [];
            if ($samples === []) {
                $out[$id] = ['value' => null, 'error' => 'missing'];

                continue;
            }
            $uniform = GoogleShoppingLiveSyncStatus::uniformPositive($samples, GoogleShoppingLiveSyncStatus::BGT_TOLERANCE);
            $out[$id] = $uniform === null
                ? ['value' => null, 'error' => 'mixed']
                : ['value' => $uniform, 'error' => null];
        }

        return $out;
    }

    /**
     * @param  array<string, array{sbid?: mixed, sbgt?: mixed}>  $targets
     * @param  array<string, array{value: float|null, error: ?string}>  $resolved
     */
    private function persistVerified(string $channel, string $field, array $targets, array $resolved): int
    {
        $now = now();
        $isBid = $field === 'bid';
        $rows = [];
        foreach ($targets as $id => $target) {
            $entry = $resolved[$id] ?? ['value' => null, 'error' => 'missing'];
            $live = isset($entry['value']) && is_numeric($entry['value']) ? (float) $entry['value'] : null;
            $decision = GoogleShoppingLiveSyncStatus::verifiedStore(
                $field,
                $live,
                isset($entry['error']) ? (string) $entry['error'] : null,
                $isBid ? ($target['sbid'] ?? null) : ($target['sbgt'] ?? null)
            );
            if ($decision === null) {
                continue;
            }
            $row = [
                'channel' => $channel,
                'campaign_id' => $id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($isBid) {
                $row['live_bid'] = $decision['value'];
                $row['bid_fetch_ok'] = true;
                $row['bid_fetch_error'] = null;
                $row['bid_fetched_at'] = $now;
                $row['bid_green'] = $decision['green'] ? 1 : 0;
            } else {
                $row['live_bgt'] = $decision['value'];
                $row['bgt_fetch_ok'] = true;
                $row['bgt_fetch_error'] = null;
                $row['bgt_fetched_at'] = $now;
                $row['bgt_green'] = $decision['green'] ? 1 : 0;
            }
            $rows[] = $row;
        }

        $update = $isBid
            ? ['live_bid', 'bid_fetch_ok', 'bid_fetch_error', 'bid_fetched_at', 'bid_green', 'updated_at']
            : ['live_bgt', 'bgt_fetch_ok', 'bgt_fetch_error', 'bgt_fetched_at', 'bgt_green', 'updated_at'];
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table(self::TABLE)->upsert($chunk, ['channel', 'campaign_id'], $update);
        }

        return count($rows);
    }

    /**
     * @param  list<string>  $ids
     * @param  array<string, array{value: float|null, error: ?string}>  $resolved
     */
    private function persistFetch(string $channel, array $ids, string $field, array $resolved): void
    {
        if (! Schema::hasTable(self::TABLE) || $ids === []) {
            return;
        }
        $now = now();
        $isBid = $field === 'bid';
        $okRows = [];
        $failedRows = [];
        foreach ($ids as $id) {
            $entry = $resolved[$id] ?? ['value' => null, 'error' => 'missing'];
            $value = isset($entry['value']) && is_numeric($entry['value']) ? (float) $entry['value'] : null;
            $error = trim((string) ($entry['error'] ?? ''));
            $fetchOk = $value !== null && $value > 0 && $error === '';
            $row = [
                'channel' => $channel,
                'campaign_id' => $id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($isBid) {
                $row['bid_fetch_ok'] = $fetchOk;
                $row['bid_fetch_error'] = $fetchOk ? null : ($error !== '' ? $error : 'missing');
                $row['bid_fetched_at'] = $now;
                if ($fetchOk) {
                    $row['live_bid'] = round($value, 4);
                }
            } else {
                $row['bgt_fetch_ok'] = $fetchOk;
                $row['bgt_fetch_error'] = $fetchOk ? null : ($error !== '' ? $error : 'missing');
                $row['bgt_fetched_at'] = $now;
                if ($fetchOk) {
                    $row['live_bgt'] = round($value, 2);
                }
            }
            if ($fetchOk) {
                $okRows[] = $row;
            } else {
                $failedRows[] = $row;
            }
        }

        $okUpdate = $isBid
            ? ['live_bid', 'bid_fetch_ok', 'bid_fetch_error', 'bid_fetched_at', 'updated_at']
            : ['live_bgt', 'bgt_fetch_ok', 'bgt_fetch_error', 'bgt_fetched_at', 'updated_at'];
        $failUpdate = $isBid
            ? ['bid_fetch_ok', 'bid_fetch_error', 'bid_fetched_at', 'updated_at']
            : ['bgt_fetch_ok', 'bgt_fetch_error', 'bgt_fetched_at', 'updated_at'];

        foreach (array_chunk($okRows, 100) as $chunk) {
            DB::table(self::TABLE)->upsert($chunk, ['channel', 'campaign_id'], $okUpdate);
        }
        foreach (array_chunk($failedRows, 100) as $chunk) {
            DB::table(self::TABLE)->upsert($chunk, ['channel', 'campaign_id'], $failUpdate);
        }
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array{value: null, error: string}>
     */
    private function errorMap(array $ids, string $error): array
    {
        $short = self::shortErrorMessage($error);
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['value' => null, 'error' => $short];
        }

        return $out;
    }

    private function ads(): GoogleAdsSbidService
    {
        return $this->ads ??= app(GoogleAdsSbidService::class);
    }

    /**
     * @param  list<mixed>  $campaignIds
     * @return list<string>
     */
    private static function normalizeIds(array $campaignIds): array
    {
        $out = [];
        foreach ($campaignIds as $id) {
            if (! is_scalar($id)) {
                continue;
            }
            $digits = preg_replace('/\D+/', '', (string) $id) ?? '';
            if ($digits !== '' && strlen($digits) <= 32) {
                $out[$digits] = true;
            }
        }

        return array_keys($out);
    }

    private static function shortError(Throwable $e): string
    {
        return self::shortErrorMessage($e->getMessage());
    }

    private static function shortErrorMessage(string $message): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');
        if ($message === '') {
            return 'pull_failed';
        }

        return mb_substr($message, 0, 180);
    }
};
