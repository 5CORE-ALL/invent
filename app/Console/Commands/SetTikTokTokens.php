<?php

namespace App\Console\Commands;

use App\Services\TikTok2ShopService;
use App\Services\TikTokShopService;
use Illuminate\Console\Command;

class SetTikTokTokens extends Command
{
    protected $signature = 'tiktok:set-tokens
        {--channel=tiktok : tiktok or tiktok2}
        {--access-token= : TikTok Shop access token}
        {--refresh-token= : TikTok Shop refresh token}
        {--write-env : Also persist tokens into .env}';

    protected $description = 'Store TikTok / TikTok 2 Shop access/refresh tokens in cache (and optionally .env)';

    public function handle(): int
    {
        $channel = strtolower(trim((string) $this->option('channel')));
        if (! in_array($channel, ['tiktok', 'tiktok2'], true)) {
            $this->error('Invalid --channel. Use tiktok or tiktok2.');

            return self::FAILURE;
        }

        $tiktok = $channel === 'tiktok2' ? app(TikTok2ShopService::class) : app(TikTokShopService::class);
        $access = trim((string) $this->option('access-token'));
        $refresh = trim((string) $this->option('refresh-token'));

        if ($access === '') {
            $this->error('Missing --access-token');
            $this->line('Complete OAuth first: open /'.($channel === 'tiktok2' ? 'tiktok2' : 'tiktok').'/connect');

            return self::FAILURE;
        }

        $tiktok->setTokens($access, $refresh !== '' ? $refresh : null);
        config([
            'services.'.$channel.'.access_token' => $access,
            'services.'.$channel.'.refresh_token' => $refresh !== '' ? $refresh : null,
        ]);

        if ($this->option('write-env')) {
            $prefix = strtoupper($channel);
            $this->writeEnv($prefix.'_ACCESS_TOKEN', $access);
            if ($refresh !== '') {
                $this->writeEnv($prefix.'_REFRESH_TOKEN', $refresh);
            }
            $this->info('Tokens written to .env');
        }

        $this->info('Tokens stored in cache.');

        $shopInfo = $tiktok->getShopInfo();
        $shops = $shopInfo['shops'] ?? ($shopInfo['data']['shops'] ?? []);
        if (! empty($shops[0])) {
            $shop = $shops[0];
            $this->info('Shop API OK: '.($shop['name'] ?? 'N/A').' (ID: '.($shop['id'] ?? 'N/A').')');

            return self::SUCCESS;
        }

        $this->warn('Tokens saved, but shop info call did not return a shop.');
        if (is_array($shopInfo)) {
            $this->line('code='.($shopInfo['code'] ?? '?'));
            $this->line('message='.($shopInfo['message'] ?? ''));
        }

        return self::SUCCESS;
    }

    protected function writeEnv(string $key, string $value): void
    {
        $path = base_path('.env');
        if (! is_file($path) || ! is_writable($path)) {
            $this->warn(".env not writable; skipped {$key}");

            return;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return;
        }

        $escaped = '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        $line = $key.'='.$escaped;
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents)) {
            $contents = preg_replace_callback($pattern, static fn () => $line, $contents, 1);
        } else {
            $contents = rtrim($contents, "\n")."\n".$line."\n";
        }

        file_put_contents($path, $contents);
    }
}
