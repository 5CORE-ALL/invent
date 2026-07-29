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
        if ($expectedState && $state !== '' && ! hash_equals((string) $expectedState, $state)) {
            return response('TikTok OAuth state mismatch. Start again from /tiktok/connect.', 400);
        }

        $exchange = $this->tiktok->exchangeAuthCode($code);
        if (empty($exchange['success'])) {
            return response(
                'TikTok token exchange failed: '.($exchange['message'] ?? 'unknown error'),
                400
            );
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
            $lines[] = 'Cipher: '.(isset($shop['cipher']) ? 'present' : 'missing');
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
