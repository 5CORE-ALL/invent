<?php

namespace App\Console\Commands;

use App\Services\TikTok2ShopService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Generate TikTok Shop 2 OAuth tokens, save them, optionally sync products + orders.
 *
 * Usage:
 *   php artisan tiktok2:authorize
 *   php artisan tiktok2:authorize --code=TTP_xxx --sync
 *   php artisan tiktok2:authorize --callback="https://inventory.5coremanagement.com/index?code=TTP_xxx" --sync
 */
class TikTok2AuthorizeCommand extends Command
{
    protected $signature = 'tiktok2:authorize
        {--code= : One-time auth code from the TikTok redirect}
        {--callback= : Full callback URL containing code=}
        {--sync : After tokens are saved, sync products + orders}
        {--days=60 : Order fetch window when --sync is used}
        {--open : Open the authorize URL in the default browser (macOS/Linux)}';

    protected $description = 'Authorize TikTok Shop 2: print OAuth URL, exchange code → tokens, optional data sync';

    public function handle(TikTok2ShopService $tiktok): int
    {
        $clientKey = trim((string) config('services.tiktok2.client_key'));
        $clientSecret = trim((string) config('services.tiktok2.client_secret'));
        $redirect = trim((string) config('services.tiktok2.redirect_uri'));

        if ($clientKey === '' || $clientSecret === '') {
            $this->error('Missing TIKTOK2_CLIENT_KEY / TIKTOK2_CLIENT_SECRET in .env');

            return self::FAILURE;
        }

        $this->info('TikTok Shop 2 OAuth');
        $this->line('  App key:     '.substr($clientKey, 0, 6).'…');
        $this->line('  Redirect:    '.($redirect !== '' ? $redirect : '(Partner Center app setting)'));
        $this->newLine();

        $code = $this->resolveCode();
        if ($code === '') {
            $url = $tiktok->getAuthorizationUrl();
            $this->warn('No auth code yet. Complete these steps:');
            $this->line('  1) Open this URL and authorize the shop:');
            $this->newLine();
            $this->line($url);
            $this->newLine();
            $this->line('  2) TikTok redirects to:');
            $this->line('     '.($redirect !== '' ? $redirect : 'https://inventory.5coremanagement.com/index').'?code=TTP_...');
            $this->line('  3) Copy the full URL (or just the code) and run:');
            $this->line('     php artisan tiktok2:authorize --callback="PASTE_URL_HERE" --sync');
            $this->line('     # or');
            $this->line('     php artisan tiktok2:authorize --code=TTP_xxx --sync');
            $this->newLine();
            $localBase = rtrim((string) (env('APP_URL') ?: 'http://127.0.0.1:8000'), '/');
            $this->line('  Local helper: '.$localBase.'/tiktok2/connect  /  '.$localBase.'/tiktok2/exchange');

            if ($this->option('open')) {
                $this->openBrowser($url);
            }

            return self::SUCCESS;
        }

        $this->info('Exchanging auth code for access / refresh tokens…');
        $exchange = $tiktok->exchangeAuthCode($code);
        if (empty($exchange['success'])) {
            $this->error('Token exchange failed: '.($exchange['message'] ?? 'unknown'));
            $this->warn('Auth codes are single-use. Run again without --code to get a NEW authorize URL.');

            return self::FAILURE;
        }

        $access = (string) ($exchange['access_token'] ?? '');
        $refresh = (string) ($exchange['refresh_token'] ?? '');

        $wroteAccess = $this->writeEnv('TIKTOK2_ACCESS_TOKEN', $access);
        if ($refresh !== '') {
            $this->writeEnv('TIKTOK2_REFRESH_TOKEN', $refresh);
        }

        config([
            'services.tiktok2.access_token' => $access,
            'services.tiktok2.refresh_token' => $refresh !== '' ? $refresh : null,
        ]);

        $this->info('Tokens saved to cache'.($wroteAccess ? ' + .env' : ' (.env not writable — copy manually)'));
        if (! $wroteAccess) {
            $this->line('TIKTOK2_ACCESS_TOKEN='.$access);
            if ($refresh !== '') {
                $this->line('TIKTOK2_REFRESH_TOKEN='.$refresh);
            }
        }

        $shopInfo = $tiktok->getShopInfo();
        $shops = $shopInfo['shops'] ?? ($shopInfo['data']['shops'] ?? []);
        $shop = is_array($shops) && ! empty($shops[0]) ? $shops[0] : null;
        if ($shop) {
            $this->info('Shop OK: '.($shop['name'] ?? 'N/A').' (ID: '.($shop['id'] ?? 'N/A').')');
            if (! empty($shop['id'])) {
                $this->writeEnv('TIKTOK2_SHOP_ID', (string) $shop['id']);
            }
        } else {
            $this->warn('Tokens saved but shop info did not return a shop. Check authorization scopes.');
            if (is_array($shopInfo)) {
                $this->line('code='.($shopInfo['code'] ?? '?'));
                $this->line('message='.($shopInfo['message'] ?? ''));
            }
        }

        if (! $this->option('sync')) {
            $this->newLine();
            $this->line('Next: php artisan tiktok2:authorize --sync');
            $this->line('  or: php artisan sync:tiktok-api-data --channel=tiktok2');
            $this->line('  or: php artisan tiktok:fetch-orders --channel=tiktok2 --days=60');

            return self::SUCCESS;
        }

        return $this->runSync((int) $this->option('days'));
    }

    protected function resolveCode(): string
    {
        $code = trim((string) $this->option('code'));
        if ($code !== '') {
            return rawurldecode($code);
        }

        $callback = trim((string) $this->option('callback'));
        if ($callback === '') {
            return '';
        }

        if (str_contains($callback, 'code=')) {
            $parts = parse_url($callback);
            if (! empty($parts['query'])) {
                parse_str($parts['query'], $q);
                $code = (string) ($q['code'] ?? $q['auth_code'] ?? '');

                return rawurldecode(trim($code));
            }
        }

        // Bare code pasted into --callback
        return rawurldecode($callback);
    }

    protected function runSync(int $days): int
    {
        $this->newLine();
        $this->info('Syncing TikTok 2 products…');
        $prodExit = Artisan::call('sync:tiktok-api-data', ['--channel' => 'tiktok2']);
        $this->output->write(Artisan::output());
        if ($prodExit !== 0) {
            $this->error('Product sync failed.');

            return self::FAILURE;
        }

        $this->info("Fetching TikTok 2 orders (last {$days} days)…");
        $ordExit = Artisan::call('tiktok:fetch-orders', [
            '--channel' => 'tiktok2',
            '--days' => $days,
        ]);
        $this->output->write(Artisan::output());
        if ($ordExit !== 0) {
            $this->error('Order sync failed.');

            return self::FAILURE;
        }

        $this->info('TikTok 2 authorize + sync complete.');

        return self::SUCCESS;
    }

    protected function openBrowser(string $url): void
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            exec('open '.escapeshellarg($url).' > /dev/null 2>&1 &');
            $this->info('Opened authorize URL in your browser.');
        } elseif (PHP_OS_FAMILY === 'Linux') {
            exec('xdg-open '.escapeshellarg($url).' > /dev/null 2>&1 &');
            $this->info('Opened authorize URL in your browser.');
        } else {
            $this->warn('Open the URL above manually in your browser.');
        }
    }

    protected function writeEnv(string $key, string $value): bool
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
