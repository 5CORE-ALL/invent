<?php

namespace App\Support;

use App\Models\AmazonSbCampaignReport;
use App\Models\AmazonSpCampaignReport;
use App\Services\AmazonAdsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Reporting APIs omit ENABLED campaigns with no impressions. Amazon's campaign
 * list still returns them — persist zero-metric L30 + daily rows so /amazon-ads/all
 * matches the Amazon console campaign count.
 */
class AmazonAdsEnabledCampaignSync
{
    public function __construct(private AmazonAdsService $ads) {}

    /**
     * @return array{created: int, skipped: int}
     */
    public function syncSp(string $profileId, string $dayYmd): array
    {
        return $this->sync(
            $this->ads->listAllSpCampaigns(['ENABLED']),
            AmazonSpCampaignReport::class,
            $profileId,
            'SPONSORED_PRODUCTS',
            $dayYmd
        );
    }

    /**
     * @return array{created: int, skipped: int}
     */
    public function syncSb(string $profileId, string $dayYmd): array
    {
        return $this->sync(
            $this->ads->listAllSbCampaigns(['ENABLED']),
            AmazonSbCampaignReport::class,
            $profileId,
            'SPONSORED_BRANDS',
            $dayYmd
        );
    }

    /**
     * @param  list<array<string, mixed>>  $campaigns
     * @param  class-string  $modelClass
     * @return array{created: int, skipped: int}
     */
    private function sync(array $campaigns, string $modelClass, string $profileId, string $adType, string $dayYmd): array
    {
        $created = 0;
        $skipped = 0;
        $l30Window = self::l30WindowEnding($dayYmd);

        foreach ($campaigns as $campaign) {
            $cid = trim((string) ($campaign['campaignId'] ?? $campaign['campaign_id'] ?? ''));
            if ($cid === '') {
                $skipped++;
                continue;
            }

            $payload = self::filterToColumns(
                self::zeroMetricPayload($campaign, $profileId, $cid, $adType, $l30Window),
                Schema::getColumnListing((new $modelClass)->getTable())
            );
            $wrote = false;
            foreach ([$dayYmd, 'L30', 'L1'] as $range) {
                $existing = $modelClass::query()
                    ->where('profile_id', $profileId)
                    ->where('campaign_id', $cid)
                    ->where('report_date_range', $range)
                    ->exists();
                if ($existing) {
                    continue;
                }
                $modelClass::create(array_merge($payload, [
                    'report_date_range' => $range,
                ]));
                $wrote = true;
            }
            if ($wrote) {
                $created++;
            } else {
                $skipped++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @param  array<string, mixed>  $campaign
     * @param  array{start: string, end: string}  $l30Window
     * @return array<string, mixed>
     */
    public static function zeroMetricPayload(array $campaign, string $profileId, string $campaignId, string $adType, array $l30Window): array
    {
        $name = trim((string) ($campaign['name'] ?? $campaign['campaignName'] ?? ''));

        return [
            'profile_id' => $profileId,
            'campaign_id' => $campaignId,
            'campaignName' => $name !== '' ? $name : null,
            'ad_type' => $adType,
            'campaignStatus' => 'ENABLED',
            'campaignBudgetAmount' => self::budgetAmount($campaign),
            'campaignBudgetCurrencyCode' => self::budgetCurrency($campaign),
            'startDate' => $l30Window['start'],
            'endDate' => $l30Window['end'],
            'impressions' => 0,
            'clicks' => 0,
            'cost' => 0,
            'spend' => 0,
            'costPerClick' => 0,
        ];
    }

    /**
     * SB reports have `cost` but no `spend`; drop keys the table does not have.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    public static function filterToColumns(array $payload, array $columns): array
    {
        $cols = array_flip($columns);
        $out = [];
        foreach ($payload as $key => $value) {
            if (isset($cols[$key])) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $campaign
     */
    public static function budgetAmount(array $campaign): ?float
    {
        $budget = $campaign['budget'] ?? null;
        if (is_array($budget)) {
            foreach (['budget', 'budgetAmount', 'amount'] as $k) {
                if (isset($budget[$k]) && is_numeric($budget[$k])) {
                    return (float) $budget[$k];
                }
            }

            return null;
        }
        if (is_numeric($budget)) {
            return (float) $budget;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $campaign
     */
    public static function budgetCurrency(array $campaign): ?string
    {
        $budget = $campaign['budget'] ?? null;
        if (! is_array($budget)) {
            return isset($campaign['currencyCode']) ? (string) $campaign['currencyCode'] : null;
        }
        foreach (['currencyCode', 'campaignBudgetCurrencyCode'] as $k) {
            if (! empty($budget[$k])) {
                return (string) $budget[$k];
            }
        }

        return isset($campaign['currencyCode']) ? (string) $campaign['currencyCode'] : null;
    }

    /**
     * @return array{start: string, end: string}
     */
    public static function l30WindowEnding(string $endYmd): array
    {
        $end = Carbon::parse($endYmd)->startOfDay();

        return [
            'start' => $end->copy()->subDays(29)->toDateString(),
            'end' => $end->toDateString(),
        ];
    }
}
