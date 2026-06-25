<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WayfairDiagnoseAuthCommand extends Command
{
    protected $signature = 'wayfair:diagnose-auth
                            {--reveal : Print credentials in full (UNSAFE – use only on a private terminal)}
                            {--audience= : Override the audience for this run (e.g. https://sandbox.api.wayfair.com/)}';

    protected $description = 'Diagnose Wayfair OAuth invalid_client failures – shows exactly what is being sent and what comes back.';

    public function handle(): int
    {
        $this->info('=== Wayfair OAuth diagnostic ===');
        $this->newLine();

        $rawClientId = (string) config('services.wayfair.client_id');
        $rawSecret = (string) config('services.wayfair.client_secret');
        $rawAudience = $this->option('audience') !== null
            ? (string) $this->option('audience')
            : (string) config('services.wayfair.audience');

        $trimChars = " \t\n\r\0\x0B\"'";
        $clientId = trim($rawClientId, $trimChars);
        $secret = trim($rawSecret, $trimChars);
        $audience = trim($rawAudience);

        $this->line('1) Values as Laravel sees them (after config cache):');
        $this->renderRow('WAYFAIR_CLIENT_ID', $rawClientId, $clientId);
        $this->renderRow('WAYFAIR_CLIENT_SECRET', $rawSecret, $secret);
        $this->renderRow('WAYFAIR_AUDIENCE', $rawAudience, $audience);
        $this->newLine();

        if ($rawClientId !== $clientId || $rawSecret !== $secret) {
            $this->warn('Found stray whitespace/quotes in client_id or client_secret. Edit .env, remove them, then `php artisan config:clear`.');
            $this->newLine();
        }

        if ($clientId === '' || $secret === '') {
            $this->error('Missing client_id or client_secret. Fix .env first.');
            return 1;
        }

        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $secret,
        ];
        if ($audience !== '') {
            $payload['audience'] = $audience;
        }

        $this->line('2) POSTing to https://sso.auth.wayfair.com/oauth/token with payload:');
        $shown = $payload;
        $shown['client_secret'] = $this->mask($secret);
        if (! $this->option('reveal')) {
            $shown['client_id'] = $this->mask($clientId);
        }
        $this->line('   ' . json_encode($shown, JSON_PRETTY_PRINT));
        $this->newLine();

        try {
            $response = Http::withoutVerifying()
                ->connectTimeout(15)
                ->timeout(60)
                ->asForm()
                ->post('https://sso.auth.wayfair.com/oauth/token', $payload);
        } catch (\Throwable $e) {
            $this->error('Network failure before Wayfair could respond: ' . $e->getMessage());
            $this->line('-> This is a firewall/DNS issue, not a credential issue.');
            return 1;
        }

        $status = $response->status();
        $body = $response->body();
        $this->line("3) Response: HTTP {$status}");
        $this->line('   Body: ' . $body);
        $this->newLine();

        if ($response->successful()) {
            $this->info('SUCCESS – credentials are accepted by Wayfair.');
            $this->line('Token (first 20 chars): ' . substr((string) $response->json('access_token'), 0, 20) . '...');
            return 0;
        }

        $this->error('Wayfair rejected the request.');
        $this->newLine();
        $err = (string) ($response->json('error') ?? '');
        switch ($err) {
            case 'invalid_client':
                $this->line('Diagnosis: Wayfair does not recognize the client_id/client_secret pair (or audience).');
                $this->line('Check, in order:');
                $this->line('  a) Re-copy WAYFAIR_CLIENT_SECRET from the Wayfair partner portal – it may have been rotated.');
                $this->line('  b) Confirm you are using the PROD client (not sandbox). Sandbox usually needs audience=https://sandbox.api.wayfair.com/.');
                $this->line('  c) Re-run with --reveal on a private terminal and verify each character matches the portal.');
                $this->line('  d) Confirm the Wayfair app/client is still ENABLED (partner portal can disable apps).');
                break;
            case 'invalid_grant':
                $this->line('Diagnosis: grant_type rejected. Confirm the app is configured for client_credentials.');
                break;
            case 'unauthorized_client':
                $this->line('Diagnosis: client exists but is not authorized for client_credentials / this audience.');
                break;
            case 'invalid_request':
                $this->line('Diagnosis: missing or malformed parameter. Often a missing/incorrect audience.');
                break;
            default:
                $this->line('Diagnosis: unknown error code "' . $err . '". Share the body above with Wayfair partner support.');
        }

        return 1;
    }

    private function renderRow(string $name, string $raw, string $trimmed): void
    {
        $len = strlen($trimmed);
        $rawLen = strlen($raw);
        $masked = $this->option('reveal') ? $trimmed : $this->mask($trimmed);
        $note = $rawLen === $len ? '' : ' (had ' . ($rawLen - $len) . ' stray chars trimmed!)';
        $this->line(sprintf('   %-22s = %s   [len=%d]%s', $name, $masked === '' ? '<empty>' : $masked, $len, $note));
    }

    private function mask(string $value): string
    {
        $len = strlen($value);
        if ($len === 0) {
            return '';
        }
        if ($len <= 6) {
            return str_repeat('*', $len);
        }
        return substr($value, 0, 3) . str_repeat('*', max(3, $len - 6)) . substr($value, -3);
    }
}
