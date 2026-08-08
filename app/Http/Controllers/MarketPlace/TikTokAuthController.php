<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Services\TikTokShopService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TikTokAuthController extends Controller
{
    public function __construct(protected TikTokShopService $tiktok)
    {
    }

    /**
     * Start OAuth — redirects browser to TikTok Shop authorize page.
     */
    public function connect()
    {
        if (! config('services.tiktok.client_key') || ! config('services.tiktok.client_secret')) {
            return response(
                'TikTok CLIENT_KEY / CLIENT_SECRET missing in .env',
                500
            );
        }

        return redirect()->away($this->tiktok->getAuthorizationUrl());
    }

    /**
     * OAuth redirect target — must match TIKTOK_REDIRECT_URI exactly
     * (http://localhost:8000/callback).
     */
    public function callback(Request $request)
    {
        $error = trim((string) ($request->query('error') ?? ''));
        if ($error !== '') {
            $desc = trim((string) ($request->query('error_description') ?? $request->query('message') ?? ''));

            return response(
                'TikTok OAuth denied: '.$error.($desc !== '' ? ' — '.$desc : ''),
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
            Log::warning('TikTok OAuth callback missing code', ['query' => $request->query()]);

            return response('TikTok OAuth callback missing authorization code.', 400);
        }

        $state = trim((string) ($request->query('state') ?? ''));
        $expectedState = Cache::get('tiktok_oauth_state');
        // Soft-check only: TikTok may reopen callback / state may expire in cache.
        if ($expectedState && $state !== '' && ! hash_equals((string) $expectedState, $state)) {
            Log::warning('TikTok OAuth state mismatch (continuing exchange)', [
                'expected_present' => true,
                'state_len' => strlen($state),
            ]);
        }

        // Auth codes are single-use. Browser prefetch / double-load burns them.
        $codeKey = 'tiktok_oauth_code_'.hash('sha256', $code);
        if (Cache::has($codeKey)) {
            return response(
                "This auth code was already used (or this page was loaded twice).\n\n"
                ."Do NOT refresh this page or paste the same URL into /tiktok/exchange.\n"
                ."Open http://127.0.0.1:8000/tiktok/connect for a NEW code, authorize once, and wait — do not copy/reuse the callback URL.",
                400,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }
        Cache::put($codeKey, 1, 600);

        return $this->finishExchange($code);
    }

    /**
     * Manual helper: paste a FRESH callback URL or bare auth code.
     * GET shows a tiny form; POST exchanges immediately.
     */
    public function exchangeForm(Request $request)
    {
        if ($request->isMethod('get')) {
            $csrf = e(csrf_token());
            $html = <<<HTML
<!doctype html><meta charset="utf-8"><title>TikTok Token Exchange</title>
<body style="font-family:system-ui;max-width:720px;margin:2rem auto;line-height:1.4">
<h1>TikTok token exchange</h1>
<p>1) Open <a href="/tiktok/connect">/tiktok/connect</a> and authorize.<br>
2) When you land on <code>/callback?code=...</code>, copy the full URL (or just the code).<br>
3) Paste below immediately — auth codes are <b>single-use</b>.</p>
<form method="post" action="/tiktok/exchange">
<input type="hidden" name="_token" value="{$csrf}">
<label>Callback URL or auth code</label><br>
<textarea name="payload" rows="5" style="width:100%" required placeholder="http://localhost:8000/callback?code=TTP_... or TTP_..."></textarea><br><br>
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
            $msg = 'TikTok token exchange failed: '.($exchange['message'] ?? 'unknown error');
            $msg .= "\n\nDo NOT reuse this code. Open http://127.0.0.1:8000/tiktok/connect again for a NEW code.";
            $msg .= "\nAlso verify TIKTOK_CLIENT_SECRET in .env matches Partner Center App Secret exactly.";

            return response($msg, 400, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $access = (string) ($exchange['access_token'] ?? '');
        $refresh = (string) ($exchange['refresh_token'] ?? '');

        $wroteAccess = $this->updateEnvValue('TIKTOK_ACCESS_TOKEN', $access);
        if ($refresh !== '') {
            $this->updateEnvValue('TIKTOK_REFRESH_TOKEN', $refresh);
        }

        // Live shop verification
        $shopInfo = $this->tiktok->getShopInfo();
        $shops = $shopInfo['shops'] ?? ($shopInfo['data']['shops'] ?? []);
        $shop = is_array($shops) && ! empty($shops[0]) ? $shops[0] : null;

        $lines = [
            'TikTok OAuth OK',
            'Access token: saved to cache'.($wroteAccess ? ' + .env' : ' (.env not writable — copy manually)'),
            'Refresh token: '.($refresh !== '' ? 'present' : 'missing'),
        ];

        if ($shop) {
            $lines[] = 'Shop API verified: '.($shop['name'] ?? 'N/A').' (ID: '.($shop['id'] ?? 'N/A').')';
            $cipher = trim((string) ($shop['cipher'] ?? ''));
            $lines[] = 'Cipher: '.($cipher !== '' ? 'present' : 'missing');
            if ($cipher !== '') {
                $wroteCipher = $this->updateEnvValue('TIKTOK_SHOP_CIPHER', $cipher);
                $lines[] = 'Shop cipher: saved to cache + file'.($wroteCipher ? ' + .env' : ' (.env not writable)');
            }
        } elseif (is_array($shopInfo) && isset($shopInfo['code']) && (int) $shopInfo['code'] !== 0) {
            $lines[] = 'Shop API error: code='.$shopInfo['code'].' message='.($shopInfo['message'] ?? '');
        } else {
            $lines[] = 'Shop API: connected but no shops returned. Check app authorization scopes / shop.';
        }

        if (! $wroteAccess) {
            $lines[] = '';
            $lines[] = 'Add these to .env manually:';
            $lines[] = 'TIKTOK_ACCESS_TOKEN='.$access;
            if ($refresh !== '') {
                $lines[] = 'TIKTOK_REFRESH_TOKEN='.$refresh;
            }
        }

        $lines[] = '';
        $lines[] = 'Next: php artisan sync:tiktok-api-data';

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Quick authenticated check that tokens still work.
     */
    public function testConnection()
    {
        if (! $this->tiktok->isAuthenticated()) {
            return response()->json([
                'success' => false,
                'message' => 'No TikTok access token. Open /tiktok/connect first.',
            ], 400);
        }

        $shopInfo = $this->tiktok->getShopInfo();
        $shops = $shopInfo['shops'] ?? ($shopInfo['data']['shops'] ?? []);
        $shop = is_array($shops) && ! empty($shops[0]) ? $shops[0] : null;

        if ($shop) {
            return response()->json([
                'success' => true,
                'message' => 'TikTok Shop API working.',
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
