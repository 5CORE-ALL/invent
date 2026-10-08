<?php

namespace App\Services;

use App\Support\EbayVolumePricingRule;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * @param  list<array{sku?:string,item_id?:string,weight_slab?:string,dil?:mixed,npft?:mixed}>  $rows
     * @return array{success:bool,message:string,pushed:int,groups:int,skipped:int,errors:list<string>,promotion_ids:array<string,string>}
     */
    public function push(array $rules, array $rows): array
    {
        $groups = [];
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
            $sum = EbayVolumePricingRule::sum(
                (string) ($row['weight_slab'] ?? 'lb_0'),
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
            $bySig[$group['sig']]['ids'][] = $itemId;
        }

        try {
            $token = $this->ebay->generateBearerToken();
        } catch (\Throwable $e) {
            return $this->fail('eBay token: '.$e->getMessage(), $skipped, $rules);
        }

        $known = is_array($rules['promotion_ids'] ?? null) ? $rules['promotion_ids'] : [];
        $planned = [];
        foreach ($bySig as $sig => $group) {
            $chunks = array_chunk($group['ids'], self::LISTING_CHUNK);
            foreach ($chunks as $index => $ids) {
                $planned[$index === 0 ? $sig : $sig.'#'.($index + 1)] = true;
            }
        }
        foreach ($known as $key => $promoId) {
            $promoId = trim((string) $promoId);
            if ($promoId === '' || isset($planned[$key])) {
                continue;
            }
            $this->pause($token, $promoId);
        }

        $used = [];
        $errors = [];
        $pushed = 0;

        foreach ($bySig as $sig => $group) {
            $chunks = array_chunk($group['ids'], self::LISTING_CHUNK);
            foreach ($chunks as $index => $ids) {
                $key = $index === 0 ? $sig : $sig.'#'.($index + 1);
                $name = $this->nameFor($group['tiers'], $index);
                $promoId = trim((string) ($known[$key] ?? ''));
                $result = $this->upsert($token, $promoId, $name, $group['tiers'], $ids);
                if (! $result['success']) {
                    $errors[] = $name.': '.$result['message'];
                    if ($promoId !== '') {
                        $used[$key] = $promoId;
                    }
                    continue;
                }
                $used[$key] = $result['promotion_id'];
                $pushed += count($ids);
            }
        }

        $ok = $errors === [];
        if ($pushed === 0 && $ok) {
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
            'promotion_ids' => $used,
        ];
    }

    /**
     * @param  list<array{qty:int,percent:float}>  $tiers
     * @param  list<string>  $listingIds
     * @return array{success:bool,message:string,promotion_id:string}
     */
    private function upsert(string $token, string $promoId, string $name, array $tiers, array $listingIds): array
    {
        $detail = $promoId !== '' ? $this->getPromotion($token, $promoId) : null;
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
        $paused = false;
        if ($status === 'RUNNING') {
            $paused = $this->pause($token, $promoId);
            $detail = $this->getPromotion($token, $promoId) ?? $detail;
            $payload = $this->payload($name, $tiers, $listingIds, $detail);
        }
        $resp = $this->http($token)->put(
            'https://api.ebay.com/sell/marketing/v1/item_promotion/'.rawurlencode($this->apiId($promoId)),
            $payload
        );
        if ($paused) {
            $this->resume($token, $promoId);
        }
        if ($resp->successful() || $resp->status() === 204) {
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
        $rules = [];
        foreach (array_values($tiers) as $i => $tier) {
            $rules[] = [
                'discountBenefit' => ['percentageOffOrder' => $this->percentString((float) $tier['percent'])],
                'discountSpecification' => ['minQuantity' => (int) $tier['qty']],
                'ruleOrder' => $i + 1,
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

    private function resume(string $token, string $promoId): void
    {
        $id = rawurlencode($this->apiId($promoId));
        try {
            $this->http($token)->post('https://api.ebay.com/sell/marketing/v1/item_promotion/'.$id.'/resume');
        } catch (\Throwable $e) {
            // already scheduled
        }
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
            'promotion_ids' => is_array($rules['promotion_ids'] ?? null) ? $rules['promotion_ids'] : [],
        ];
    }
}
