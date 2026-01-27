<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateEbay2RefreshToken extends Command
{
    protected $signature = 'ebay2:generate-refresh-token {--code= : Authorization code from eBay}';
    protected $description = 'Generate a new eBay2 refresh token using RuName';

    private $ruName = 'Pro_Light_Sound-ProLight-5CoreI-zhnuqwpvs';
    private $baseUrl = 'https://auth.ebay.com/oauth2/authorize';
    private $tokenUrl = 'https://api.ebay.com/identity/v1/oauth2/token';

    public function handle()
    {
        $this->info('=== eBay2 Refresh Token Generator ===');
        $this->newLine();

        $clientId = env('EBAY2_APP_ID');
        $clientSecret = env('EBAY2_CERT_ID');

        if (!$clientId || !$clientSecret) {
            $this->error('✗ Missing EBAY2_APP_ID or EBAY2_CERT_ID in .env file');
            return 1;
        }

        $this->info('Client ID: ' . substr($clientId, 0, 10) . '...');
        $this->info('RuName: ' . $this->ruName);
        $this->newLine();

        // Check if authorization code is provided
        $authCode = $this->option('code');

        if (!$authCode) {
            // Step 1: Generate authorization URL
            $this->info('Step 1: Generate Authorization URL');
            $this->newLine();

            $scopes = [
                'https://api.ebay.com/oauth/api_scope/sell.account',
                'https://api.ebay.com/oauth/api_scope/sell.inventory',
                'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
                'https://api.ebay.com/oauth/api_scope/sell.analytics.readonly',
                'https://api.ebay.com/oauth/api_scope/sell.stores',
                'https://api.ebay.com/oauth/api_scope/sell.finances',
                'https://api.ebay.com/oauth/api_scope/sell.marketing',
            ];

            $scopeString = implode(' ', $scopes);

            $authUrl = $this->baseUrl . '?' . http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $this->ruName,
                'response_type' => 'code',
                'scope' => $scopeString,
            ]);

            $this->line('Please visit the following URL in your browser:');
            $this->newLine();
            $this->line($authUrl);
            $this->newLine();
            $this->info('After authorizing, you will be redirected to a page with a URL containing "code=" parameter.');
            $this->info('Copy the entire authorization code value and run this command again with:');
            $this->newLine();
            $this->line('php artisan ebay2:generate-refresh-token --code=YOUR_AUTHORIZATION_CODE');
            $this->newLine();

            return 0;
        }

        // Step 2: Exchange authorization code for refresh token
        $this->info('Step 2: Exchanging Authorization Code for Refresh Token');
        $this->newLine();

        try {
            // eBay requires redirect_uri to be the RuName value (not the URL) when exchanging authorization code
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post($this->tokenUrl, [
                    'grant_type' => 'authorization_code',
                    'code' => $authCode,
                    'redirect_uri' => $this->ruName, // Must be RuName, not the redirect URL
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['refresh_token'])) {
                    $this->info('✓ Successfully generated refresh token!');
                    $this->newLine();
                    $this->line('Refresh Token:');
                    $this->line($data['refresh_token']);
                    $this->newLine();
                    $this->info('Please update your .env file with:');
                    $this->line('EBAY2_REFRESH_TOKEN=' . $data['refresh_token']);
                    $this->newLine();

                    if (isset($data['access_token'])) {
                        $this->line('Access Token (expires in ' . ($data['expires_in'] ?? 'unknown') . ' seconds):');
                        $this->line(substr($data['access_token'], 0, 50) . '...');
                        $this->newLine();
                    }

                    // Log the success
                    Log::info('eBay2 Refresh Token Generated Successfully', [
                        'ruName' => $this->ruName,
                        'token_length' => strlen($data['refresh_token']),
                    ]);

                    return 0;
                } else {
                    $this->error('✗ Response did not contain refresh_token');
                    $this->line('Response: ' . json_encode($data, JSON_PRETTY_PRINT));
                    return 1;
                }
            } else {
                $this->error('✗ Failed to exchange authorization code');
                $this->line('Status: ' . $response->status());
                $this->line('Response: ' . $response->body());
                $this->newLine();
                $this->line('Request details:');
                $this->line('  - grant_type: authorization_code');
                $this->line('  - code: ' . substr($authCode, 0, 20) . '...');
                $this->line('  - redirect_uri: ' . $this->ruName);
                $this->line('  - token_url: ' . $this->tokenUrl);

                Log::error('eBay2 Refresh Token Generation Failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'redirect_uri' => $this->ruName,
                ]);

                return 1;
            }
        } catch (\Exception $e) {
            $this->error('✗ Exception: ' . $e->getMessage());
            Log::error('eBay2 Refresh Token Generation Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }
}
