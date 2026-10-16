<?php

namespace App\Services;

use App\Support\EbayVolumePricingRule;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Push Buy 2 / Buy 3 / Buy 4 onto eBay 1 as Sell Marketing volume-pricing promotions.
 * Listings that share a discount are one promotion. A listing stays in only one.
 */
class Ebay1VolumePricingService
{
    private const MARKETPLACE = 'EBAY_US';

    private const NAME_PREFIX = '5C VP ';

    private const LISTING_CHUNK = 400;

    public function __construct(private ?EbayApiService $ebay = null)
    {
        $this->ebay ??= app(EbayApiService::class);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  list<array{sku?:string,item_id?:string,weight_lb?:mixed,dil?:mixed,npft?:mixed}>  $rows
     * @return array{success:bool,message:string,pushed:int,groups:int,skipped:int,errors:list<string>,promotion_ids:array<string,string>}
     */
    public function push(array $rules, array $rows, bool $partial = false, bool $skipUnchanged = false): array
    {
        [$bySig, $seen, $skipped, $conflicts] = $this->group($rules, $rows);

        return $this->apply($rules, $bySig, $seen, $skipped, $conflicts, $partial, $skipUnchanged);
    }

    /**
     * Work out each listing's Buy 2/3/4 and group listings that share a discount. Does not call eBay.
     *
     * @param  array<string, mixed>  $rules
     * @param  list<array<string, mixed>>  $rows
     * @return array{0:array<string,array{tiers:list<array{qty:int,percent:float}>,ids:list<string>}>,1:array<string,bool>,2:int,3:int}
     */
    private function group(array $rules, array $rows): array
    {
        $groups = [];
        $seen = [];
        $skipped = 0;
        $conflicts = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                $skipped++;
                continue;
            }
            $itemId = $this->listingId((string) ($row['item_id'] ?? ''));
            if ($itemId === '') {
                $skipped++;
                continue;
            }
            $seen[$itemId] = true;
            $sum = EbayVolumePricingRule::sum(
                is_numeric($row['weight_lb'] ?? null) ? (float) $row['weight_lb'] : null,
                is_numeric($row['dil'] ?? null) ? (float) $row['dil'] : null,
                is_numeric($row['npft'] ?? null) ? (float) $row['npft'] : null,
                $rules
            );
            $tiers = EbayVolumePricingRule::ebayTiers($sum);
            if ($tiers === []) {
                $skipped++;
                continue;
            }
            $sig = EbayVolumePricingRule::signature($tiers);
            if (isset($groups[$itemId]) && $groups[$itemId]['sig'] !== $sig) {
                $conflicts++;
                if ($this->tierScore($tiers) <= $this->tierScore($groups[$itemId]['tiers'])) {
                    continue;
                }
            }
            $groups[$itemId] = ['sig' => $sig, 'tiers' => $tiers];
        }

        $bySig = [];
        foreach ($groups as $itemId => $group) {
            $bySig[$group['sig']]['tiers'] = $group['tiers'];
            $bySig[$group['sig']]['ids'][] = (string) $itemId;
        }

