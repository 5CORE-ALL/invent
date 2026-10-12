<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\EbayController;
use App\Http\Controllers\MarketPlace\EbayThreeController;
use App\Http\Controllers\MarketPlace\EbayTwoController;
use App\Models\MarketplacePercentage;
use Throwable;

/**
 * Export-only helper for eBay 1 / 2 / 3: S GPFT / S GROI / SNROI / SNPFT from a saved S PRC.
 * There is no Dil / Sprc Dil rule here — S PRC is whatever is saved on the SKU.
 */
class EbaySpriceExportPercents
{
    private readonly string $channel;

    public function __construct(string $channel = 'ebay1')
    {
        $this->channel = strtolower(trim($channel));
    }

    public static function for(string $channel): self
    {
        return new self($channel);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{sgpft: ?float, sgroi: ?float, snroi: ?float, snpft: ?float}
     */
    public function paintedPercentsFromSprice(array $item, float $sprice, float $adsPct): array
    {
        $empty = ['sgpft' => null, 'sgroi' => null, 'snroi' => null, 'snpft' => null];
        if (! empty($item['is_parent_summary']) || ! ($sprice > 0)) {
            return $empty;
        }

        $margin = (float) ($item['percentage'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        if (! ($margin > 0)) {
            $margin = $this->takeHome();
        }

        $lp = (float) ($item['LP_productmaster'] ?? $item['lp'] ?? 0);
        $ship = (float) ($item['Ship_productmaster'] ?? $item['ship'] ?? 0);
        $sgpft = (($sprice * $margin - $ship - $lp) / $sprice) * 100;
        $gross = ($sprice * $margin) - $ship - $lp;
        $sgroi = $lp > 0 ? ($gross / $lp) * 100 : null;
        $snroi = $lp > 0 ? (($gross - ($sprice * ($adsPct / 100))) / $lp) * 100 : null;

        return [
            'sgpft' => $sgpft,
            'sgroi' => $sgroi,
            'snroi' => $snroi,
            'snpft' => $sgpft - $adsPct,
        ];
    }

    /** Channel Ads% from the channel's tabulator controller. */
    public function channelAdsPercent(): float
    {
        try {
            return match ($this->channel) {
                'ebay2' => app(EbayTwoController::class)->tabulatorChannelAdsPercent(),
                'ebay3' => app(EbayThreeController::class)->tabulatorChannelAdsPercent(),
                default => app(EbayController::class)->tabulatorChannelAdsPercent(),
            };
        } catch (Throwable $e) {
            return 0.0;
        }
    }

    protected function takeHome(): float
    {
        $margin = MarketplacePercentage::takeHomeForPromoChannel($this->channel);
        if (! ($margin > 0) || $margin > 1) {
            $margin = $margin > 1 ? $margin / 100 : 0.80;
        }

        return $margin;
    }
}
