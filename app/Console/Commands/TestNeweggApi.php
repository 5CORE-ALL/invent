<?php

namespace App\Console\Commands;

use App\Services\NeweggApiService;
use Illuminate\Console\Command;

class TestNeweggApi extends Command
{
    /**
     * Run from a Newegg-whitelisted server:  php artisan newegg:test
     */
    protected $signature = 'newegg:test {--group=contentmgmt : Service group (contentmgmt, ordermgmt, reportmgmt, sellermgmt)} {--raw : Print the full raw response body}';

    protected $description = 'Test the Newegg Marketplace API connection (Service Status endpoint) and print the response';

    public function handle(NeweggApiService $newegg): int
    {
        $group = (string) $this->option('group');

        $this->info('Calling Newegg Service Status API...');
        $this->line('  SellerID:     ' . (config('services.newegg.seller_id') ?: '(not set)'));
        $this->line('  API Key:      ' . $this->mask(config('services.newegg.api_key')));
        $this->line('  Secret Key:   ' . $this->mask(config('services.newegg.secret_key')));
        $this->line('  Base URL:     ' . config('services.newegg.base_url'));
        $this->line('  Servicegroup: ' . $group);
        $this->line('  Endpoint:     /marketplace/' . strtolower($group) . '/servicestatus?sellerid=' . config('services.newegg.seller_id'));
        $this->newLine();

        if (!config('services.newegg.seller_id') || !config('services.newegg.api_key')) {
            $this->warn('Credentials show as (not set). Add NEWEGG_* to this server\'s .env then run: php artisan config:clear');
            $this->newLine();
        }

        $result = $newegg->getServiceStatus($group);

        $this->line('HTTP status: ' . $result['status']);

        if ($result['error']) {
            $this->error('Request error: ' . $result['error']);
            return self::FAILURE;
        }

        if ($result['blocked_by_cloudflare']) {
            $this->error('Blocked by Cloudflare (managed challenge / CAPTCHA page).');
            $this->warn('This IP is not whitelisted in the Newegg Seller Portal. Run this from a whitelisted server.');
            return self::FAILURE;
        }

        if ($result['ok'] && $result['json'] !== null) {
            $this->info('Success! Newegg API responded with JSON:');
            $this->line(json_encode($result['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->warn('Non-JSON or unsuccessful response.');
        $this->line($this->option('raw') ? $result['raw'] : substr($result['raw'], 0, 1000));

        return self::FAILURE;
    }

    private function mask(?string $value): string
    {
        if (!$value) {
            return '(not set)';
        }

        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($value, 0, 4) . str_repeat('*', $len - 8) . substr($value, -4);
    }
}