        return [$bySig, $seen, $skipped, $conflicts];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, array{tiers:list<array{qty:int,percent:float}>,ids:list<string>}>  $bySig
     * @param  array<string, bool>  $seen
     * @return array<string, mixed>
     */
    private function apply(array $rules, array $bySig, array $seen, int $skipped, int $conflicts, bool $partial, bool $skipUnchanged): array
    {
        try {
            $token = $this->ebay->generateBearerToken();
        } catch (\Throwable $e) {
            return $this->fail('eBay token: '.$e->getMessage(), $skipped, $rules);
        }

        $known = is_array($rules['promotion_ids'] ?? null) ? $rules['promotion_ids'] : [];
        $state = is_array($rules['promotion_state'] ?? null) ? $rules['promotion_state'] : [];

        // Every promotion this push wants: key => listing ids.
        $planned = [];
        foreach ($bySig as $sig => $group) {
            foreach (array_chunk($group['ids'], self::LISTING_CHUNK) as $index => $ids) {
                $planned[$index === 0 ? $sig : $sig.'#'.($index + 1)] = [
                    'ids' => $ids,
                    'tiers' => $group['tiers'],
                    'index' => $index,
                ];
            }
        }

        $used = [];
        $kept = [];
        $errors = [];
        $pushed = 0;
        $unchanged = 0;
        $seenIds = array_map('strval', array_keys($seen));

        // Step 1 - take listings OUT of the promotions they are leaving. eBay will not put a listing in
        // two volume promotions, so this has to finish before step 2 adds them to the new one.
        foreach ($known as $key => $promoId) {
            $promoId = trim((string) $promoId);
            if ($promoId === '') {
                continue;
            }
            $want = $planned[$key] ?? null;
            if ($want !== null && $skipUnchanged && ($state[$key] ?? '') === $this->idsHash($want['ids'])) {
                continue;
            }
            $detail = $this->getPromotion($token, $promoId);
            if ($detail === null) {
                continue;
            }
            $current = array_map('strval', is_array($detail['inventoryCriterion']['listingIds'] ?? null) ? $detail['inventoryCriterion']['listingIds'] : []);
            $stay = $want !== null ? array_intersect($current, $want['ids']) : [];
            $keep = array_values(array_unique(array_merge(array_diff($current, $seenIds), $stay)));
            if (count($keep) === count($current)) {
                if ($want === null) {
                    $kept[$key] = $promoId;
                }

                continue;
            }
            if ($keep === []) {
                if ($want === null) {
                    $this->pause($token, $promoId);
                }

                continue;
            }
            [$tiers, $index] = $want !== null ? [$want['tiers'], $want['index']] : $this->tiersFromKey($key);
            $result = $this->upsert($token, $promoId, $this->nameFor($tiers, $index), $tiers, $keep);
            if (! $result['success']) {
                $errors[] = $this->nameFor($tiers, $index).': '.$result['message'];
            }
            if ($want === null) {
                $kept[$key] = $promoId;
            }
        }

        // Step 2 - create or update each wanted promotion.
        $newState = [];
        foreach ($planned as $key => $want) {
            $promoId = trim((string) ($known[$key] ?? ''));
            $hash = $this->idsHash($want['ids']);
            if ($promoId !== '' && $skipUnchanged && ($state[$key] ?? '') === $hash) {
                $used[$key] = $promoId;
                $newState[$key] = $hash;
                $unchanged++;

                continue;
            }
            $name = $this->nameFor($want['tiers'], $want['index']);
            $result = $this->upsert($token, $promoId, $name, $want['tiers'], $want['ids'], $partial, $seenIds);
            if (! $result['success']) {
                $errors[] = $name.': '.$result['message'];
                if ($promoId !== '') {
                    $used[$key] = $promoId;
                }

                continue;
            }
            $used[$key] = $result['promotion_id'];
            if (! $partial) {
                $newState[$key] = $hash;
            }
            $pushed += count($want['ids']);
        }
        // A partial push merges listings into a promotion, so its saved fingerprint is no longer exact.
        // Leaving it out makes the next full or auto run re-check that promotion.

        $ok = $errors === [];
        if ($pushed === 0 && $unchanged > 0 && $ok) {
            $message = 'Nothing changed. '.$unchanged.' volume pricing promotion'.($unchanged === 1 ? ' is' : 's are').' already up to date.';
        } elseif ($pushed === 0 && $ok) {
            $message = 'No listing had a Buy 2, Buy 3, or Buy 4 above 0.';
        } else {
            $message = $ok
                ? 'Pushed '.$pushed.' listing'.($pushed === 1 ? '' : 's').' in '.count($used).' volume pricing promotion'.(count($used) === 1 ? '' : 's').'.'
                : 'Pushed '.$pushed.' listing'.($pushed === 1 ? '' : 's').'. '.count($errors).' promotion'.(count($errors) === 1 ? '' : 's').' failed.';
        }
        if ($conflicts > 0) {
            $message .= ' '.$conflicts.' variation'.($conflicts === 1 ? '' : 's').' shared a listing and kept the higher discount.';
        }

        Log::info('eBay1 volume pricing push', [
            'pushed' => $pushed,
            'groups' => count($used),
            'skipped' => $skipped,
            'errors' => $errors,
        ]);

        return [
            'success' => $ok,
            'message' => $message,
            'pushed' => $pushed,
            'groups' => count($used),
            'skipped' => $skipped,
            'errors' => $errors,
            'unchanged' => $unchanged,
            'promotion_ids' => array_merge($kept, $used),
            'promotion_state' => array_merge(array_intersect_key($state, $kept), $newState),
        ];
    }

