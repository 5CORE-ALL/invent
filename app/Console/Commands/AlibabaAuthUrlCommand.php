<?php

namespace App\Console\Commands;

use App\Services\AlibabaAuthService;
use Illuminate\Console\Command;

class AlibabaAuthUrlCommand extends Command
{
    protected $signature = 'alibaba:auth-url
                            {--exchange= : Authorization code from redirect URL}
                            {--write-env : Write access/refresh tokens into .env}';

    protected $description = 'Print Alibaba OAuth URL or exchange code for access_token';

    public function handle(AlibabaAuthService $auth): int
    {
        $code = trim((string) $this->option('exchange'));

        if ($code !== '') {
            $result = $auth->exchangeCodeForToken($code);
            if (empty($result['success'])) {
                $this->error($result['message'] ?? 'Token exchange failed.');

                return self::FAILURE;
            }

            $access = (string) ($result['access_token'] ?? '');
            $refresh = (string) ($result['refresh_token'] ?? '');

            if ($this->option('write-env')) {
                $this->writeEnvTokens($access, $refresh !== '' ? $refresh : null);
                $this->info('Wrote ALIBABA_ACCESS_TOKEN'.($refresh !== '' ? ' and ALIBABA_REFRESH_TOKEN' : '').' to .env');
                $this->call('config:clear');
            } else {
                $this->info('Add to .env:');
                $this->line('ALIBABA_ACCESS_TOKEN='.$access);
                if ($refresh !== '') {
                    $this->line('ALIBABA_REFRESH_TOKEN='.$refresh);
                }
            }

            if (! empty($result['expires_in'])) {
                $this->line('expires_in='.$result['expires_in']);
            }

            return self::SUCCESS;
        }

        $url = $auth->getAuthorizeUrl();
        $this->info('Open this URL (seller login), then run:');
        $this->line('php artisan alibaba:auth-url --exchange=CODE_FROM_REDIRECT --write-env');
        $this->newLine();
        $this->line($url);

        return self::SUCCESS;
    }

    protected function writeEnvTokens(string $accessToken, ?string $refreshToken): void
    {
        $path = base_path('.env');
        $text = is_file($path) ? (string) file_get_contents($path) : '';
        $split = $text === '' ? [] : preg_split("/\r\n|\n|\r/", $text);
        $lines = is_array($split) ? $split : [];
        $updates = [
            'ALIBABA_ACCESS_TOKEN' => $accessToken,
        ];
        if ($refreshToken !== null && $refreshToken !== '') {
            $updates['ALIBABA_REFRESH_TOKEN'] = $refreshToken;
        }

        $seen = [];
        $out = [];
        foreach ($lines as $line) {
            if (str_contains($line, '=') && ! str_starts_with(ltrim($line), '#')) {
                $key = trim(explode('=', $line, 2)[0]);
                if (isset($updates[$key])) {
                    $out[] = $key.'='.$updates[$key];
                    $seen[$key] = true;
                    continue;
                }
            }
            $out[] = $line;
        }
        foreach ($updates as $key => $value) {
            if (empty($seen[$key])) {
                $out[] = $key.'='.$value;
            }
        }

        $ending = (str_ends_with($text, "\n") || str_ends_with($text, "\r\n")) ? PHP_EOL : '';
        file_put_contents($path, implode(PHP_EOL, $out).$ending);
    }
}
