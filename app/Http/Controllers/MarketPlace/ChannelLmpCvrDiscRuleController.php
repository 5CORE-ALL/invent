<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Support\AmazonLmpCvrDiscRule;
use App\Support\EbayRuleSpriceApply;
use Illuminate\Http\Request;

/**
 * "CVR up / down — extra discount on S PRC" rule for eBay 1 / 2 / 3 (the same rule Amazon has in its Sprc Dil modal).
 * Each page keeps its own saved copy. Save Only writes this page; Save All (all=1) writes the three eBay pages.
 */
class ChannelLmpCvrDiscRuleController extends Controller
{
    public function get(string $channel)
    {
        $channel = $this->channel($channel);

        return response()->json(['success' => true] + AmazonLmpCvrDiscRule::config($channel));
    }

    public function save(Request $request, string $channel)
    {
        $channel = $this->channel($channel);
        $cfg = [];
        foreach (['enabled', 'need_lmp', 'down2_lt', 'down2_disc', 'down_lt', 'down_disc', 'up_gt', 'up_disc', 'up2_gt', 'up2_disc'] as $key) {
            $cfg[$key] = $request->input($key);
        }
        $targets = $request->boolean('all') ? AmazonLmpCvrDiscRule::EBAY_CHANNELS : [$channel];
        $payload = [];
        foreach ($targets as $target) {
            $saved = AmazonLmpCvrDiscRule::save($cfg, $target);
            if ($target === $channel) {
                $payload = $saved;
            }
            // Re-save S PRC from the rules right after (nothing is pushed here). Rapid edits share one run.
            EbayRuleSpriceApply::dispatch($target);
        }

        return response()->json(['success' => true, 'channels' => $targets] + $payload);
    }

    private function channel(string $channel): string
    {
        $channel = strtolower(trim($channel));
        abort_unless(in_array($channel, AmazonLmpCvrDiscRule::EBAY_CHANNELS, true), 404);

        return $channel;
    }
}