    /**
     * Saved rules plus the promotion map. Shared by the page and the cron command.
     *
     * @return array<string, mixed>
     */
    public function loadRules(): array
    {
        if (! Schema::hasTable('ebay_sbid_rules')) {
            return EbayVolumePricingRule::defaults();
        }
        $row = DB::table('ebay_sbid_rules')->where('key', EbayVolumePricingRule::KEY)->first();
        $saved = null;
        if ($row && isset($row->rule)) {
            $decoded = is_array($row->rule) ? $row->rule : json_decode((string) $row->rule, true);
            $saved = is_array($decoded) ? $decoded : null;
        }

        return EbayVolumePricingRule::merge($saved);
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function saveRules(array $rules): void
    {
        if (! Schema::hasTable('ebay_sbid_rules')) {
            return;
        }
        $now = now();
        $values = [
            'rule' => json_encode($rules),
            'updated_at' => $now,
        ];
        if (! DB::table('ebay_sbid_rules')->where('key', EbayVolumePricingRule::KEY)->exists()) {
            $values['created_at'] = $now;
        }
        DB::table('ebay_sbid_rules')->updateOrInsert(['key' => EbayVolumePricingRule::KEY], $values);
    }

    /**
     * @param  list<string>  $ids
     */
    private function idsHash(array $ids): string
    {
        $ids = array_map('strval', $ids);
        sort($ids);

        return md5(implode(',', $ids));
    }

    /**
     * Read a saved promotion key such as "2=6|3=15|4=25#2" back into tiers and a chunk index.
     *
     * @return array{0:list<array{qty:int,percent:float}>,1:int}
     */
    private function tiersFromKey(string $key): array
    {
        [$sig, $chunk] = array_pad(explode('#', $key, 2), 2, '1');
        $tiers = [];
        foreach (explode('|', $sig) as $part) {
            [$qty, $pct] = array_pad(explode('=', $part, 2), 2, '0');
            if ((int) $qty > 0 && (float) $pct > 0) {
                $tiers[] = ['qty' => (int) $qty, 'percent' => (float) $pct];
            }
        }

        return [$tiers, max(0, (int) $chunk - 1)];
    }

    /**
     * @param  list<array{qty:int,percent:float}>  $tiers
     * @param  list<string>  $listingIds
     * @return array{success:bool,message:string,promotion_id:string}
     */
    private function upsert(string $token, string $promoId, string $name, array $tiers, array $listingIds, bool $merge = false, array $exclude = []): array
    {
        $detail = $promoId !== '' ? $this->getPromotion($token, $promoId) : null;
        if ($merge && $detail !== null) {
            $current = $detail['inventoryCriterion']['listingIds'] ?? [];
            if (is_array($current)) {
                // Listings in this push are placed by $listingIds, so only the others are carried over.
                $carry = array_diff(array_map('strval', $current), $exclude);
                $listingIds = array_values(array_unique(array_merge($carry, $listingIds)));
            }
        }
        $payload = $this->payload($name, $tiers, $listingIds, $detail);
        if ($detail === null) {
            $resp = $this->http($token)->post('https://api.ebay.com/sell/marketing/v1/item_promotion', $payload);
            if ($resp->status() === 201 || $resp->successful()) {
                $id = $this->promotionId($resp);

                return ['success' => $id !== '', 'message' => $id !== '' ? 'created' : 'eBay did not return a promotion id', 'promotion_id' => $id];
            }

            return ['success' => false, 'message' => $this->errorMessage($resp), 'promotion_id' => ''];
        }

        $status = strtoupper((string) ($detail['promotionStatus'] ?? ''));
        // These promotions belong to the app, so a RUNNING or PAUSED one must end up running again.
        $needsResume = $status === 'PAUSED';
        if ($status === 'RUNNING') {
            $needsResume = $this->pause($token, $promoId);
            $detail = $this->getPromotion($token, $promoId) ?? $detail;
            $payload = $this->payload($name, $tiers, $listingIds, $detail);
        }
        $resp = $this->http($token)->put(
            'https://api.ebay.com/sell/marketing/v1/item_promotion/'.rawurlencode($this->apiId($promoId)),
            $payload
        );
        $resumed = true;
        if ($needsResume) {
            $resumed = $this->resume($token, $promoId);
        }
        if ($resp->successful() || $resp->status() === 204) {
            if (! $resumed) {
                return ['success' => false, 'message' => 'updated but eBay would not resume the promotion (it is paused)', 'promotion_id' => $promoId];
            }

            return ['success' => true, 'message' => 'updated', 'promotion_id' => $promoId];
        }

        return ['success' => false, 'message' => $this->errorMessage($resp), 'promotion_id' => $promoId];
    }

    /**
     * @param  list<array{qty:int,percent:float}>  $tiers
     * @param  list<string>  $listingIds
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    private function payload(string $name, array $tiers, array $listingIds, ?array $existing): array
    {
        // eBay needs minQuantity 1,2,3,(4) in order, ruleOrder equal to minQuantity, and Buy 1 at 0%.
        $rules = [[
            'discountBenefit' => ['percentageOffOrder' => '0'],
            'discountSpecification' => ['minQuantity' => 1],
            'ruleOrder' => 1,
        ]];
        foreach (array_values($tiers) as $tier) {
            $rules[] = [
                'discountBenefit' => ['percentageOffOrder' => $this->percentString((float) $tier['percent'])],
                'discountSpecification' => ['minQuantity' => (int) $tier['qty']],
                'ruleOrder' => (int) $tier['qty'],
            ];
        }
        $start = now('UTC')->addMinutes(2)->format('Y-m-d\TH:i:s.000\Z');
        $end = now('UTC')->addYear()->format('Y-m-d\TH:i:s.000\Z');
        $status = strtoupper((string) ($existing['promotionStatus'] ?? ''));
        if ($existing && in_array($status, ['RUNNING', 'PAUSED', 'SCHEDULED'], true)) {
            $kept = trim((string) ($existing['startDate'] ?? ''));
            if ($kept !== '') {
                $start = $kept;
            }
        }

        return [
            'name' => $name,
            'description' => '5Core volume pricing',
            'startDate' => $start,
            'endDate' => $end,
            'marketplaceId' => self::MARKETPLACE,
            'promotionStatus' => 'SCHEDULED',
            'promotionType' => 'VOLUME_DISCOUNT',
            'inventoryCriterion' => [
                'inventoryCriterionType' => 'INVENTORY_BY_VALUE',
                'listingIds' => array_values($listingIds),
            ],
            'discountRules' => $rules,
        ];
    }

    /**
     * @param  list<array{qty:int,percent:float}>  $tiers
     */
    private function nameFor(array $tiers, int $index): string
    {
        $bits = [];
        foreach ($tiers as $tier) {
            $bits[] = 'B'.$tier['qty'].' '.$this->percentString((float) $tier['percent']);
        }
        $name = self::NAME_PREFIX.implode(' ', $bits);
        if ($index > 0) {
            $name .= ' p'.($index + 1);
        }

        return mb_substr($name, 0, 80);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPromotion(string $token, string $promoId): ?array
    {
        $resp = $this->http($token)->get(
            'https://api.ebay.com/sell/marketing/v1/item_promotion/'.rawurlencode($this->apiId($promoId))
        );
        if (! $resp->successful()) {
            return null;
        }
        $json = $resp->json();

        return is_array($json) ? $json : null;
    }

    private function pause(string $token, string $promoId): bool
    {
        $id = rawurlencode($this->apiId($promoId));
        foreach ([
            'https://api.ebay.com/sell/marketing/v1/item_promotion/'.$id.'/pause',
            'https://api.ebay.com/sell/marketing/v1/promotion/'.$id.'/pause',
        ] as $url) {
            $resp = $this->http($token)->post($url);
            if ($resp->successful() || $resp->status() === 204) {
                return true;
            }
        }

        return false;
    }

    private function resume(string $token, string $promoId): bool
    {
        $id = rawurlencode($this->apiId($promoId));
        foreach ([
            'https://api.ebay.com/sell/marketing/v1/item_promotion/'.$id.'/resume',
            'https://api.ebay.com/sell/marketing/v1/promotion/'.$id.'/resume',
        ] as $url) {
            try {
                $resp = $this->http($token)->post($url);
            } catch (\Throwable $e) {
                continue;
            }
            if ($resp->successful() || $resp->status() === 204) {
                return true;
            }
            Log::warning('eBay1 volume pricing resume failed', ['url' => $url, 'status' => $resp->status(), 'body' => mb_substr((string) $resp->body(), 0, 300)]);
        }
        $current = strtoupper((string) ($this->getPromotion($token, $promoId)['promotionStatus'] ?? ''));

        return $current !== 'PAUSED';
    }

    private function http(string $token): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withoutVerifying()
            ->withToken($token)
            ->asJson()
            ->acceptJson()
            ->withHeaders(['Content-Language' => 'en-US'])
            ->timeout(60);
    }

    private function listingId(string $itemId): string
    {
        $id = trim($itemId);
        if (preg_match('/^v1\|([^|]+)/', $id, $m)) {
            return trim((string) $m[1]);
        }

        return $id;
    }

    private function apiId(string $promoId): string
    {
        $id = trim($promoId);
        if ($id !== '' && ! str_contains($id, '@')) {
            return $id.'@'.self::MARKETPLACE;
        }

        return $id;
    }

    private function promotionId(Response $resp): string
    {
        $loc = (string) ($resp->header('Location') ?? $resp->header('location') ?? '');
        if ($loc !== '' && preg_match('#(?:item_promotion|promotion)/([^/?]+)#', $loc, $m)) {
            return urldecode($m[1]);
        }
        $json = $resp->json();
        if (is_array($json) && ! empty($json['promotionId'])) {
            return (string) $json['promotionId'];
        }

        return '';
    }

    private function errorMessage(Response $resp): string
    {
        $json = $resp->json();
        if (is_array($json) && ! empty($json['errors'][0]['message'])) {
            return (string) $json['errors'][0]['message'];
        }

        $body = trim((string) $resp->body());

        return $body !== '' ? mb_substr($body, 0, 240) : ('HTTP '.$resp->status());
    }

    private function percentString(float $pct): string
    {
        $text = number_format($pct, 1, '.', '');

        return str_ends_with($text, '.0') ? substr($text, 0, -2) : $text;
    }

    /**
     * @param  list<array{qty:int,percent:float}>  $tiers
     */
    private function tierScore(array $tiers): float
    {
        $score = 0.0;
        foreach ($tiers as $tier) {
            $score += (float) $tier['percent'];
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array{success:bool,message:string,pushed:int,groups:int,skipped:int,errors:list<string>,promotion_ids:array<string,string>}
     */
    private function fail(string $message, int $skipped, array $rules): array
    {
        return [
            'success' => false,
            'message' => $message,
            'pushed' => 0,
            'groups' => 0,
            'skipped' => $skipped,
            'errors' => [$message],
            'unchanged' => 0,
            'promotion_ids' => is_array($rules['promotion_ids'] ?? null) ? $rules['promotion_ids'] : [],
            'promotion_state' => is_array($rules['promotion_state'] ?? null) ? $rules['promotion_state'] : [],
        ];
    }

    /**
     * Background run, no page needed. Reads the same eBay 1 rows the page shows, works out Buy 2/3/4 from the
     * saved Weight + Dil + Std NPFT rules, and pushes only promotions whose listings changed.
     *
     * @return array{status:string,message:string,listings:int,pushed:int,groups:int,unchanged:int,skipped:int,errors:list<string>}
     */
    public function autoRun(bool $dryRun = false, bool $force = false, ?callable $log = null): array
    {
        $say = $log ?? static function (string $m): void {};
        $rules = $this->loadRules();
        if (! $force && ! $rules['enabled']) {
            return $this->autoResult('off', 'Volume pricing rules are off. Nothing pushed.');
        }
        if (! $force && ! ($rules['auto_push'] ?? false)) {
            return $this->autoResult('off', 'Auto push is off for volume pricing. Nothing pushed.');
        }

        $rows = [];
        foreach ($this->gridRows() as $d) {
            $row = EbayVolumePricingRule::listingRow($d);
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        $say('Listings with an eBay id: '.count($rows));
        if ($rows === []) {
            return $this->autoResult('empty', 'No eBay 1 listings with an item id were found.');
        }

        if ($dryRun) {
            [$bySig, $seen, $skipped] = $this->group($rules, $rows);
            $state = is_array($rules['promotion_state'] ?? null) ? $rules['promotion_state'] : [];
            $known = is_array($rules['promotion_ids'] ?? null) ? $rules['promotion_ids'] : [];
            $groups = 0;
            $changed = 0;
            $listings = 0;
            foreach ($bySig as $sig => $group) {
                foreach (array_chunk($group['ids'], self::LISTING_CHUNK) as $index => $ids) {
                    $key = $index === 0 ? $sig : $sig.'#'.($index + 1);
                    $groups++;
                    $listings += count($ids);
                    $isSame = ! empty($known[$key]) && ($state[$key] ?? '') === $this->idsHash($ids);
                    if (! $isSame) {
                        $changed++;
                    }
                    $say(sprintf('  %s  %d listings  %s', $this->nameFor($group['tiers'], $index), count($ids), $isSame ? 'unchanged' : 'WOULD PUSH'));
                }
            }
            $res = $this->autoResult('dry-run', sprintf('Dry run: %d promotions wanted, %d would change, %d listings with a discount, %d without.', $groups, $changed, $listings, $skipped));
            $res['listings'] = $listings;
            $res['groups'] = $groups;
            $res['unchanged'] = $groups - $changed;
            $res['skipped'] = $skipped;

            return $res;
        }

        $result = $this->push($rules, $rows, false, ! $force);
        // Keep whatever the page has saved (rules, on/off) and only update the promotion bookkeeping.
        $fresh = $this->loadRules();
        $fresh['promotion_ids'] = $result['promotion_ids'];
        $fresh['promotion_state'] = $result['promotion_state'];
        $this->saveRules($fresh);

        $out = $this->autoResult($result['success'] ? 'ok' : 'failed', $result['message']);
        $out['listings'] = count($rows);
        $out['pushed'] = $result['pushed'];
        $out['groups'] = $result['groups'];
        $out['unchanged'] = $result['unchanged'];
        $out['skipped'] = $result['skipped'];
        $out['errors'] = $result['errors'];

        return $out;
    }

    /**
     * Same rows the eBay 1 pages use, flattened (child rows can sit under a parent).
     *
     * @return list<array<string, mixed>>
     */
    private function gridRows(): array
    {
        $request = new \Illuminate\Http\Request;
        $request->attributes->set('skip_daily_summary', true);
        $level = ob_get_level();
        ob_start();
        try {
            $response = app(\App\Http\Controllers\MarketPlace\EbayController::class)->getViewEbayData($request);
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
        $payload = json_decode((string) $response->getContent(), true);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : (is_array($payload) ? $payload : []);

        $flat = static function (array $rows) use (&$flat): array {
            $out = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $out[] = $row;
                if (is_array($row['_children'] ?? null)) {
                    array_push($out, ...$flat($row['_children']));
                }
            }

            return $out;
        };

        return $flat($data);
    }

    /**
     * @return array{status:string,message:string,listings:int,pushed:int,groups:int,unchanged:int,skipped:int,errors:list<string>}
     */
    private function autoResult(string $status, string $message): array
    {
        return ['status' => $status, 'message' => $message, 'listings' => 0, 'pushed' => 0, 'groups' => 0, 'unchanged' => 0, 'skipped' => 0, 'errors' => []];
    }
}
