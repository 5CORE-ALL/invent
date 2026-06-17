<?php

namespace App\Console\Commands;

use App\Services\AliExpressAuthService;
use Illuminate\Console\Command;

class AliExpressAuthUrlCommand extends Command
{
    protected $signature = 'aliexpress:auth-url {--exchange= : Authorization code from redirect URL}';

    protected $description = 'Print AliExpress OAuth URL or exchange code for access_token';

    public function handle(AliExpressAuthService $auth): int
    {
        $code = $this->option('exchange');

        if ($code) {
            $result = $auth->exchangeCodeForToken($code);
            if (empty($result['success'])) {
                $this->error($result['message'] ?? 'Token exchange failed.');

                return self::FAILURE;
            }

            $this->info('Add to .env:');
            $this->line('ALIEXPRESS_ACCESS_TOKEN='.$result['access_token']);
            if (! empty($result['refresh_token'])) {
                $this->line('# ALIEXPRESS_REFRESH_TOKEN='.$result['refresh_token']);
            }

            return self::SUCCESS;
        }

        $url = $auth->getAuthorizeUrl();
        $this->info('Open this URL (seller login), then run:');
        $this->line('php artisan aliexpress:auth-url --exchange=CODE_FROM_REDIRECT');
        $this->newLine();
        $this->line($url);

        return self::SUCCESS;
    }
}
