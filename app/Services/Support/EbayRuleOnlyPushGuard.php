<?php

namespace App\Services\Support;

use App\Services\EbayRuleSpriceApplyService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * eBay 1 / 2 / 3: the only price that may go to a live listing is the Std-vs-Dil / Sprc Dil rule price
 * the server computes. The tabulator pages post whatever price the browser calculated (or Std, or a
 * typed value) to /push-ebay-price-tabulator & co.; those pushes overwrote the rule price with
 * Std-valued prices. This guard rejects every page-driven push that is not the rule price.
 *
 * Switch off with EBAY_RULE_ONLY_PUSH=false (config/ebay_push.php).
 */
final class EbayRuleOnlyPushGuard
{
    public static function enabled(): bool
    {
        return (bool) config('ebay_push.rule_only', true);
    }

    /**
     * The tabulator posts its own calculated S PRC when it saves. Saved S PRC is what the badge, the blue
     * catch-up and the runner read, so it must be the rule price. Returns the rule price when the posted
     * price differs from it, null when the posted price should be saved as is (guard off, no rule price,
     * or already equal).
     */
    public static function ruleOverride(string $channel, string $sku, float $posted, string $caller = 'saveSprice'): ?float
    {
        if (! self::enabled() || ! ($posted > 0)) {
            return null;
        }

        $channel = EbayRuleSpriceApplyService::normalizeChannel($channel);
        try {
            $rule = EbayRuleSpriceApplyService::for($channel)->ruleSpriceForSku($sku);
        } catch (Throwable $e) {
            return null;
        }
        if ($rule === null || $rule <= 0 || EbayRuleSpriceApplyService::sameCents($rule, round($posted, 2))) {
            return null;
        }

        Log::info('[EbayRuleOnlyPush] page S PRC replaced by rule price on save', [
            'channel' => $channel,
            'sku' => strtoupper(trim($sku)),
            'posted' => round($posted, 2),
            'rule_price' => $rule,
            'caller' => $caller,
            'user' => optional(auth()->user())->name,
        ]);

        return $rule;
    }

    /**
     * @return string|null reason the push must be rejected, null when allowed
     */
    public static function rejectReason(string $channel, string $sku, float $price, string $caller = 'page'): ?string
    {
        if (! self::enabled()) {
            return null;
        }

        $channel = EbayRuleSpriceApplyService::normalizeChannel($channel);
        $sku = strtoupper(trim($sku));

        try {
            $rule = EbayRuleSpriceApplyService::for($channel)->ruleSpriceForSku($sku);
        } catch (Throwable $e) {
            Log::warning('[EbayRuleOnlyPush] could not verify rule price — push blocked', [
                'channel' => $channel, 'sku' => $sku, 'price' => $price, 'caller' => $caller, 'error' => $e->getMessage(),
            ]);

            return 'Push blocked: could not verify the Std-vs-Dil rule price for '.$sku;
        }

        if ($rule === null || $rule <= 0) {
            $reason = 'Push blocked: the Std-vs-Dil rule has no price for '.$sku;
        } elseif (EbayRuleSpriceApplyService::sameCents($rule, round($price, 2))) {
            return null;
        } else {
            $reason = sprintf(
                'Push blocked: $%.2f is not the rule price $%.2f for %s (only Std-vs-Dil / Sprc Dil prices are pushed)',
                $price,
                $rule,
                $sku
            );
        }

        Log::warning('[EbayRuleOnlyPush] page push rejected', [
            'channel' => $channel,
            'sku' => $sku,
            'requested' => round($price, 2),
            'rule_price' => $rule,
            'caller' => $caller,
            'user' => optional(auth()->user())->name,
        ]);

        return $reason;
    }
}
