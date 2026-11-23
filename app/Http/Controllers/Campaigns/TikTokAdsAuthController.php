<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Services\TikTokAdsApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Authorize our TikTok Marketing API app against an advertiser account.
 *
 *  /tiktok-ads/connect   (auth)   -> redirects to TikTok's advertiser authorization page
 *  /tiktok/callback      (public) -> receives ?auth_code=...&state=... (state must match connect)
 *  /tiktok-ads/exchange  (auth)   -> paste a callback URL / bare auth_code (use when TikTok redirected
 *                                    to a different allowed redirect URL such as the site root)
 */
class TikTokAdsAuthController extends Controller
{
    private const STATE_KEY = 'tiktok_ads_oauth_state';

    public function __construct(private TikTokAdsApiService $ads)
    {
    }

    public function connect()
    {
        if (! $this->ads->isConfigured()) {
            return response('Set TIKTOK_ADS_APP_ID and TIKTOK_ADS_APP_SECRET in .env first.', 500);
        }

        $state = Str::random(40);
        Cache::put(self::STATE_KEY, $state, 900);

        return redirect()->away($this->ads->authorizationUrl($state));
    }

    public function callback(Request $request)
    {
        $expected = (string) Cache::get(self::STATE_KEY, '');
        $state = (string) $request->query('state', '');
        if ($expected === '' || ! hash_equals($expected, $state)) {
            return response('Invalid or expired state. Start again from /tiktok-ads/connect.', 400);
        }

        $code = trim((string) ($request->query('auth_code') ?? $request->query('code') ?? ''));
        if ($code === '') {
            return response('TikTok callback is missing auth_code.', 400);
        }
        Cache::forget(self::STATE_KEY);

        return $this->finish($code);
    }

    public function exchange(Request $request)
    {
        if ($request->isMethod('get')) {
            $csrf = e(csrf_token());

            return response(<<<HTML
<!doctype html><meta charset="utf-8"><title>TikTok Ads token exchange</title>
<body style="font-family:system-ui;max-width:720px;margin:2rem auto;line-height:1.4">
<h1>TikTok Ads token exchange</h1>
<p>1) Open <a href="/tiktok-ads/connect">/tiktok-ads/connect</a> and authorize.<br>
2) Copy the full URL you land on (it contains <code>auth_code=...</code>) and paste it below at once &mdash; the code expires quickly and is single-use.</p>
<form method="post" action="/tiktok-ads/exchange">
<input type="hidden" name="_token" value="{$csrf}">
<textarea name="payload" rows="4" style="width:100%" required></textarea><br><br>
<button type="submit">Exchange</button>
</form></body>
HTML, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        $payload = trim((string) $request->input('payload', ''));
        $code = $payload;
        if (str_contains($payload, 'auth_code=') || str_contains($payload, 'code=')) {
            parse_str((string) (parse_url($payload, PHP_URL_QUERY) ?? ''), $q);
            $code = (string) ($q['auth_code'] ?? $q['code'] ?? '');
        }
        if ($code === '') {
            return response('Could not find auth_code in the pasted value.', 400);
        }

        return $this->finish($code);
    }

    private function finish(string $code)
    {
        try {
            $advertisers = $this->ads->exchangeAuthCode($code);
        } catch (\Throwable $e) {
            return response('TikTok Ads token exchange failed: '.$e->getMessage(), 400, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $lines = ['TikTok Ads authorized. Advertisers saved:'];
        foreach ($advertisers as $a) {
            $lines[] = '- '.$a['advertiser_id'].' '.($a['advertiser_name'] ?? '').' '.($a['currency'] ?? '');
        }
        $lines[] = '';
        $lines[] = 'Next: php artisan tiktok-ads:sync --days=30';

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
