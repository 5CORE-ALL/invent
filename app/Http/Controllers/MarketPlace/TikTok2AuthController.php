<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Services\TikTok2ShopService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TikTok2AuthController extends Controller
{
    public function __construct(protected TikTok2ShopService $tiktok)
    {
    }

    /**
     * Start OAuth — redirects browser to TikTok Shop authorize page (app 2).
     */
    public function connect()
    {
        if (! config('services.tiktok2.client_key') || ! config('services.tiktok2.client_secret')) {
            return response(
                'TikTok 2 CLIENT_KEY / CLIENT_SECRET missing in .env (TIKTOK2_CLIENT_KEY / TIKTOK2_CLIENT_SECRET)',
                500
            );
        }

        return redirect()->away($this->tiktok->getAuthorizationUrl());
    }

    /**
     * OAuth redirect target — must match TIKTOK2_REDIRECT_URI exactly
     * (https://inventory.5coremanagement.com/index).
     * When hit without an OAuth code, fall through to /home.
     */
    public function callback(Request $request)
    {
        if ($this->looksLikeAlibabaOAuth($request)) {
            return app(AlibabaSyncController::class)->oauthCallback($request);
        }

        $hasOAuth = $request->filled('code')
            || $request->filled('auth_code')
            || $request->filled('error');

        if (! $hasOAuth) {
            return redirect('/home');
        }

        $error = trim((string) ($request->query('error') ?? ''));
        if ($error !== '') {
            $desc = trim((string) ($request->query('error_description') ?? $request->query('message') ?? ''));

            return response(
                'TikTok 2 OAuth denied: '.$error.($desc !== '' ? ' — '.$desc : ''),
                400
            );
        }

        $code = trim((string) (
            $request->query('code')
            ?? $request->query('auth_code')
            ?? $request->input('code')
            ?? ''
        ));
        if ($code === '') {
            Log::warning('TikTok 2 OAuth callback missing code', ['query' => $request->query()]);

            return response('TikTok 2 OAuth callback missing authorization code.', 400);
        }

        $state = trim((string) ($request->query('state') ?? ''));
        $expectedState = Cache::get('tiktok2_oauth_state');
        if ($expectedState && $state !== '' && ! hash_equals((string) $expectedState, $state)) {
            Log::warning('TikTok 2 OAuth state mismatch (continuing exchange)', [
                'expected_present' => true,
                'state_len' => strlen($state),
            ]);
        }

        return $this->finishExchange($code);
    }

    /**
     * /index is also the Alibaba.com Open Platform callback (portal Callback URL).
     */
    protected function looksLikeAlibabaOAuth(Request $request): bool
    {
        if (! $request->filled('code') && ! $request->filled('error')) {
            return false;
        }

        $code = trim((string) ($request->query('code') ?? $request->input('code') ?? ''));
        if (str_starts_with($code, 'TTP_')) {
            return false;
        }

        $state = trim((string) ($request->query('state') ?? ''));
        $sessionState = (string) $request->session()->get('alibaba_oauth_state', '');
        $cacheState = (string) Cache::get('alibaba_oauth_state', '');

        if ($state !== '' && $sessionState !== '' && hash_equals($sessionState, $state)) {
            return true;
        }
        if ($state !== '' && $cacheState !== '' && hash_equals($cacheState, $state)) {
            return true;
        }

        return $sessionState !== '' || $cacheState !== '';
    }

    /**
     * Manual helper: paste a FRESH callback URL or bare auth code.
     */
    public function exchangeForm(Request $request)
    {
        if ($request->isMethod('get')) {
            $csrf = e(csrf_token());
            $redirect = e((string) config('services.tiktok2.redirect_uri', '/index'));
            $html = <<<HTML
<!doctype html><meta charset="utf-8"><title>TikTok 2 Token Exchange</title>
<body style="font-family:system-ui;max-width:720px;margin:2rem auto;line-height:1.4">
<h1>TikTok 2 token exchange</h1>
<p>1) Open <a href="/tiktok2/connect">/tiktok2/connect</a> and authorize.<br>
2) When you land on <code>{$redirect}?code=...</code>, copy the full URL (or just the code).<br>
3) Paste below immediately — auth codes are <b>single-use</b>.</p>
<form method="post" action="/tiktok2/exchange">
<input type="hidden" name="_token" value="{$csrf}">
<label>Callback URL or auth code</label><br>
<textarea name="payload" rows="5" style="width:100%" required placeholder="{$redirect}?code=TTP_... or TTP_..."></textarea><br><br>
<button type="submit">Exchange now</button>
</form>
</body>
HTML;

            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        $payload = trim((string) $request->input('payload', ''));
        $code = $payload;
        if (str_contains($payload, 'code=')) {
            $parts = parse_url($payload);
            if (! empty($parts['query'])) {
                parse_str($parts['query'], $q);
                $code = (string) ($q['code'] ?? $q['auth_code'] ?? '');
            }
        }

        if ($code === '') {
            return response('Could not find code= in the pasted value.', 400);
        }

        return $this->finishExchange($code);
    }

    protected function finishExchange(string $code)
    {
        $exchange = $this->tiktok->exchangeAuthCode($code);
        if (empty($exchange['success'])) {
            $msg = 'TikTok 2 token exchange failed: '.($exchange['message'] ?? 'unknown error');
            $msg .= "\n\nDo NOT reuse this code. Open /tiktok2/connect again for a NEW code.";
            $msg .= "\nAlso verify TIKTOK2_CLIENT_SECRET in .env matches Partner Center App Secret exactly.";

            return response($msg, 400, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $access = (string) ($exchange['access_token'] ?? '');
        $refresh = (string) ($exchange['refresh_token'] ?? '');

        $wroteAccess = $this->updateEnvValue('TIKTOK2_ACCESS_TOKEN', $access);
        if ($refresh !== '') {
            $this->updateEnvValue('TIKTOK2_REFRESH_TOKEN', $refresh);
        }

        $shopInfo = $this->tiktok->getShopInfo();
        $shops = $shopInfo['shops'] ?? ($shopInfo['data']['shops'] ?? []);
        $shop = is_array($shops) && ! empty($shops[0]) ? $shops[0] : null;

        $lines = [
            'TikTok 2 OAuth OK',
            'Access token: saved to cache'.($wroteAccess ? ' + .env' : ' (.env not writable — copy manually)'),
            'Refresh token: '.($refresh !== '' ? 'present' : 'missing'),
        ];

        if ($shop) {
            $lines[] = 'Shop API verified: '.($shop['name'] ?? 'N/A').' (ID: '.($shop['id'] ?? 'N/A').')';
            $cipher = trim((string) ($shop['cipher'] ?? ''));
            $lines[] = 'Cipher: '.($cipher !== '' ? 'present' : 'missing');
            if ($cipher !== '') {
                $wroteCipher = $this->updateEnvValue('TIKTOK2_SHOP_CIPHER', $cipher);
                $lines[] = 'Shop cipher: saved to cache + file'.($wroteCipher ? ' + .env' : ' (.env not writable)');
            }
            if (! empty($shop['id'])) {
                $this->updateEnvValue('TIKTOK2_SHOP_ID', (string) $shop['id']);
            }
        } elseif (is_array($shopInfo) && isset($shopInfo['code']) && (int) $shopInfo['code'] !== 0) {
            $lines[] = 'Shop API error: code='.$shopInfo['code'].' message='.($shopInfo['message'] ?? '');
        } else {
            $lines[] = 'Shop API: connected but no shops returned. Check app authorization scopes / shop.';
        }

        if (! $wroteAccess) {
            $lines[] = '';
            $lines[] = 'Add these to .env manually:';
            $lines[] = 'TIKTOK2_ACCESS_TOKEN='.$access;
            if ($refresh !== '') {
                $lines[] = 'TIKTOK2_REFRESH_TOKEN='.$refresh;
            }
        }

        $lines[] = '';
        $lines[] = 'Next: php artisan sync:tiktok-api-data --channel=tiktok2';
        $lines[] = 'Then: php artisan tiktok:fetch-orders --channel=tiktok2 --days=60';

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function testConnection()
    {
        if (! $this->tiktok->isAuthenticated()) {
            return response()->json([
                'success' => false,
                'message' => 'No TikTok 2 access token. Open /tiktok2/connect first.',
            ], 400);
        }

        $shopInfo = $this->tiktok->getShopInfo();
        $shops = $shopInfo['shops'] ?? ($shopInfo['data']['shops'] ?? []);
        $shop = is_array($shops) && ! empty($shops[0]) ? $shops[0] : null;

        if ($shop) {
            return response()->json([
                'success' => true,
                'message' => 'TikTok Shop 2 API working.',
                'shop' => [
                    'id' => $shop['id'] ?? null,
                    'name' => $shop['name'] ?? null,
                    'region' => $shop['region'] ?? ($shop['shop_region'] ?? null),
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => is_array($shopInfo)
                ? ('code='.($shopInfo['code'] ?? '?').' '.($shopInfo['message'] ?? 'No shops'))
                : 'Shop info call failed.',
            'raw' => $shopInfo,
        ], 400);
    }

    protected function updateEnvValue(string $key, string $value): bool
    {
        $path = base_path('.env');
        if (! is_file($path) || ! is_writable($path)) {
            return false;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return false;
        }

        $escaped = '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        $line = $key.'='.$escaped;
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents)) {
            $contents = preg_replace_callback($pattern, static fn () => $line, $contents, 1);
        } else {
            $contents = rtrim($contents, "\n")."\n".$line."\n";
        }

        return file_put_contents($path, $contents) !== false;
    }
}
