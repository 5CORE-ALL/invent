<?php

namespace App\Http\Middleware;

use App\Support\AmazonAdsRuleScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the BGT rule set a request works on: no scope = BGT Rules (SP + SB), scope=hl = SBGT HL rules.
 * The scope is reset afterwards so it never leaks into the next request on a long-lived worker.
 */
class SetAmazonAdsRuleScope
{
    public function handle(Request $request, Closure $next): Response
    {
        AmazonAdsRuleScope::set($request->input('scope'));
        try {
            return $next($request);
        } finally {
            AmazonAdsRuleScope::set(AmazonAdsRuleScope::DEFAULT);
        }
    }
}
